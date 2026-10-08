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
        'notes',
        'created_by',
    ];

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
}
