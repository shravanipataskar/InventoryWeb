<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class GoodsReceipt extends Model
{
    protected $fillable = [
        'grn_number',
        'submission_token',
        'purchase_order_id',
        'supplier_id',
        'store_id',
        'received_date',
        'invoice_number',
        'remarks',
        'status',
        'received_by',
        'created_by',
    ];

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
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
        return $this->hasMany(GoodsReceiptItem::class);
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
