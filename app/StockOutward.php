<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class StockOutward extends Model
{
    protected $fillable = [
        'product_id',
        'reference_number',
        'outward_date',
        'quantity',
        'selling_price',
        'total_amount',
        'issued_to',
        'remarks',
        'is_active',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}