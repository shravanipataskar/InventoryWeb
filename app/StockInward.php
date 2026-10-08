<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class StockInward extends Model
{
    protected $fillable = [
        'product_id',
        'supplier_id',
        'invoice_number',
        'inward_date',
        'quantity',
        'purchase_price',
        'total_amount',
        'sgst_rate',
        'cgst_rate',
        'sgst_amount',
        'cgst_amount',
        'tax_total',
        'subtotal',
        'grand_total',
        'remarks',
        'is_active',
        'inward_number',
        'goods_receipt_item_id',
        'store_id',
        'received_quantity',
        'rejected_quantity',
        'status',
        'created_by',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function goodsReceiptItem()
    {
        return $this->belongsTo(GoodsReceiptItem::class);
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isGoodsReceiptGenerated()
    {
        return $this->goods_receipt_item_id !== null;
    }
}