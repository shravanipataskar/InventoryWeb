<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PurchasePaymentBillAllocation extends Model
{
    protected $fillable = ['purchase_payment_id', 'supplier_bill_id', 'amount'];

    public function purchasePayment()
    {
        return $this->belongsTo(PurchasePayment::class);
    }

    public function bill()
    {
        return $this->belongsTo(SupplierBill::class, 'supplier_bill_id');
    }
}
