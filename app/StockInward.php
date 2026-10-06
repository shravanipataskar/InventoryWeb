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
        'remarks',
        'is_active',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }
}