<?php

namespace App\Services;

use App\PurchaseOrder;
use App\PurchasePayment;
use App\PurchasePaymentBillAllocation;
use App\SupplierBill;
use App\SupplierPayment;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchasePaymentAllocationService
{
    private $supplierPayments;

    public function __construct(SupplierPaymentService $supplierPayments)
    {
        $this->supplierPayments = $supplierPayments;
    }

    /**
     * Apply available PO advances to approved bills in order, leaving in-flight
     * supplier-payment requests reserved against each bill.
     */
    public function allocateForOrder(PurchaseOrder $order, $rejectUnallocatedWithPendingBills = false)
    {
        $payments = PurchasePayment::where('purchase_order_id', $order->id)
            ->where('status', 'paid')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
        $bills = SupplierBill::where('purchase_order_id', $order->id)
            ->whereIn('status', SupplierBill::PAYABLE_STATUSES)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($bills as $bill) {
            $availableOnBill = max(0, $bill->outstandingMinor() - $this->supplierPayments->reservedMinor($bill->id));
            if ($availableOnBill === 0) {
                continue;
            }

            foreach ($payments as $payment) {
                if ($availableOnBill === 0) {
                    break;
                }

                $allocated = Money::toMinor(PurchasePaymentBillAllocation::where('purchase_payment_id', $payment->id)->sum('amount'));
                $availableOnPayment = Money::toMinor($payment->amount) - $allocated;
                if ($availableOnPayment <= 0) {
                    continue;
                }

                $amount = min($availableOnBill, $availableOnPayment);
                $allocation = PurchasePaymentBillAllocation::where('purchase_payment_id', $payment->id)
                    ->where('supplier_bill_id', $bill->id)
                    ->lockForUpdate()
                    ->first();
                if ($allocation) {
                    $allocation->amount = Money::fromMinor(Money::toMinor($allocation->amount) + $amount);
                    $allocation->save();
                } else {
                    PurchasePaymentBillAllocation::create([
                        'purchase_payment_id' => $payment->id,
                        'supplier_bill_id' => $bill->id,
                        'amount' => Money::fromMinor($amount),
                    ]);
                }
                $availableOnBill -= $amount;

                $this->supplierPayments->refreshBillBalance($bill, 'purchase_payment_applied', null, [
                    'purchase_payment_id' => $payment->id,
                    'amount' => Money::fromMinor($amount),
                ]);
            }
        }

        if ($rejectUnallocatedWithPendingBills) {
            $unallocated = 0;
            foreach ($payments as $payment) {
                $allocated = Money::toMinor(PurchasePaymentBillAllocation::where('purchase_payment_id', $payment->id)->sum('amount'));
                $unallocated += max(0, Money::toMinor($payment->amount) - $allocated);
            }

            $hasPendingBills = DB::table('payment_allocations')
                ->join('supplier_bills', 'supplier_bills.id', '=', 'payment_allocations.supplier_bill_id')
                ->join('supplier_payments', 'supplier_payments.id', '=', 'payment_allocations.supplier_payment_id')
                ->where('supplier_bills.purchase_order_id', $order->id)
                ->whereIn('supplier_payments.status', SupplierPayment::IN_FLIGHT_STATUSES)
                ->exists();

            if ($unallocated > 0 && $hasPendingBills) {
                throw ValidationException::withMessages([
                    'amount' => 'Cancel or complete pending supplier bill payments before adding a purchase-order payment.',
                ]);
            }
        }
    }

    public function paidMinorForOrder($orderId)
    {
        $purchasePayments = PurchasePayment::where('purchase_order_id', $orderId)
            ->where('status', 'paid')
            ->sum('amount');
        $supplierPayments = DB::table('payment_allocations')
            ->join('supplier_bills', 'supplier_bills.id', '=', 'payment_allocations.supplier_bill_id')
            ->join('supplier_payments', 'supplier_payments.id', '=', 'payment_allocations.supplier_payment_id')
            ->where('supplier_bills.purchase_order_id', $orderId)
            ->where('supplier_payments.status', SupplierPayment::STATUS_COMPLETED)
            ->sum('payment_allocations.amount');

        return Money::toMinor($purchasePayments) + Money::toMinor($supplierPayments);
    }

    public function committedMinorForOrder($orderId)
    {
        $purchasePayments = PurchasePayment::where('purchase_order_id', $orderId)
            ->where('status', 'paid')
            ->sum('amount');
        $supplierPayments = DB::table('payment_allocations')
            ->join('supplier_bills', 'supplier_bills.id', '=', 'payment_allocations.supplier_bill_id')
            ->join('supplier_payments', 'supplier_payments.id', '=', 'payment_allocations.supplier_payment_id')
            ->where('supplier_bills.purchase_order_id', $orderId)
            ->whereIn('supplier_payments.status', array_merge(
                SupplierPayment::IN_FLIGHT_STATUSES,
                [SupplierPayment::STATUS_COMPLETED]
            ))
            ->sum('payment_allocations.amount');

        return Money::toMinor($purchasePayments) + Money::toMinor($supplierPayments);
    }

    public function completedSupplierPaymentsMinorForBill($billId)
    {
        return Money::toMinor(DB::table('payment_allocations')
            ->join('supplier_payments', 'supplier_payments.id', '=', 'payment_allocations.supplier_payment_id')
            ->where('payment_allocations.supplier_bill_id', $billId)
            ->where('supplier_payments.status', SupplierPayment::STATUS_COMPLETED)
            ->sum('payment_allocations.amount'));
    }
}
