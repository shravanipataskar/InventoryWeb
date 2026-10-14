<?php

namespace App\Services;

use App\PaymentAllocation;
use App\ProcurementAuditLog;
use App\PurchasePaymentBillAllocation;
use App\SupplierBill;
use App\SupplierPayment;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

class SupplierPaymentService
{
    /**
     * Amount (minor units) of a bill reserved by payment requests not yet completed or closed.
     */
    public function reservedMinor($billId, $excludePaymentId = null)
    {
        $sum = PaymentAllocation::where('supplier_bill_id', $billId)
            ->whereHas('payment', function ($query) {
                $query->whereIn('status', SupplierPayment::IN_FLIGHT_STATUSES);
            })
            ->when($excludePaymentId, function ($query) use ($excludePaymentId) {
                $query->where('supplier_payment_id', '<>', $excludePaymentId);
            })
            ->sum('amount');

        return Money::toMinor($sum);
    }

    public function availableMinor(SupplierBill $bill)
    {
        return max(0, $bill->outstandingMinor() - $this->reservedMinor($bill->id));
    }

    /**
     * Recalculate a bill's paid amount from completed payment allocations and set its payment status.
     * Callers must hold a row lock on the bill inside a transaction.
     */
    public function refreshBillBalance(SupplierBill $bill, $event, $reason = null, array $meta = [])
    {
        $paid = Money::toMinor(PaymentAllocation::where('supplier_bill_id', $bill->id)
            ->whereHas('payment', function ($query) {
                $query->where('status', SupplierPayment::STATUS_COMPLETED);
            })
            ->sum('amount'));
        $paid += Money::toMinor(PurchasePaymentBillAllocation::where('supplier_bill_id', $bill->id)->sum('amount'));
        $total = Money::toMinor($bill->total_amount);

        if ($paid > $total) {
            throw new \RuntimeException('Completed payments exceed the total of bill ' . $bill->bill_number . '.');
        }

        $previous = $bill->status;
        $bill->paid_amount = Money::fromMinor($paid);
        if ($paid === 0) {
            $bill->status = SupplierBill::STATUS_APPROVED;
        } elseif ($paid < $total) {
            $bill->status = SupplierBill::STATUS_PARTIALLY_PAID;
        } else {
            $bill->status = SupplierBill::STATUS_PAID;
        }
        $bill->save();

        ProcurementAuditLog::record($bill, $event, $previous, $bill->status, $reason, $meta + [
            'paid_amount' => $bill->paid_amount,
            'outstanding' => $bill->outstanding_amount,
        ]);
    }

    /**
     * Lock the bills allocated to a payment in a stable order to avoid deadlocks.
     */
    public function lockBills(SupplierPayment $payment)
    {
        $billIds = $payment->allocations()->pluck('supplier_bill_id')->sort()->values()->all();

        return SupplierBill::whereIn('id', $billIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
    }
}
