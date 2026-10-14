<?php

namespace App\Http\Controllers;

use App\ProcurementAuditLog;
use App\Services\SupplierPaymentService;
use App\Supplier;
use App\SupplierBill;
use App\SupplierPayment;
use App\Support\ActivityLogger;
use App\Support\Money;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SupplierPaymentController extends Controller
{
    private $payments;

    public function __construct(SupplierPaymentService $payments)
    {
        $this->payments = $payments;
    }

    public function index(Request $request)
    {
        $query = SupplierPayment::with(['supplier', 'allocations.bill'])->orderByDesc('id');
        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }
        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', (int) $request->query('supplier_id'));
        }
        $payments = $query->paginate(20)->appends($request->query());
        $suppliers = Supplier::orderBy('name')->get(['id', 'name']);

        return view('supplier_payments.index', compact('payments', 'suppliers'));
    }

    public function create(Request $request)
    {
        $suppliers = Supplier::whereHas('supplierBills', function ($query) {
            $query->whereIn('status', SupplierBill::PAYABLE_STATUSES);
        })->orderBy('name')->get(['id', 'name']);
        $supplier = $suppliers->firstWhere('id', (int) $request->query('supplier_id'));
        $bills = collect();
        if ($supplier) {
            $bills = SupplierBill::where('supplier_id', $supplier->id)
                ->whereIn('status', SupplierBill::PAYABLE_STATUSES)
                ->orderBy('due_date')
                ->orderBy('id')
                ->get()
                ->each(function ($bill) {
                    $bill->setAttribute('available_minor', $this->payments->availableMinor($bill));
                })
                ->filter(function ($bill) {
                    return $bill->available_minor > 0;
                });
        }
        $preselectBillId = (int) $request->query('bill_id');

        return view('supplier_payments.create', compact('suppliers', 'supplier', 'bills', 'preselectBillId'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'submission_token' => 'required|uuid',
            'supplier_id' => 'required|integer|exists:suppliers,id',
            'payment_method' => ['required', Rule::in(array_keys(SupplierPayment::METHODS))],
            'account_reference' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:4000',
            'allocations' => 'required|array|min:1',
            'allocations.*' => 'nullable|numeric|min:0|max:9999999999999',
        ]);

        $existing = SupplierPayment::where('submission_token', $validated['submission_token'])->first();
        if ($existing) {
            return redirect()->route('supplier-payments.show', $existing->id)
                ->with('success', 'This payment request was already submitted.');
        }

        $requested = [];
        foreach ($validated['allocations'] as $billId => $amount) {
            $minor = Money::toMinor($amount);
            if ($minor > 0) {
                $requested[(int) $billId] = $minor;
            }
        }
        if (!$requested) {
            throw ValidationException::withMessages(['allocations' => 'Enter an amount for at least one bill.']);
        }
        ksort($requested);

        try {
            $payment = DB::transaction(function () use ($validated, $requested) {
                $bills = SupplierBill::whereIn('id', array_keys($requested))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                $total = 0;
                foreach ($requested as $billId => $minor) {
                    $bill = $bills->get($billId);
                    if (!$bill || (int) $bill->supplier_id !== (int) $validated['supplier_id']) {
                        throw ValidationException::withMessages(['allocations' => 'Every bill must belong to the selected supplier.']);
                    }
                    if (!in_array($bill->status, SupplierBill::PAYABLE_STATUSES, true)) {
                        throw ValidationException::withMessages(['allocations' => 'Bill ' . $bill->bill_number . ' is not approved for payment.']);
                    }
                    $available = $this->payments->availableMinor($bill);
                    if ($minor > $available) {
                        throw ValidationException::withMessages(['allocations' => 'Bill ' . $bill->bill_number . ' has only ₹' . Money::fromMinor($available) . ' available after pending payment requests.']);
                    }
                    $total += $minor;
                }

                $payment = SupplierPayment::create([
                    'payment_number' => 'TEMP-' . Str::uuid(),
                    'submission_token' => $validated['submission_token'],
                    'supplier_id' => $validated['supplier_id'],
                    'amount' => Money::fromMinor($total),
                    'payment_method' => $validated['payment_method'],
                    'account_reference' => $validated['account_reference'] ?? null,
                    'status' => SupplierPayment::STATUS_PENDING,
                    'notes' => $validated['notes'] ?? null,
                    'requested_by' => Auth::id(),
                ]);
                $payment->payment_number = 'PAY-' . now()->format('Ymd') . '-' . str_pad($payment->id, 4, '0', STR_PAD_LEFT);
                $payment->save();

                foreach ($requested as $billId => $minor) {
                    $payment->allocations()->create(['supplier_bill_id' => $billId, 'amount' => Money::fromMinor($minor)]);
                    ProcurementAuditLog::record($bills->get($billId), 'payment_requested', null, null, null, [
                        'payment_id' => $payment->id,
                        'payment_number' => $payment->payment_number,
                        'amount' => Money::fromMinor($minor),
                    ]);
                }
                ProcurementAuditLog::record($payment, 'payment_requested', null, $payment->status, null, [
                    'amount' => $payment->amount,
                    'bills' => array_keys($requested),
                ]);

                return $payment;
            });
        } catch (QueryException $exception) {
            $existing = SupplierPayment::where('submission_token', $validated['submission_token'])->first();
            if (!$existing) {
                throw $exception;
            }

            return redirect()->route('supplier-payments.show', $existing->id)
                ->with('success', 'This payment request was already submitted.');
        }

        ActivityLogger::log('Payment Requested', 'Supplier Payment', 'Payment request ' . $payment->payment_number . ' for ₹' . $payment->amount . ' was created.');

        return redirect()->route('supplier-payments.show', $payment->id)
            ->with('success', 'Payment request submitted for approval.');
    }

    public function show($id)
    {
        abort_unless(
            Auth::user()->hasPermission('payments', 'view')
                || Auth::user()->hasPermission('bills', 'view'),
            403
        );
        $payment = SupplierPayment::with(['supplier', 'allocations.bill', 'requester', 'approver', 'completer'])->findOrFail($id);
        $history = ProcurementAuditLog::forEntity($payment)->get();

        return view('supplier_payments.show', compact('payment', 'history'));
    }

    public function approve($id)
    {
        $payment = DB::transaction(function () use ($id) {
            $payment = SupplierPayment::lockForUpdate()->findOrFail($id);
            $this->assertStatus($payment, [SupplierPayment::STATUS_PENDING]);
            if ($payment->requested_by && (int) $payment->requested_by === (int) Auth::id()) {
                throw ValidationException::withMessages(['payment' => 'You cannot approve a payment you requested. Another authorised user must approve it.']);
            }
            foreach ($this->payments->lockBills($payment) as $bill) {
                if (!in_array($bill->status, SupplierBill::PAYABLE_STATUSES, true)) {
                    throw ValidationException::withMessages(['payment' => 'Bill ' . $bill->bill_number . ' is no longer payable.']);
                }
            }
            $payment->status = SupplierPayment::STATUS_APPROVED;
            $payment->approved_by = Auth::id();
            $payment->approved_at = now();
            $payment->save();
            ProcurementAuditLog::record($payment, 'payment_approved', SupplierPayment::STATUS_PENDING, $payment->status);

            return $payment;
        });

        ActivityLogger::log('Payment Approved', 'Supplier Payment', 'Payment request ' . $payment->payment_number . ' was approved.');

        return back()->with('success', 'Payment approved. Record it as completed after the transfer is made.');
    }

    public function reject(Request $request, $id)
    {
        return $this->close($request, $id, [SupplierPayment::STATUS_PENDING], SupplierPayment::STATUS_REJECTED, 'payment_rejected', 'Payment request rejected.');
    }

    public function cancel(Request $request, $id)
    {
        return $this->close($request, $id, [SupplierPayment::STATUS_PENDING, SupplierPayment::STATUS_APPROVED], SupplierPayment::STATUS_CANCELLED, 'payment_cancelled', 'Payment request cancelled.');
    }

    public function fail(Request $request, $id)
    {
        return $this->close($request, $id, [SupplierPayment::STATUS_APPROVED], SupplierPayment::STATUS_FAILED, 'payment_failed', 'Payment recorded as failed. The bill balance is unchanged.');
    }

    public function complete(Request $request, $id)
    {
        $payment = SupplierPayment::findOrFail($id);
        $validated = $request->validate([
            'payment_date' => 'required|date|before_or_equal:today',
            'reference_number' => [$payment->payment_method === 'cash' ? 'nullable' : 'required', 'string', 'max:100'],
            'account_reference' => 'nullable|string|max:100',
            'proof' => 'nullable|file|mimes:pdf,jpg,jpeg,png,webp|max:10240',
        ]);
        $proof = $request->hasFile('proof') ? $request->file('proof')->store('supplier-payments', 'local') : null;

        try {
            $payment = DB::transaction(function () use ($id, $validated, $proof, $request) {
                $payment = SupplierPayment::lockForUpdate()->findOrFail($id);
                $this->assertStatus($payment, [SupplierPayment::STATUS_APPROVED]);

                $reference = isset($validated['reference_number']) ? trim($validated['reference_number']) : null;
                if ($reference !== null && $reference !== '' && SupplierPayment::where('id', '<>', $payment->id)
                        ->where('payment_method', $payment->payment_method)
                        ->whereRaw('UPPER(reference_number) = ?', [strtoupper($reference)])
                        ->where('status', SupplierPayment::STATUS_COMPLETED)
                        ->exists()) {
                    throw ValidationException::withMessages(['reference_number' => 'Another completed payment already uses this transaction reference.']);
                }

                $bills = $this->payments->lockBills($payment);
                $payment->fill([
                    'status' => SupplierPayment::STATUS_COMPLETED,
                    'payment_date' => $validated['payment_date'],
                    'reference_number' => $reference ?: null,
                    'account_reference' => $validated['account_reference'] ?? $payment->account_reference,
                    'completed_by' => Auth::id(),
                    'completed_at' => now(),
                ]);
                if ($proof) {
                    $payment->proof_path = $proof;
                    $payment->proof_name = Str::limit(preg_replace('/[^A-Za-z0-9._ -]/', '_', $request->file('proof')->getClientOriginalName()), 180, '');
                }
                $payment->save();

                foreach ($payment->allocations as $allocation) {
                    $bill = $bills->get($allocation->supplier_bill_id);
                    if (!in_array($bill->status, SupplierBill::PAYABLE_STATUSES, true)) {
                        throw ValidationException::withMessages(['payment' => 'Bill ' . $bill->bill_number . ' is no longer payable.']);
                    }
                    if (Money::toMinor($allocation->amount) > $bill->outstandingMinor()) {
                        throw ValidationException::withMessages(['payment' => 'This payment would exceed the outstanding balance of bill ' . $bill->bill_number . '.']);
                    }
                    $this->payments->refreshBillBalance($bill, 'payment_completed', null, [
                        'payment_id' => $payment->id,
                        'payment_number' => $payment->payment_number,
                        'amount' => $allocation->amount,
                    ]);
                }
                ProcurementAuditLog::record($payment, 'payment_completed', SupplierPayment::STATUS_APPROVED, $payment->status, null, [
                    'reference_number' => $payment->reference_number,
                    'payment_date' => $payment->payment_date,
                ]);

                return $payment;
            });
        } catch (\Throwable $exception) {
            if ($proof) {
                Storage::disk('local')->delete($proof);
            }
            throw $exception;
        }

        ActivityLogger::log('Payment Completed', 'Supplier Payment', 'Payment ' . $payment->payment_number . ' for ₹' . $payment->amount . ' was recorded as completed.');

        return back()->with('success', 'Payment recorded as completed and bill balances updated.');
    }

    public function reverse(Request $request, $id)
    {
        $validated = $request->validate(['reason' => 'required|string|max:2000']);

        $payment = DB::transaction(function () use ($id, $validated) {
            $payment = SupplierPayment::lockForUpdate()->findOrFail($id);
            $this->assertStatus($payment, [SupplierPayment::STATUS_COMPLETED]);
            $bills = $this->payments->lockBills($payment);

            $payment->status = SupplierPayment::STATUS_REVERSED;
            $payment->status_reason = $validated['reason'];
            $payment->save();

            foreach ($payment->allocations as $allocation) {
                $this->payments->refreshBillBalance($bills->get($allocation->supplier_bill_id), 'payment_reversed', $validated['reason'], [
                    'payment_id' => $payment->id,
                    'payment_number' => $payment->payment_number,
                    'amount' => $allocation->amount,
                ]);
            }
            ProcurementAuditLog::record($payment, 'payment_reversed', SupplierPayment::STATUS_COMPLETED, $payment->status, $validated['reason']);

            return $payment;
        });

        ActivityLogger::log('Payment Reversed', 'Supplier Payment', 'Payment ' . $payment->payment_number . ' was reversed.');

        return back()->with('success', 'Payment reversed. The bill outstanding balance has been restored.');
    }

    public function proof($id)
    {
        $payment = SupplierPayment::findOrFail($id);
        abort_unless($payment->proof_path && Storage::disk('local')->exists($payment->proof_path), 404);

        return Storage::disk('local')->download($payment->proof_path, $payment->proof_name ?: basename($payment->proof_path));
    }

    private function close(Request $request, $id, array $fromStatuses, $toStatus, $event, $message)
    {
        $validated = $request->validate(['reason' => 'required|string|max:2000']);

        $payment = DB::transaction(function () use ($id, $validated, $fromStatuses, $toStatus, $event) {
            $payment = SupplierPayment::lockForUpdate()->findOrFail($id);
            $this->assertStatus($payment, $fromStatuses);
            $previous = $payment->status;
            $payment->status = $toStatus;
            $payment->status_reason = $validated['reason'];
            $payment->save();
            ProcurementAuditLog::record($payment, $event, $previous, $toStatus, $validated['reason']);

            return $payment;
        });

        ActivityLogger::log('Payment ' . ucfirst($toStatus), 'Supplier Payment', 'Payment request ' . $payment->payment_number . ' was ' . $toStatus . '.');

        return back()->with('success', $message);
    }

    private function assertStatus(SupplierPayment $payment, array $allowed)
    {
        if (!in_array($payment->status, $allowed, true)) {
            throw ValidationException::withMessages(['payment' => 'This action is not allowed for a payment that is ' . str_replace('_', ' ', $payment->status) . '.']);
        }
    }
}
