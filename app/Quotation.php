<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Quotation extends Model
{
    protected $fillable = [
        'quotation_request_id', 'supplier_id', 'quotation_code', 'quotation_date',
        'status', 'subtotal', 'cgst_total', 'sgst_total', 'tax_total',
        'grand_total', 'submitted_at', 'approved_at', 'rejected_at',
        'created_by', 'submitted_by', 'approved_by', 'rejected_by',
        'rejection_reason', 'purchase_order_id',
    ];

    protected $dates = ['submitted_at', 'approved_at', 'rejected_at'];

    public function request()
    {
        return $this->belongsTo(QuotationRequest::class, 'quotation_request_id');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items()
    {
        return $this->hasMany(QuotationItem::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function submitter()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }
}
