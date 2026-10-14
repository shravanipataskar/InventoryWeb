<?php

namespace App\Http\Controllers;

use App\Category;
use App\Product;
use App\Quotation;
use App\QuotationRequest;
use App\Store;
use App\Supplier;
use App\PurchaseOrder;
use App\PurchasePayment;
use App\PurchasePaymentInstallment;
use App\ProcurementAuditLog;
use App\Services\PurchasePaymentAllocationService;
use App\Support\ActivityLogger;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class QuotationController extends Controller
{
    public function index(Request $request)
    {
        $requests = QuotationRequest::with(['creator', 'quotations.supplier'])
            ->withCount('quotations')
            ->orderByDesc('id')
            ->paginate(20)
            ->appends($request->query());

        return view('quotations.index', compact('requests'));
    }

    public function create(Request $request)
    {
        $categories = Category::where('is_active', true)->orderBy('name')->get();
        $products = Product::where('is_active', true)->with(['category', 'unit'])->orderBy('name')->get();
        $suppliers = Supplier::where('is_active', true)->orderBy('name')->get();
        $stores = Store::where('is_active', true)->orderBy('name')->get();
        $prefillProduct = $products->firstWhere('id', (int) $request->query('product_id'));
        $requestedQuantity = filter_var($request->query('quantity'), FILTER_VALIDATE_FLOAT);
        $prefillItems = $prefillProduct ? [[
            'category_id' => $prefillProduct->category_id,
            'product_id' => $prefillProduct->id,
            'quantity' => $requestedQuantity !== false && $requestedQuantity > 0
                ? $requestedQuantity
                : ((float) $prefillProduct->reorder_quantity > 0
                    ? (float) $prefillProduct->reorder_quantity
                    : max(1, (float) $prefillProduct->minimum_stock - (float) $prefillProduct->current_stock)),
        ]] : null;

        return view('quotations.create', compact('categories', 'products', 'suppliers', 'stores', 'prefillItems'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'request_date' => 'required|date',
            'required_date' => 'nullable|date|after_or_equal:request_date',
            'store_id' => 'required|integer|exists:stores,id,is_active,1',
            'remarks' => 'nullable|string|max:4000',
            'supplier_ids' => 'required|array|min:1',
            'supplier_ids.*' => 'integer|distinct|exists:suppliers,id,is_active,1',
            'items' => 'required|array|min:1',
            'items.*.category_id' => 'required|exists:categories,id',
            'items.*.product_id' => [
                'required',
                'distinct',
                Rule::exists('products', 'id')->where('is_active', true),
            ],
            'items.*.quantity' => 'required|numeric|min:0.01',
        ], [
            'supplier_ids.required' => 'Please select at least one supplier.',
            'supplier_ids.array' => 'Please select at least one supplier.',
            'supplier_ids.min' => 'Please select at least one supplier.',
        ]);

        foreach ($validated['items'] as $item) {
            if (!Product::where('id', $item['product_id'])->where('category_id', $item['category_id'])->where('is_active', true)->exists()) {
                throw ValidationException::withMessages(['items' => 'Each product must belong to its selected category.']);
            }
        }

        $quotationRequest = DB::transaction(function () use ($validated) {
            $requestRecord = QuotationRequest::create([
                'quotation_request_code' => 'TEMP-' . Str::uuid(),
                'request_date' => $validated['request_date'],
                'required_date' => $validated['required_date'] ?? null,
                'store_id' => $validated['store_id'],
                'created_by' => Auth::id(),
                'status' => 'draft',
                'remarks' => $validated['remarks'] ?? null,
            ]);
            $requestRecord->quotation_request_code = 'QR-' . date('Ymd', strtotime($validated['request_date']))
                . '-' . str_pad($requestRecord->id, 4, '0', STR_PAD_LEFT);
            $requestRecord->save();

            foreach ($validated['supplier_ids'] as $supplierId) {
                $quotation = $requestRecord->quotations()->create([
                    'supplier_id' => $supplierId,
                    'quotation_code' => 'TEMP-' . Str::uuid(),
                    'quotation_date' => $validated['request_date'],
                    'status' => 'draft',
                    'created_by' => Auth::id(),
                ]);
                $quotation->quotation_code = 'QT-' . date('Ymd', strtotime($validated['request_date']))
                    . '-' . str_pad($quotation->id, 4, '0', STR_PAD_LEFT);
                $quotation->save();

                foreach ($validated['items'] as $item) {
                    $product = Product::findOrFail($item['product_id']);
                    $quotation->items()->create([
                        'product_id' => $product->id,
                        'category_id' => $product->category_id,
                        'unit_id' => $product->unit_id,
                        'quantity' => round((float) $item['quantity'], 2),
                    ]);
                }
            }

            return $requestRecord;
        });

        ActivityLogger::log('Quotation Created', 'Quotation', 'Quotation request ' . $quotationRequest->quotation_request_code . ' was created.');

        return redirect()->route('quotations.show', $quotationRequest->id)
            ->with('success', 'Quotation request created for the selected suppliers.');
    }

    public function show($id)
    {
        $quotationRequest = $this->loadRequest($id);

        return view('quotations.show', compact('quotationRequest'));
    }

    public function purchaseDetails(PurchasePaymentAllocationService $paymentAllocations, $id)
    {
        $quotation = Quotation::with([
            'request.store',
            'supplier',
            'items.product.category',
            'items.product.unit',
            'purchaseOrder.items.product.category',
            'purchaseOrder.items.product.unit',
            'purchaseOrder.payments.enteredBy',
            'purchaseOrder.installments.payments',
        ])->findOrFail($id);

        abort_unless($quotation->status === 'approved', 404);

        $purchaseOrder = $quotation->purchaseOrder;
        if ($purchaseOrder) {
            $quotationRates = $quotation->items->keyBy('product_id');
            foreach ($purchaseOrder->items as $item) {
                $quotationItem = $quotationRates->get($item->product_id);
                $basicMinor = Money::multiply(Money::toMinor($item->quantity), Money::toMinor($item->purchase_rate));
                $discountMinor = Money::percentOf($basicMinor, Money::toMinor($item->discount_percent));
                $item->setAttribute('quotation_rate', $quotationItem ? $quotationItem->supplier_rate : null);
                $item->setAttribute('basic_amount', Money::fromMinor($basicMinor - $discountMinor));
                $item->setAttribute('tax_amount', Money::fromMinor(
                    Money::toMinor($item->cgst_amount) + Money::toMinor($item->sgst_amount)
                ));
            }
        }
        $paidMinor = $purchaseOrder ? $paymentAllocations->paidMinorForOrder($purchaseOrder->id) : 0;
        $totalPaid = Money::fromMinor($paidMinor);
        $totalPayable = $purchaseOrder ? (float) $purchaseOrder->grand_total : (float) $quotation->grand_total;
        $payableMinor = Money::toMinor($totalPayable);
        $committedMinor = $purchaseOrder ? $paymentAllocations->committedMinorForOrder($purchaseOrder->id) : 0;
        $remaining = Money::fromMinor(max(0, $payableMinor - $paidMinor));
        $eligibleRemaining = Money::fromMinor(max(0, $payableMinor - $committedMinor));
        $eligiblePercentage = $payableMinor > 0
            ? round((Money::toMinor($eligibleRemaining) / $payableMinor) * 100, 2)
            : 0;
        $committedAmount = Money::fromMinor($committedMinor);
        if ($purchaseOrder) {
            foreach ($purchaseOrder->payments as $payment) {
                $payment->setAttribute('voucher_number', 'PP-' . str_pad($payment->id, 8, '0', STR_PAD_LEFT));
                $payment->setAttribute('payment_percentage', $payableMinor > 0
                    ? round((Money::toMinor($payment->amount) / $payableMinor) * 100, 2)
                    : 0);
            }
        }

        $paymentPercentage = $totalPayable > 0 ? round(($totalPaid / $totalPayable) * 100, 2) : 0;
        $hasOverdueInstallment = $purchaseOrder && $purchaseOrder->installments->contains(function ($installment) {
            return $installment->status() === 'Overdue';
        });
        $paymentStatus = $remaining <= 0
            ? 'Paid'
            : ($hasOverdueInstallment ? 'Overdue' : ($totalPaid > 0 ? 'Partially Paid' : 'Pending'));
        $canManagePayments = Auth::user() && Auth::user()->hasPermission('purchase', 'approve');
        $canViewPurchaseOrder = Auth::user() && Auth::user()->hasPermission('purchase', 'view');
        $canEditPurchaseItems = Auth::user()
            && (Auth::user()->hasPermission('purchase', 'edit') || $canManagePayments)
            && $purchaseOrder
            && $purchaseOrder->status === 'draft'
            && !$purchaseOrder->goodsReceipts()->exists()
            && !$purchaseOrder->supplierBills()->exists();

        return view('quotations.purchase-details', compact(
            'quotation',
            'totalPaid',
            'totalPayable',
            'remaining',
            'eligibleRemaining',
            'eligiblePercentage',
            'committedAmount',
            'paymentPercentage',
            'paymentStatus',
            'canManagePayments',
            'canViewPurchaseOrder',
            'canEditPurchaseItems'
        ));
    }

    public function createPurchaseOrder(Request $request, $id)
    {
        $quotation = Quotation::with(['items', 'request'])->findOrFail($id);
        abort_unless($quotation->status === 'approved', 404);

        $validated = $request->validate([
            'items' => 'required|array',
            'items.*.purchase_rate' => 'required|numeric|min:0.01',
            'items.*.discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100', 'regex:/^\d+(\.\d{1,2})?$/'],
        ]);

        $order = DB::transaction(function () use ($quotation, $validated) {
            $lockedQuotation = Quotation::with(['items', 'request'])->lockForUpdate()->findOrFail($quotation->id);
            if ($lockedQuotation->purchaseOrder) {
                return $lockedQuotation->purchaseOrder;
            }

            return $this->createPurchaseOrderFromNegotiatedQuotation($lockedQuotation, $validated['items']);
        });

        ActivityLogger::log('Purchase Order Generated', 'Purchase', 'Purchase order ' . $order->po_number . ' was generated from quotation ' . $quotation->quotation_code . '.');

        return redirect()->route('quotations.purchase-details', $quotation->id)
            ->with('success', 'Purchase order generated. You can now record payments directly.');
    }

    public function updatePurchaseDiscounts(Request $request, PurchasePaymentAllocationService $paymentAllocations, $id)
    {
        $user = $request->user();
        abort_unless($user && ($user->hasPermission('purchase', 'edit') || $user->hasPermission('purchase', 'approve')), 403);

        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.discount_percent' => ['required', 'numeric', 'min:0', 'max:100', 'regex:/^\d+(\.\d{1,2})?$/'],
        ]);
        $quotation = Quotation::findOrFail($id);
        abort_unless($quotation->status === 'approved', 404);

        $order = DB::transaction(function () use ($quotation, $validated, $paymentAllocations) {
            $lockedQuotation = Quotation::lockForUpdate()->findOrFail($quotation->id);
            $order = PurchaseOrder::where('quotation_id', $lockedQuotation->id)
                ->lockForUpdate()
                ->firstOrFail();
            if ($order->status !== 'draft'
                || $order->goodsReceipts()->exists()
                || $order->supplierBills()->exists()) {
                throw ValidationException::withMessages([
                    'purchase_order' => 'Discounts can only be changed on a draft purchase order before goods receipts or supplier bills are linked.',
                ]);
            }

            $items = $order->items()->lockForUpdate()->get()->keyBy('id');
            $submittedIds = array_map('strval', array_keys($validated['items']));
            $existingIds = $items->keys()->map(function ($key) {
                return (string) $key;
            })->all();
            sort($submittedIds);
            sort($existingIds);
            if ($submittedIds !== $existingIds) {
                throw ValidationException::withMessages([
                    'items' => 'Submit a discount value for every purchase-order item.',
                ]);
            }

            $subtotalMinor = $discountMinor = $cgstMinor = $sgstMinor = 0;
            foreach ($items as $itemId => $item) {
                $discountPercentMinor = Money::toMinor($validated['items'][$itemId]['discount_percent']);
                $basicMinor = Money::multiply(
                    Money::toMinor($item->quantity),
                    Money::toMinor($item->purchase_rate)
                );
                $lineDiscountMinor = Money::percentOf($basicMinor, $discountPercentMinor);
                $taxableMinor = $basicMinor - $lineDiscountMinor;
                $lineCgstMinor = Money::percentOf($taxableMinor, Money::toMinor($item->cgst_rate));
                $lineSgstMinor = Money::percentOf($taxableMinor, Money::toMinor($item->sgst_rate));
                $item->update([
                    'discount_percent' => Money::fromMinor($discountPercentMinor),
                    'cgst_amount' => Money::fromMinor($lineCgstMinor),
                    'sgst_amount' => Money::fromMinor($lineSgstMinor),
                    'total_amount' => Money::fromMinor($taxableMinor + $lineCgstMinor + $lineSgstMinor),
                ]);
                $subtotalMinor += $basicMinor;
                $discountMinor += $lineDiscountMinor;
                $cgstMinor += $lineCgstMinor;
                $sgstMinor += $lineSgstMinor;
            }

            $grandTotalMinor = $subtotalMinor - $discountMinor + $cgstMinor + $sgstMinor;
            if ($grandTotalMinor < $paymentAllocations->committedMinorForOrder($order->id)) {
                throw ValidationException::withMessages([
                    'items' => 'The updated purchase payable cannot be less than payments already recorded or reserved.',
                ]);
            }

            $order->update([
                'subtotal' => Money::fromMinor($subtotalMinor),
                'discount_amount' => Money::fromMinor($discountMinor),
                'tax_amount' => Money::fromMinor($cgstMinor + $sgstMinor),
                'cgst_total' => Money::fromMinor($cgstMinor),
                'sgst_total' => Money::fromMinor($sgstMinor),
                'grand_total' => Money::fromMinor($grandTotalMinor),
            ]);
            ProcurementAuditLog::recordIfAvailable($order, 'po_discounts_updated', $order->status, $order->status, null, [
                'discount_amount' => Money::fromMinor($discountMinor),
                'grand_total' => Money::fromMinor($grandTotalMinor),
            ]);

            return $order;
        });

        ActivityLogger::log('Purchase Discounts Updated', 'Purchase', 'Discounts were updated on purchase order ' . $order->po_number . '.');

        return redirect()->route('quotations.purchase-details', $quotation->id)
            ->with('success', 'Discounts and purchase totals updated.');
    }

    public function storePurchaseInstallment(Request $request, $id, PurchasePaymentAllocationService $paymentAllocations)
    {
        $validated = $request->validate([
            'due_date' => 'required|date',
            'planned_amount' => ['required', 'numeric', 'min:0.01', 'regex:/^\d+(\.\d{1,2})?$/'],
            'submission_token' => 'required|uuid',
        ]);
        $quotation = Quotation::findOrFail($id);
        abort_unless($quotation->status === 'approved', 404);

        $installment = DB::transaction(function () use ($quotation, $validated, $paymentAllocations) {
            $lockedQuotation = Quotation::lockForUpdate()->findOrFail($quotation->id);
            $order = $lockedQuotation->purchaseOrder;
            if (!$order) {
                throw ValidationException::withMessages(['purchase_order' => 'Generate the purchase order before creating an installment plan.']);
            }
            $order = PurchaseOrder::lockForUpdate()->findOrFail($order->id);
            if ($order->status === 'cancelled') {
                throw ValidationException::withMessages(['purchase_order' => 'A cancelled purchase order cannot have a payment plan.']);
            }
            if (PurchasePaymentInstallment::where('submission_token', $validated['submission_token'])->exists()) {
                return null;
            }

            $activePlanMinor = 0;
            foreach (PurchasePaymentInstallment::where('purchase_order_id', $order->id)
                ->whereNull('cancelled_at')
                ->lockForUpdate()
                ->get() as $existing) {
                $activePlanMinor += Money::toMinor($existing->planned_amount);
            }
            $amountMinor = Money::toMinor($validated['planned_amount']);
            $eligibleMinor = max(0, Money::toMinor($order->grand_total) - $paymentAllocations->committedMinorForOrder($order->id));
            if ($activePlanMinor + $amountMinor > $eligibleMinor) {
                throw ValidationException::withMessages([
                    'planned_amount' => 'The installment plan cannot exceed the eligible purchase outstanding amount of ₹' . number_format(Money::fromMinor($eligibleMinor), 2) . '.',
                ]);
            }

            $installmentNumber = (int) PurchasePaymentInstallment::where('purchase_order_id', $order->id)->max('installment_number') + 1;
            $installment = PurchasePaymentInstallment::create([
                'purchase_order_id' => $order->id,
                'installment_number' => $installmentNumber,
                'due_date' => $validated['due_date'],
                'planned_amount' => Money::fromMinor($amountMinor),
                'submission_token' => $validated['submission_token'],
            ]);
            ProcurementAuditLog::recordIfAvailable($order, 'po_installment_scheduled', null, null, null, [
                'installment_id' => $installment->id,
                'installment_number' => $installment->installment_number,
                'due_date' => $installment->due_date->toDateString(),
                'planned_amount' => $installment->planned_amount,
            ]);

            return $installment;
        });
        if ($installment) {
            ActivityLogger::log('Purchase Installment Scheduled', 'Purchase', 'Installment ' . $installment->installment_number . ' was scheduled for purchase order ' . $installment->purchase_order_id . '.');
        }

        return redirect()->route('quotations.purchase-details', $quotation->id)
            ->with('success', 'Installment added to the payment plan.');
    }

    public function cancelPurchaseInstallment($id, $installmentId)
    {
        $quotation = Quotation::findOrFail($id);
        abort_unless($quotation->status === 'approved', 404);

        $cancelled = DB::transaction(function () use ($quotation, $installmentId) {
            $lockedQuotation = Quotation::lockForUpdate()->findOrFail($quotation->id);
            $order = $lockedQuotation->purchaseOrder;
            if (!$order) {
                throw ValidationException::withMessages(['installment' => 'No purchase order exists for this quotation.']);
            }
            PurchaseOrder::lockForUpdate()->findOrFail($order->id);
            $installment = PurchasePaymentInstallment::where('purchase_order_id', $order->id)
                ->lockForUpdate()
                ->findOrFail($installmentId);
            if ($installment->cancelled_at) {
                return false;
            }
            if ($installment->payments()->where('status', 'paid')->exists()) {
                throw ValidationException::withMessages(['installment' => 'An installment with recorded payments cannot be cancelled.']);
            }

            $installment->cancelled_at = now();
            $installment->save();
            ProcurementAuditLog::recordIfAvailable($order, 'po_installment_cancelled', null, null, null, [
                'installment_id' => $installment->id,
                'installment_number' => $installment->installment_number,
                'planned_amount' => $installment->planned_amount,
            ]);

            return true;
        });
        if ($cancelled) {
            ActivityLogger::log('Purchase Installment Cancelled', 'Purchase', 'Installment ' . $installmentId . ' was cancelled for purchase order ' . $quotation->purchase_order_id . '.');
        }

        return redirect()->route('quotations.purchase-details', $quotation->id)
            ->with('success', 'Installment cancelled.');
    }

    public function savePurchasePayment(Request $request, PurchasePaymentAllocationService $paymentAllocations, $id)
    {
        $quotation = Quotation::with(['items.product', 'request'])->findOrFail($id);
        abort_unless($quotation->status === 'approved', 404);

        $inputMode = $request->input('payment_input_mode', 'amount');
        $request->merge(['payment_input_mode' => $inputMode]);
        $validated = $request->validate([
            'payment_date' => 'required|date',
            'payment_input_mode' => ['required', Rule::in(['amount', 'percentage'])],
            'amount' => $inputMode === 'amount'
                ? ['required', 'numeric', 'min:0.01', 'regex:/^\d+(\.\d{1,2})?$/']
                : ['nullable'],
            'payment_percentage' => $inputMode === 'percentage'
                ? ['required', 'numeric', 'min:0.01', 'max:100', 'regex:/^\d+(\.\d{1,2})?$/']
                : ['nullable'],
            'payment_method' => ['nullable', Rule::in(PurchasePayment::METHODS)],
            'reference_number' => 'nullable|string|max:100',
            'remarks' => 'nullable|string|max:2000',
            'submission_token' => 'required|uuid',
            'installment_id' => 'nullable|integer',
            'items' => $quotation->purchase_order_id ? 'nullable|array' : 'required|array',
            'items.*.purchase_rate' => $quotation->purchase_order_id ? 'nullable|numeric|min:0.01' : 'required|numeric|min:0.01',
            'items.*.discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100', 'regex:/^\d+(\.\d{1,2})?$/'],
        ]);
        if (!empty($validated['installment_id'])
            && \Carbon\Carbon::parse($validated['payment_date'])->startOfDay()->gt(\Carbon\Carbon::today())) {
            throw ValidationException::withMessages([
                'payment_date' => 'An actual installment payment date cannot be in the future.',
            ]);
        }

        $purchaseOrder = DB::transaction(function () use ($quotation, $validated, $paymentAllocations) {
            $lockedQuotation = Quotation::with('items')->lockForUpdate()->findOrFail($quotation->id);
            if (PurchasePayment::where('submission_token', $validated['submission_token'])->exists()) {
                return $lockedQuotation->purchaseOrder()->firstOrFail();
            }

            $order = $lockedQuotation->purchaseOrder;
            if (!$order) {
                $order = $this->createPurchaseOrderFromNegotiatedQuotation($lockedQuotation, $validated['items']);
            } else {
                $order = PurchaseOrder::lockForUpdate()->findOrFail($order->id);
                if ($order->status === 'cancelled') {
                    throw ValidationException::withMessages(['purchase_order' => 'A cancelled purchase order cannot receive further payments.']);
                }
            }

            $committed = $paymentAllocations->committedMinorForOrder($order->id);
            $remaining = max(0, Money::toMinor($order->grand_total) - $committed);
            if ($remaining <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'The full payable amount has already been paid. No further payment can be added.',
                ]);
            }

            $amount = $validated['payment_input_mode'] === 'percentage'
                ? Money::percentOf($remaining, Money::toMinor($validated['payment_percentage']))
                : Money::toMinor($validated['amount']);
            if ($amount <= 0) {
                throw ValidationException::withMessages([
                    $validated['payment_input_mode'] === 'percentage' ? 'payment_percentage' : 'amount'
                        => 'The calculated payment amount must be greater than zero.',
                ]);
            }
            $installment = null;
            $installmentRemaining = $remaining;
            if (!empty($validated['installment_id'])) {
                $installment = PurchasePaymentInstallment::where('purchase_order_id', $order->id)
                    ->lockForUpdate()
                    ->findOrFail($validated['installment_id']);
                if ($installment->cancelled_at) {
                    throw ValidationException::withMessages(['installment_id' => 'A cancelled installment cannot receive a payment.']);
                }
                $installmentPaid = Money::toMinor(PurchasePayment::where('installment_id', $installment->id)
                    ->where('status', 'paid')
                    ->sum('amount'));
                $installmentRemaining = max(0, Money::toMinor($installment->planned_amount) - $installmentPaid);
                if ($amount > $installmentRemaining) {
                    throw ValidationException::withMessages([
                        'amount' => 'Payment amount cannot exceed this installment\'s remaining amount of ₹' . number_format(Money::fromMinor($installmentRemaining), 2) . '.',
                    ]);
                }
            }
            if ($amount > $remaining) {
                throw ValidationException::withMessages([
                    $validated['payment_input_mode'] === 'percentage' ? 'payment_percentage' : 'amount'
                        => 'Payment amount cannot be greater than the eligible outstanding amount of ₹' . number_format(Money::fromMinor($remaining), 2) . '.',
                ]);
            }

            PurchasePayment::create([
                'purchase_order_id' => $order->id,
                'installment_id' => $installment ? $installment->id : null,
                'payment_date' => $validated['payment_date'],
                'amount' => Money::fromMinor($amount),
                'payment_method' => $validated['payment_method'] ?? 'Other',
                'reference_number' => $validated['reference_number'] ?? null,
                'remarks' => $validated['remarks'] ?? null,
                'status' => 'paid',
                'submission_token' => $validated['submission_token'],
                'entered_by' => Auth::id(),
            ]);
            $paymentAllocations->allocateForOrder($order, true);
            ProcurementAuditLog::recordIfAvailable($order, 'po_payment_recorded', $order->status, $order->status, null, [
                'amount' => Money::fromMinor($amount),
                'remaining_amount' => Money::fromMinor($remaining - $amount),
            ]);

            return $order;
        });

        ActivityLogger::log('Purchase Payment Recorded', 'Purchase', 'Payment recorded against purchase order ' . $purchaseOrder->po_number . '.');

        return redirect()->route('quotations.purchase-details', $quotation->id)
            ->with('success', 'Payment saved successfully.');
    }

    public function approval()
    {
        $quotations = QuotationRequest::with([
                'creator',
                'quotations.supplier',
                'quotations.items',
            ])
                ->whereIn('status', ['submitted', 'under_review', 'approved'])
            ->orderByDesc('id')
            ->get();
        $quotations->each(function ($requestRecord) {
                $visibleQuotations = $requestRecord->status === 'approved'
                    ? $requestRecord->quotations->where('status', 'approved')
                    : $requestRecord->quotations->whereIn('status', ['submitted', 'under_review']);
                $requestRecord->setAttribute('approval_quotations', $visibleQuotations);
                $requestRecord->setAttribute('quotations_count', $visibleQuotations->count());
                $requestRecord->setAttribute('product_count', $visibleQuotations
                    ->flatMap(function ($quotation) {
                        return $quotation->items->pluck('product_id');
                    })
                ->unique()
                ->count());
        });

        return view('quotations.approval', compact('quotations'));
    }

    public function rejected($id)
    {
        $quotationRequest = QuotationRequest::with([
                'creator',
                'quotations.supplier',
                'quotations.items.product.category',
                'quotations.items.product.unit',
            ])
            ->findOrFail($id);

        abort_unless($quotationRequest->status === 'approved', 404);

        return view('quotations.rejected', compact('quotationRequest'));
    }

    public function submit(Request $request, $id)
    {
        $quotation = Quotation::with('items')->findOrFail($id);
        abort_unless(in_array($quotation->status, ['draft', 'under_review'], true), 422, 'This quotation cannot be submitted.');

        $validated = $request->validate([
            'items' => 'required|array',
            'items.*.supplier_rate' => 'required|numeric|min:0',
            'items.*.discount_percent' => 'nullable|numeric|min:0|max:100',
            'items.*.gst_rate' => 'required|numeric|min:0|max:100',
            'bill_copy' => 'nullable|file|mimes:pdf,jpg,jpeg,png,webp|max:10240',
        ]);

        $billCopy = $this->storeBillCopy($request, 'bill_copy');
        try {
            DB::transaction(function () use ($quotation, $validated, $billCopy) {
                $this->submitQuotation($quotation, $validated['items']);
                if ($billCopy) {
                    $quotation->update($billCopy);
                }
                $quotation->request()->update(['status' => 'submitted']);
            });
        } catch (Throwable $exception) {
            $this->deleteBillCopies([$billCopy]);
            throw $exception;
        }

        ActivityLogger::log('Quotation Submitted', 'Quotation', 'Quotation ' . $quotation->quotation_code . ' was submitted.');

        return redirect()->route('quotations.show', $quotation->quotation_request_id)
            ->with('success', 'Supplier quotation submitted for authority review.');
    }

    public function submitRequest(Request $request, $id)
    {
        $requestRecord = QuotationRequest::with('quotations.items')->findOrFail($id);
        $draftQuotations = $requestRecord->quotations->filter(function ($quotation) {
            return $quotation->status === 'draft';
        });

        abort_if($draftQuotations->isEmpty(), 422, 'There are no draft quotations to submit.');

        $validated = $request->validate([
            'items' => 'required|array',
            'items.*' => 'required|array',
            'items.*.*.supplier_rate' => 'required|numeric|min:0',
            'items.*.*.discount_percent' => 'nullable|numeric|min:0|max:100',
            'items.*.*.gst_rate' => 'required|numeric|min:0|max:100',
            'bill_copies' => 'nullable|array',
            'bill_copies.*' => 'nullable|file|mimes:pdf,jpg,jpeg,png,webp|max:10240',
        ]);

        $draftQuotationIds = $draftQuotations->pluck('id')->map(function ($id) {
            return (string) $id;
        })->all();
        $uploadedCopies = [];
        try {
            foreach ($request->file('bill_copies', []) as $quotationId => $file) {
                if (!in_array((string) $quotationId, $draftQuotationIds, true)) {
                    throw ValidationException::withMessages([
                        'bill_copies' => 'A bill copy can only be attached to a draft supplier quotation in this request.',
                    ]);
                }
            }

            foreach ($draftQuotations as $quotation) {
                if ($request->hasFile('bill_copies.' . $quotation->id)) {
                    $uploadedCopies[$quotation->id] = $this->storeBillCopy($request, 'bill_copies.' . $quotation->id);
                }
            }

            DB::transaction(function () use ($requestRecord, $draftQuotations, $validated, $uploadedCopies) {
                foreach ($draftQuotations as $quotation) {
                    if (!isset($validated['items'][$quotation->id])) {
                        throw ValidationException::withMessages(['items' => 'Every supplier quotation must be completed.']);
                    }
                    $this->submitQuotation($quotation, $validated['items'][$quotation->id]);
                    if (isset($uploadedCopies[$quotation->id])) {
                        $quotation->update($uploadedCopies[$quotation->id]);
                    }
                }
                $requestRecord->update(['status' => 'submitted']);
            });
        } catch (Throwable $exception) {
            $this->deleteBillCopies($uploadedCopies);
            throw $exception;
        }

        ActivityLogger::log('Quotations Submitted', 'Quotation', 'All supplier quotations for request ' . $requestRecord->quotation_request_code . ' were submitted.');

        return redirect()->route('quotations.show', $requestRecord->id)
            ->with('success', 'All supplier quotations were submitted for authority review.');
    }

    public function billCopy($id, Request $request)
    {
        $user = Auth::user();
        abort_unless(
            $user && (
                $user->hasPermission('quotations', 'view')
                || $user->hasPermission('quotations', 'edit')
                || $user->hasPermission('quotations', 'approve')
            ),
            403
        );

        $quotation = Quotation::findOrFail($id);
        $path = $quotation->bill_copy_path;
        abort_unless(
            $path && strpos($path, 'quotation-bill-copies/') === 0 && Storage::disk('local')->exists($path),
            404
        );

        if ($request->boolean('download')) {
            return Storage::disk('local')->download($path, $quotation->bill_copy_name ?: basename($path));
        }

        return response()->file(Storage::disk('local')->path($path));
    }

    public function approve($id)
    {
        $quotation = $this->approveSelectedQuotation($id);

        ActivityLogger::log('Quotation Approved', 'Quotation', 'Quotation ' . $quotation->quotation_code . ' was approved.');

        return redirect()->route('quotations.show', $quotation->quotation_request_id)
            ->with('success', 'Quotation approved. Other quotations for this request were rejected.')
            ->with('approval_quotation_id', $quotation->id);
    }

    private function approveSelectedQuotation($id)
    {
        return DB::transaction(function () use ($id) {
            $quotation = Quotation::lockForUpdate()->findOrFail($id);
            $requestRecord = QuotationRequest::lockForUpdate()->findOrFail($quotation->quotation_request_id);
            if ($quotation->status === 'approved' && $requestRecord->status === 'approved') {
                return $quotation;
            }
            if ($quotation->purchase_order_id) {
                throw ValidationException::withMessages(['quotation' => 'This quotation already has a purchase order.']);
            }
            if (!in_array($requestRecord->status, ['submitted', 'under_review'], true)
                || !in_array($quotation->status, ['submitted', 'under_review'], true)) {
                throw ValidationException::withMessages(['quotation' => 'Only submitted quotations can be approved.']);
            }
            if (Quotation::where('quotation_request_id', $requestRecord->id)->where('status', 'approved')->exists()) {
                throw ValidationException::withMessages(['quotation' => 'Another quotation is already approved for this request.']);
            }

            $previousStatus = $quotation->status;
            $quotation->update(['status' => 'approved', 'approved_at' => now(), 'approved_by' => Auth::id()]);
            Quotation::where('quotation_request_id', $requestRecord->id)
                ->where('id', '<>', $quotation->id)
                ->whereNotIn('status', ['approved'])
                ->update(['status' => 'rejected', 'rejected_at' => now(), 'rejected_by' => Auth::id(), 'rejection_reason' => 'Another supplier quotation was approved.']);
            $requestRecord->update(['status' => 'approved']);
            ProcurementAuditLog::recordIfAvailable($quotation, 'quotation_approved', $previousStatus, 'approved', null, [
                'quotation_request_id' => $requestRecord->id,
            ]);

            return $quotation;
        });
    }

    public function reject(Request $request, $id)
    {
        $validated = $request->validate(['rejection_reason' => 'required|string|max:2000']);
        $quotation = DB::transaction(function () use ($id, $validated) {
            $quotation = Quotation::lockForUpdate()->findOrFail($id);
            $requestRecord = QuotationRequest::lockForUpdate()->findOrFail($quotation->quotation_request_id);
            abort_if($requestRecord->status === 'approved', 422, 'An approved quotation request cannot be rejected.');
            abort_if($quotation->purchase_order_id, 422, 'A quotation linked to a purchase cannot be rejected.');
            Quotation::where('quotation_request_id', $requestRecord->id)->update([
                'status' => 'rejected',
                'rejected_at' => now(),
                'rejected_by' => Auth::id(),
                'rejection_reason' => $validated['rejection_reason'],
            ]);
            $requestRecord->update(['status' => 'rejected']);
            ProcurementAuditLog::recordIfAvailable($quotation, 'quotation_request_rejected', null, 'rejected', $validated['rejection_reason'], [
                'quotation_request_id' => $requestRecord->id,
            ]);

            return $quotation;
        });
        ActivityLogger::log('Quotation Rejected', 'Quotation', 'Quotation ' . $quotation->quotation_code . ' was rejected.');

        return back()->with('success', 'Quotation rejected.');
    }

    public function generatePurchase($id)
    {
        $quotation = Quotation::with('request')->findOrFail($id);
        if (in_array($quotation->status, ['submitted', 'under_review'], true)) {
            $quotation = $this->approveSelectedQuotation($id);
        }
        if ($quotation->status !== 'approved') {
            throw ValidationException::withMessages(['quotation' => 'Only an approved quotation can continue to purchase details.']);
        }

        return redirect()->route('quotations.purchase-details', $quotation->id)
            ->with('success', 'Review the negotiated purchase details and generate the purchase order when ready.');
    }

    private function createPurchaseOrderFromNegotiatedQuotation(Quotation $quotation, array $items)
    {
        if ($quotation->status !== 'approved' || $quotation->purchase_order_id) {
            throw ValidationException::withMessages(['quotation' => 'Only an approved quotation without a purchase order can generate a purchase order.']);
        }

        $quotationItems = $quotation->items->keyBy('id');
        if (count($items) !== $quotationItems->count() || $quotationItems->keys()->diff(array_keys($items))->isNotEmpty()) {
            throw ValidationException::withMessages(['items' => 'Submit negotiated rates for every item in the approved quotation.']);
        }

        $subtotal = $discountTotal = $cgstTotal = $sgstTotal = 0;
        $lines = [];
        foreach ($quotationItems as $item) {
            $line = $items[$item->id];
            $rate = round((float) $line['purchase_rate'], 2);
            $discountPercent = round((float) ($line['discount_percent'] ?? 0), 2);
            $basic = round((float) $item->quantity * $rate, 2);
            $discount = round($basic * $discountPercent / 100, 2);
            $taxable = round($basic - $discount, 2);
            $gstRate = round((float) $item->gst_rate, 2);
            $cgstRate = round($gstRate / 2, 2);
            $sgstRate = round($gstRate - $cgstRate, 2);
            $cgst = round($taxable * $cgstRate / 100, 2);
            $sgst = round($taxable * $sgstRate / 100, 2);
            $subtotal += $basic;
            $discountTotal += $discount;
            $cgstTotal += $cgst;
            $sgstTotal += $sgst;
            $lines[] = compact('item', 'rate', 'discountPercent', 'basic', 'discount', 'gstRate', 'cgstRate', 'cgst', 'sgstRate', 'sgst');
        }
        $grandTotal = round($subtotal - $discountTotal + $cgstTotal + $sgstTotal, 2);
        if ($grandTotal <= 0) {
            throw ValidationException::withMessages(['amount' => 'A purchase order must have a payable amount greater than zero.']);
        }

        $order = PurchaseOrder::create([
            'po_number' => 'TEMP-' . Str::uuid(),
            'supplier_id' => $quotation->supplier_id,
            'store_id' => $quotation->request->store_id,
            'po_date' => now()->toDateString(),
            'expected_date' => $quotation->request->required_date,
            'status' => 'draft',
            'subtotal' => round($subtotal, 2),
            'discount_amount' => round($discountTotal, 2),
            'tax_amount' => round($cgstTotal + $sgstTotal, 2),
            'cgst_total' => round($cgstTotal, 2),
            'sgst_total' => round($sgstTotal, 2),
            'grand_total' => $grandTotal,
            'notes' => 'Generated from quotation ' . $quotation->quotation_code,
            'created_by' => Auth::id(),
            'quotation_id' => $quotation->id,
            'quotation_request_id' => $quotation->quotation_request_id,
        ]);
        $order->po_number = 'PO-' . now()->format('Ymd') . '-' . str_pad($order->id, 4, '0', STR_PAD_LEFT);
        $order->save();

        foreach ($lines as $line) {
            $item = $line['item'];
            $order->items()->create([
                'product_id' => $item->product_id,
                'unit_id' => $item->unit_id,
                'quantity' => $item->quantity,
                'received_quantity' => 0,
                'purchase_rate' => $line['rate'],
                'discount_percent' => $line['discountPercent'],
                'tax_percent' => $line['gstRate'],
                'total_amount' => round($line['basic'] - $line['discount'] + $line['cgst'] + $line['sgst'], 2),
                'cgst_rate' => $line['cgstRate'],
                'cgst_amount' => $line['cgst'],
                'sgst_rate' => $line['sgstRate'],
                'sgst_amount' => $line['sgst'],
            ]);
        }

        $quotation->update(['purchase_order_id' => $order->id]);
        ProcurementAuditLog::recordIfAvailable($order, 'po_created', null, $order->status, null, [
            'quotation_id' => $quotation->id,
            'quotation_code' => $quotation->quotation_code,
        ]);

        return $order;
    }

    private function loadRequest($id)
    {
        return QuotationRequest::with([
            'creator',
            'store',
            'quotations.supplier',
            'quotations.items.product.category',
            'quotations.items.product.unit',
            'quotations.purchaseOrder',
        ])->findOrFail($id);
    }

    private function submitQuotation(Quotation $quotation, array $items)
    {
        $subtotal = $discountTotal = $cgstTotal = $sgstTotal = 0;
        foreach ($quotation->items as $item) {
            if (!isset($items[$item->id])) {
                throw ValidationException::withMessages(['items' => 'Every quotation item must be completed.']);
            }
            $line = $items[$item->id];
            $rate = round((float) $line['supplier_rate'], 2);
            $quantity = round((float) $item->quantity, 2);
            $basic = round($quantity * $rate, 2);
            $discountPercent = round((float) ($line['discount_percent'] ?? 0), 2);
            $discount = round($basic * $discountPercent / 100, 2);
            $taxable = round($basic - $discount, 2);
            $gstRate = round((float) $line['gst_rate'], 2);
            $cgstRate = round($gstRate / 2, 2);
            $sgstRate = round($gstRate - $cgstRate, 2);
            $cgst = round($taxable * $cgstRate / 100, 2);
            $sgst = round($taxable * $sgstRate / 100, 2);
            $item->update([
                'supplier_rate' => $rate,
                'basic_amount' => $basic,
                'discount_percent' => $discountPercent,
                'gst_rate' => $gstRate,
                'cgst_rate' => $cgstRate,
                'cgst_amount' => $cgst,
                'sgst_rate' => $sgstRate,
                'sgst_amount' => $sgst,
                'tax_amount' => round($cgst + $sgst, 2),
                'total_amount' => round($taxable + $cgst + $sgst, 2),
            ]);
            $subtotal += $basic;
            $discountTotal += $discount;
            $cgstTotal += $cgst;
            $sgstTotal += $sgst;
        }
        $quotation->update([
            'status' => 'submitted',
            'subtotal' => round($subtotal, 2),
            'discount_total' => round($discountTotal, 2),
            'cgst_total' => round($cgstTotal, 2),
            'sgst_total' => round($sgstTotal, 2),
            'tax_total' => round($cgstTotal + $sgstTotal, 2),
            'grand_total' => round($subtotal - $discountTotal + $cgstTotal + $sgstTotal, 2),
            'submitted_at' => now(),
            'submitted_by' => Auth::id(),
        ]);
    }

    private function storeBillCopy(Request $request, $field)
    {
        if (!$request->hasFile($field)) {
            return null;
        }

        $file = $request->file($field);
        $path = $file->store('quotation-bill-copies', 'local');
        if (!$path) {
            throw new RuntimeException('The supplier quotation bill copy could not be stored.');
        }

        return [
            'bill_copy_path' => $path,
            'bill_copy_name' => Str::limit(preg_replace('/[^A-Za-z0-9._ -]/', '_', $file->getClientOriginalName()), 180, ''),
        ];
    }

    private function deleteBillCopies(array $copies)
    {
        foreach ($copies as $copy) {
            if ($copy && isset($copy['bill_copy_path'])
                && Storage::disk('local')->exists($copy['bill_copy_path'])
                && !Storage::disk('local')->delete($copy['bill_copy_path'])) {
                throw new RuntimeException('A supplier quotation bill copy could not be removed from storage.');
            }
        }
    }
}
