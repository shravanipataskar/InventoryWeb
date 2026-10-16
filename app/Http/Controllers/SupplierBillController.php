<?php

namespace App\Http\Controllers;

use App\ProcurementAuditLog;
use App\PurchaseOrder;
use App\Services\PurchasePaymentAllocationService;
use App\Services\SupplierBillService;
use App\Services\SupplierPaymentService;
use App\SupplierBill;
use App\Support\ActivityLogger;
use App\Support\Money;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class SupplierBillController extends Controller
{
    private $bills;
    private $payments;
    private $purchasePayments;

    public function __construct(
        SupplierBillService $bills,
        SupplierPaymentService $payments,
        PurchasePaymentAllocationService $purchasePayments
    )
    {
        $this->bills = $bills;
        $this->payments = $payments;
        $this->purchasePayments = $purchasePayments;
    }

    public function create($purchaseOrderId)
    {
        $purchaseOrder = PurchaseOrder::with(['supplier', 'items.product.unit', 'goodsReceipts'])->findOrFail($purchaseOrderId);
        $redirect = $this->guardBillable($purchaseOrder);
        if ($redirect) {
            return $redirect;
        }

        $positions = $this->bills->quantityPositions($purchaseOrder);

        return view('supplier_bills.create', compact('purchaseOrder', 'positions'));
    }

    public function store(Request $request, $purchaseOrderId)
    {
        $validated = $this->validateBill($request, true);

        $existing = SupplierBill::where('submission_token', $validated['submission_token'])->first();
        if ($existing) {
            return redirect()->route('supplier-bills.show', $existing->id)
                ->with('success', 'This supplier bill was already submitted.');
        }

        $attachment = $this->storeAttachment($request);

        // Start transaction for supplier bill creation and audit logging
        try {
            DB::beginTransaction();

            // Lock the PO so concurrent bills cannot both claim the same accepted quantity.
            $order = PurchaseOrder::lockForUpdate()->with('items.product')->findOrFail($purchaseOrderId);
            if ($order->status === 'cancelled') {
                throw ValidationException::withMessages(['purchase_order' => 'A cancelled purchase order cannot be billed.']);
            }

            $duplicateKey = SupplierBill::duplicateKey($order->supplier_id, $validated['invoice_number']);
            if (SupplierBill::where('duplicate_key', $duplicateKey)->exists()) {
                throw ValidationException::withMessages(['invoice_number' => 'This supplier invoice number has already been recorded.']);
            }

            $lines = $this->bills->buildLines($order, $validated['items']);
            $invoiceTotal = Money::toMinor($validated['invoice_total']);
            $match = $this->bills->match($order, $lines, $invoiceTotal, $order->supplier_id);
            $status = $match['status'] === 'matched' ? SupplierBill::STATUS_UNDER_VERIFICATION : SupplierBill::STATUS_ON_HOLD;

            $bill = SupplierBill::create([
                'bill_number' => 'TEMP-' . Str::uuid(),
                'submission_token' => $validated['submission_token'],
                'supplier_id' => $order->supplier_id,
                'purchase_order_id' => $order->id,
                'invoice_number' => trim($validated['invoice_number']),
                'duplicate_key' => $duplicateKey,
                'invoice_date' => $validated['invoice_date'],
                'due_date' => $validated['due_date'] ?? null,
                'invoice_total' => Money::fromMinor($invoiceTotal),
                'paid_amount' => 0,
                'status' => $status,
                'match_status' => $match['status'],
                'match_result' => $match,
                'attachment_path' => $attachment ? $attachment['path'] : null,
                'attachment_name' => $attachment ? $attachment['name'] : null,
                'notes' => $validated['notes'] ?? null,
                'created_by' => Auth::id(),
            ]);

            $bill->bill_number = 'BILL-' . date('Ymd', strtotime($validated['invoice_date']))
                . '-' . str_pad($bill->id, 4, '0', STR_PAD_LEFT);
            $bill->fill($this->bills->writeLines($bill, $lines));
            $bill->save();

            ProcurementAuditLog::record($bill, 'bill_submitted', null, $status, null, [
                'purchase_order_id' => $order->id,
                'invoice_number' => $bill->invoice_number,
                'match_status' => $match['status'],
                'issues' => count($match['issues']),
            ]);

            DB::commit();
        } catch (QueryException $exception) {
            DB::rollBack();
            $this->discardAttachment($attachment);

            $existing = SupplierBill::where('submission_token', $validated['submission_token'])->first();
            if ($existing) {
                return redirect()->route('supplier-bills.show', $existing->id)
                    ->with('success', 'This supplier bill was already submitted.');
            }

            $order = PurchaseOrder::find($purchaseOrderId);
            if ($order && SupplierBill::where('duplicate_key', SupplierBill::duplicateKey($order->supplier_id, $validated['invoice_number']))->exists()) {
                throw ValidationException::withMessages(['invoice_number' => 'This supplier invoice number has already been recorded.']);
            }

            throw $exception;
        } catch (Throwable $exception) {
            DB::rollBack();
            $this->discardAttachment($attachment);
            throw $exception;
        }

        ActivityLogger::log('Supplier Bill Submitted', 'Supplier Bill', 'Supplier bill ' . $bill->bill_number . ' (invoice ' . $bill->invoice_number . ') was submitted.');

        return redirect()->route('supplier-bills.show', $bill->id)->with(
            $bill->status === SupplierBill::STATUS_ON_HOLD ? 'warning' : 'success',
            $bill->status === SupplierBill::STATUS_ON_HOLD
                ? 'Bill saved but put ON HOLD: it does not match the purchase order and goods received.'
                : 'Bill matched the purchase order and goods received. It is now awaiting approval.'
        );
    }

    public function show($id)
    {
        abort_unless(
            Auth::user()->hasPermission('bills', 'view')
                || Auth::user()->hasPermission('payments', 'view'),
            403
        );

        $bill = SupplierBill::with([
            'supplier', 'purchaseOrder.quotation', 'items.product.unit', 'items.purchaseOrderItem',
            'allocations.payment', 'purchasePaymentAllocations.purchasePayment', 'creator', 'approver',
        ])->findOrFail($id);
        $history = ProcurementAuditLog::forEntity($bill)->get();
        $reservedMinor = $this->payments->reservedMinor($bill->id);
        $canCancel = in_array($bill->status, array_merge(
            SupplierBill::EDITABLE_STATUSES,
            [SupplierBill::STATUS_APPROVED, SupplierBill::STATUS_PARTIALLY_PAID, SupplierBill::STATUS_PAID]
        ), true)
            && $this->purchasePayments->completedSupplierPaymentsMinorForBill($bill->id) === 0
            && $reservedMinor === 0;

        return view('supplier_bills.show', compact('bill', 'history', 'reservedMinor', 'canCancel'));
    }

    public function edit($id)
    {
        $bill = SupplierBill::with('items')->findOrFail($id);
        abort_unless(in_array($bill->status, SupplierBill::EDITABLE_STATUSES, true), 422, 'This bill can no longer be corrected.');
        $purchaseOrder = PurchaseOrder::with(['supplier', 'items.product.unit'])->findOrFail($bill->purchase_order_id);
        $positions = $this->bills->quantityPositions($purchaseOrder, $bill->id);

        return view('supplier_bills.create', compact('purchaseOrder', 'positions', 'bill'));
    }

    public function update(Request $request, $id)
    {
        $validated = $this->validateBill($request, false);
        $attachment = $this->storeAttachment($request);

        // Start transaction for supplier bill correction and audit logging
        try {
            DB::beginTransaction();

            $bill = SupplierBill::lockForUpdate()->findOrFail($id);
            if (!in_array($bill->status, SupplierBill::EDITABLE_STATUSES, true)) {
                throw ValidationException::withMessages(['bill' => 'This bill can no longer be corrected.']);
            }

            $order = PurchaseOrder::lockForUpdate()->with('items.product')->findOrFail($bill->purchase_order_id);

            $duplicateKey = SupplierBill::duplicateKey($order->supplier_id, $validated['invoice_number']);
            if (SupplierBill::where('duplicate_key', $duplicateKey)->where('id', '<>', $bill->id)->exists()) {
                throw ValidationException::withMessages(['invoice_number' => 'This supplier invoice number has already been recorded.']);
            }

            $lines = $this->bills->buildLines($order, $validated['items']);
            $invoiceTotal = Money::toMinor($validated['invoice_total']);
            $match = $this->bills->match($order, $lines, $invoiceTotal, $bill->supplier_id, $bill->id);
            $previous = $bill->status;
            $oldAttachment = $bill->attachment_path;

            $bill->fill([
                'invoice_number' => trim($validated['invoice_number']),
                'duplicate_key' => $duplicateKey,
                'invoice_date' => $validated['invoice_date'],
                'due_date' => $validated['due_date'] ?? null,
                'invoice_total' => Money::fromMinor($invoiceTotal),
                'status' => $match['status'] === 'matched' ? SupplierBill::STATUS_UNDER_VERIFICATION : SupplierBill::STATUS_ON_HOLD,
                'match_status' => $match['status'],
                'match_result' => $match,
                'exception_approved' => false,
                'notes' => $validated['notes'] ?? null,
            ]);

            if ($attachment) {
                $bill->attachment_path = $attachment['path'];
                $bill->attachment_name = $attachment['name'];
            }

            $bill->fill($this->bills->writeLines($bill, $lines));
            $bill->save();

            ProcurementAuditLog::record($bill, 'bill_corrected', $previous, $bill->status, $validated['correction_reason'] ?? null, [
                'match_status' => $match['status'],
                'issues' => count($match['issues']),
                'previous_attachment' => $attachment ? $oldAttachment : null,
            ]);

            DB::commit();
        } catch (Throwable $exception) {
            DB::rollBack();
            $this->discardAttachment($attachment);
            throw $exception;
        }

        ActivityLogger::log('Supplier Bill Corrected', 'Supplier Bill', 'Supplier bill ' . $bill->bill_number . ' was corrected.');

        return redirect()->route('supplier-bills.show', $bill->id)->with('success', 'Bill corrected and re-matched.');
    }

    public function approve(Request $request, $id)
    {
        $validated = $request->validate(['reason' => 'nullable|string|max:2000']);
        $orderId = SupplierBill::where('id', $id)->value('purchase_order_id');

        // Start transaction for supplier bill approval and payment allocation
        try {
            DB::beginTransaction();

            $order = PurchaseOrder::lockForUpdate()->findOrFail($orderId);
            $bill = SupplierBill::lockForUpdate()->findOrFail($id);
            $this->assertNotCreator($bill->created_by, 'approve');
            if (!in_array($bill->status, SupplierBill::EDITABLE_STATUSES, true)) {
                throw ValidationException::withMessages(['bill' => 'Only bills awaiting verification can be approved.']);
            }

            $isException = $bill->status !== SupplierBill::STATUS_UNDER_VERIFICATION || $bill->match_status !== 'matched';
            if ($isException && empty($validated['reason'])) {
                throw ValidationException::withMessages(['reason' => 'A reason is required to approve a bill with discrepancies.']);
            }

            $previous = $bill->status;
            $bill->status = SupplierBill::STATUS_APPROVED;
            $bill->exception_approved = $isException;
            $bill->status_reason = $validated['reason'] ?? null;
            $bill->approved_by = Auth::id();
            $bill->approved_at = now();
            $bill->save();

            ProcurementAuditLog::record($bill, $isException ? 'bill_exception_approved' : 'bill_approved', $previous, $bill->status, $validated['reason'] ?? null, [
                'match_status' => $bill->match_status,
            ]);

            $this->purchasePayments->allocateForOrder($order);

            DB::commit();
        } catch (Throwable $exception) {
            DB::rollBack();
            throw $exception;
        }

        ActivityLogger::log('Supplier Bill Approved', 'Supplier Bill', 'Supplier bill ' . $bill->bill_number . ' was approved for payment' . ($bill->exception_approved ? ' as an exception' : '') . '.');

        return back()->with('success', 'Bill approved for payment.');
    }

    public function reject(Request $request, $id)
    {
        return $this->closeBill($request, $id, SupplierBill::STATUS_REJECTED, SupplierBill::EDITABLE_STATUSES, 'bill_rejected', 'Bill rejected.');
    }

    public function dispute(Request $request, $id)
    {
        return $this->transition($request, $id, SupplierBill::STATUS_DISPUTED, [SupplierBill::STATUS_UNDER_VERIFICATION, SupplierBill::STATUS_ON_HOLD], 'bill_disputed', 'Bill marked as disputed with the supplier.');
    }

    public function cancel(Request $request, $id)
    {
        return $this->closeBill($request, $id, SupplierBill::STATUS_CANCELLED, array_merge(
            SupplierBill::EDITABLE_STATUSES,
            [SupplierBill::STATUS_APPROVED, SupplierBill::STATUS_PARTIALLY_PAID, SupplierBill::STATUS_PAID]
        ), 'bill_cancelled', 'Bill cancelled.');
    }

    public function attachment($id)
    {
        $bill = SupplierBill::findOrFail($id);
        abort_unless($bill->attachment_path && Storage::disk('local')->exists($bill->attachment_path), 404);

        return Storage::disk('local')->download($bill->attachment_path, $bill->attachment_name ?: basename($bill->attachment_path));
    }

    private function transition(Request $request, $id, $toStatus, array $fromStatuses, $event, $message)
    {
        $validated = $request->validate(['reason' => 'required|string|max:2000']);

        // Start transaction for supplier bill status transition and audit logging
        try {
            DB::beginTransaction();

            $bill = SupplierBill::lockForUpdate()->findOrFail($id);
            if (!in_array($bill->status, $fromStatuses, true)) {
                throw ValidationException::withMessages(['bill' => 'This action is not allowed for a bill that is ' . str_replace('_', ' ', $bill->status) . '.']);
            }

            $previous = $bill->status;
            $bill->status = $toStatus;
            $bill->status_reason = $validated['reason'];
            $bill->save();
            ProcurementAuditLog::record($bill, $event, $previous, $toStatus, $validated['reason']);

            DB::commit();
        } catch (Throwable $exception) {
            DB::rollBack();
            throw $exception;
        }

        ActivityLogger::log('Supplier Bill ' . ucfirst(str_replace('_', ' ', $toStatus)), 'Supplier Bill', 'Supplier bill ' . $bill->bill_number . ' is now ' . str_replace('_', ' ', $toStatus) . '.');

        return back()->with('success', $message);
    }

    private function closeBill(Request $request, $id, $toStatus, array $fromStatuses, $event, $message)
    {
        $validated = $request->validate(['reason' => 'required|string|max:2000']);
        $orderId = SupplierBill::where('id', $id)->value('purchase_order_id');

        // Start transaction for supplier bill closure, payment release, and status update
        try {
            DB::beginTransaction();

            $order = PurchaseOrder::lockForUpdate()->findOrFail($orderId);
            $bill = SupplierBill::lockForUpdate()->findOrFail($id);
            if (!in_array($bill->status, $fromStatuses, true)) {
                throw ValidationException::withMessages(['bill' => 'This action is not allowed for a bill that is ' . str_replace('_', ' ', $bill->status) . '.']);
            }

            if ($this->purchasePayments->completedSupplierPaymentsMinorForBill($bill->id) > 0
                || $this->payments->reservedMinor($bill->id) > 0) {
                throw ValidationException::withMessages(['bill' => 'Cancel or reverse the payments linked to this bill first.']);
            }

            $releasedAdvance = Money::toMinor($bill->purchasePaymentAllocations()->sum('amount'));
            $bill->purchasePaymentAllocations()->delete();
            $bill->paid_amount = 0;
            $previous = $bill->status;
            $bill->status = $toStatus;
            $bill->status_reason = $validated['reason'];
            // Release the invoice number so a corrected invoice can be recorded again.
            $bill->duplicate_key = null;
            $bill->save();
            ProcurementAuditLog::record($bill, $event, $previous, $toStatus, $validated['reason'], [
                'released_purchase_advance' => Money::fromMinor($releasedAdvance),
            ]);
            $this->purchasePayments->allocateForOrder($order);

            DB::commit();
        } catch (Throwable $exception) {
            DB::rollBack();
            throw $exception;
        }

        ActivityLogger::log('Supplier Bill ' . ucfirst($toStatus), 'Supplier Bill', 'Supplier bill ' . $bill->bill_number . ' was ' . $toStatus . '.');

        return back()->with('success', $message);
    }

    private function guardBillable(PurchaseOrder $order)
    {
        if ($order->status === 'cancelled') {
            return redirect()->route('purchase-orders.show', $order->id)->with('error', 'A cancelled purchase order cannot be billed.');
        }

        if ($order->goodsReceipts->isEmpty()) {
            return redirect()->route('purchase-orders.show', $order->id)->with('error', 'Record goods received before entering the supplier bill.');
        }

        return null;
    }

    private function validateBill(Request $request, $creating)
    {
        return $request->validate([
            'submission_token' => ($creating ? 'required' : 'nullable') . '|uuid',
            'invoice_number' => 'required|string|max:100',
            'invoice_date' => 'required|date|before_or_equal:today',
            'due_date' => 'nullable|date|after_or_equal:invoice_date',
            'invoice_total' => 'required|numeric|min:0|max:9999999999999',
            'notes' => 'nullable|string|max:4000',
            'correction_reason' => ($creating ? 'nullable' : 'required') . '|string|max:2000',
            'attachment' => 'nullable|file|mimes:pdf,jpg,jpeg,png,webp|max:10240',
            'items' => 'required|array|min:1',
            'items.*.quantity' => 'nullable|numeric|min:0',
            'items.*.rate' => 'required|numeric|min:0',
            'items.*.discount_percent' => 'nullable|numeric|min:0|max:100',
            'items.*.tax_percent' => 'nullable|numeric|min:0|max:100',
        ]);
    }

    private function storeAttachment(Request $request)
    {
        if (!$request->hasFile('attachment')) {
            return null;
        }

        $file = $request->file('attachment');

        return [
            'path' => $file->store('supplier-bills', 'local'),
            'name' => Str::limit(preg_replace('/[^A-Za-z0-9._ -]/', '_', $file->getClientOriginalName()), 180, ''),
        ];
    }

    private function discardAttachment($attachment)
    {
        if ($attachment) {
            Storage::disk('local')->delete($attachment['path']);
        }
    }

    private function assertNotCreator($creatorId, $verb)
    {
        if ($creatorId && (int) $creatorId === (int) Auth::id()) {
            throw ValidationException::withMessages(['bill' => 'You cannot ' . $verb . ' a bill you submitted. Another authorised user must review it.']);
        }
    }
}