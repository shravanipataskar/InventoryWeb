<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrder extends Model
{
    protected $fillable = [
        'po_number',
        'supplier_id',
        'store_id',
        'po_date',
        'expected_date',
        'status',
        'subtotal',
        'discount_amount',
        'tax_amount',
        'grand_total',
        'cgst_total',
        'sgst_total',
        'notes',
        'created_by',
        'released_by',
        'released_at',
        'quotation_id',
        'quotation_request_id',
        'cancelled_at',
        'closed_at',
        'status_reason',
    ];

    // Statuses in which goods may still be received (unchanged from the existing receiving workflow).
    const RECEIVABLE_STATUSES = ['released', 'ordered', 'pending', 'approved', 'partially_received'];

    protected $dates = ['released_at'];

    public function getOrderDateAttribute($value)
    {
        return $value ?: ($this->attributes['po_date'] ?? null);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function items()
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function goodsReceipts()
    {
        return $this->hasMany(GoodsReceipt::class);
    }

    public function quotation()
    {
        return $this->belongsTo(Quotation::class);
    }

    public function supplierBills()
    {
        return $this->hasMany(SupplierBill::class);
    }

    public function payments()
    {
        return $this->hasMany(PurchasePayment::class);
    }

    public function installments()
    {
        return $this->hasMany(PurchasePaymentInstallment::class)->orderBy('installment_number');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function releaser()
    {
        return $this->belongsTo(User::class, 'released_by');
    }
}
