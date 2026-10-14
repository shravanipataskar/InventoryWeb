<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PurchasePayment extends Model
{
    const METHODS = [
        'Bank Transfer',
        'UPI',
        'Cheque',
        'Cash',
        'NEFT',
        'RTGS',
        'Other',
    ];

    protected $fillable = [
        'purchase_order_id',
        'installment_id',
        'payment_date',
        'amount',
        'payment_method',
        'reference_number',
        'remarks',
        'status',
        'submission_token',
        'entered_by',
    ];

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function installment()
    {
        return $this->belongsTo(PurchasePaymentInstallment::class, 'installment_id');
    }

    public function enteredBy()
    {
        return $this->belongsTo(User::class, 'entered_by');
    }

    public function billAllocations()
    {
        return $this->hasMany(PurchasePaymentBillAllocation::class);
    }
}
