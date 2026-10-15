<?php

namespace App\Http\Controllers;

use App\PurchaseOrder;
use App\Supplier;
use App\SupplierBill;
use App\SupplierPayment;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcurementController extends Controller
{
    public function approvals(Request $request)
    {
        try {
            $user = $request->user();
            $canBills = $user->hasPermission('bills', 'approve');
            $canPayments = $user->hasPermission('payments', 'approve');
            abort_unless($canBills || $canPayments, 403);

            $bills = $canBills
                ? SupplierBill::with(['supplier', 'purchaseOrder', 'creator'])
                    ->whereIn('status', SupplierBill::EDITABLE_STATUSES)
                    ->orderByRaw("CASE status WHEN 'under_verification' THEN 0 WHEN 'on_hold' THEN 1 ELSE 2 END")
                    ->orderBy('id')
                    ->get()
                : collect();

            $payments = $canPayments
                ? SupplierPayment::with(['supplier', 'allocations.bill', 'requester'])
                    ->where('status', SupplierPayment::STATUS_PENDING)
                    ->orderBy('id')
                    ->get()
                : collect();

            return view('procurement.approvals', compact('bills', 'payments', 'canBills', 'canPayments'));
        } catch (Throwable $exception) {
            Log::error('Failed to load procurement approvals.', [
                'operation' => 'procurement.approvals',
                'exception' => get_class($exception),
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    public function supplierHistory($supplierId)
    {
        try {
            $supplier = Supplier::findOrFail($supplierId);
            $purchaseOrders = PurchaseOrder::where('supplier_id', $supplier->id)
                ->orderByDesc('id')
                ->limit(50)
                ->get();

            $bills = SupplierBill::where('supplier_id', $supplier->id)
                ->orderByDesc('id')
                ->get();

            $payments = SupplierPayment::with('allocations.bill')
                ->where('supplier_id', $supplier->id)
                ->orderByDesc('id')
                ->get();

            $activeBills = $bills->whereIn(
                'status',
                array_merge(SupplierBill::PAYABLE_STATUSES, [SupplierBill::STATUS_PAID])
            );

            $summary = [
                'ordered' => Money::fromMinor($purchaseOrders->where('status', '<>', 'cancelled')->sum(function ($order) {
                    return Money::toMinor($order->grand_total);
                })),
                'billed' => Money::fromMinor($activeBills->sum(function ($bill) {
                    return Money::toMinor($bill->total_amount);
                })),
                'paid' => Money::fromMinor($payments->where('status', SupplierPayment::STATUS_COMPLETED)->sum(function ($payment) {
                    return Money::toMinor($payment->amount);
                })),
                'outstanding' => Money::fromMinor($bills->whereIn('status', SupplierBill::PAYABLE_STATUSES)->sum(function ($bill) {
                    return $bill->outstandingMinor();
                })),
            ];

            return view('procurement.supplier_history', compact(
                'supplier',
                'purchaseOrders',
                'bills',
                'payments',
                'summary'
            ));
        } catch (Throwable $exception) {
            Log::error('Failed to load supplier procurement history.', [
                'operation' => 'procurement.supplier_history',
                'supplier_id' => $supplierId,
                'exception' => get_class($exception),
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}
