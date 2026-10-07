<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class StockAdjustment extends Model
{
    protected $fillable = [
        'product_id',
        'reference_number',
        'adjustment_date',
        'quantity_before',
        'adjustment_quantity',
        'quantity_after',
        'reason',
        'is_active',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
