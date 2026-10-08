<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class GoodsReceiptItem extends Model
{
    protected $fillable = [
        'goods_receipt_id',
        'purchase_order_item_id',
        'product_id',
        'ordered_quantity',
        'received_quantity',
        'rejected_quantity',
        'accepted_quantity',
        'purchase_rate',
    ];

    public function goodsReceipt()
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    public function purchaseOrderItem()
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function stockInward()
    {
        return $this->hasOne(StockInward::class, 'goods_receipt_item_id');
    }
}
