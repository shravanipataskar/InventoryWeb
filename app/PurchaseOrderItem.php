<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrderItem extends Model
{
    protected $fillable = [
        'purchase_order_id',
        'product_id',
        'unit_id',
        'quantity',
        'received_quantity',
        'purchase_rate',
        'discount_percent',
        'tax_percent',
        'total_amount',
    ];

    public function getOrderedQuantityAttribute($value)
    {
        return $value !== null ? $value : ($this->attributes['quantity'] ?? 0);
    }

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
