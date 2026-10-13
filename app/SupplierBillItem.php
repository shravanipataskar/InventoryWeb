<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class SupplierBillItem extends Model
{
    protected $fillable = [
        'supplier_bill_id', 'purchase_order_item_id', 'product_id', 'quantity', 'rate',
        'discount_percent', 'tax_percent', 'basic_amount', 'discount_amount',
        'tax_amount', 'total_amount',
    ];

    public function bill()
    {
        return $this->belongsTo(SupplierBill::class, 'supplier_bill_id');
    }

    public function purchaseOrderItem()
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
