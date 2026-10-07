<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class StockTransfer extends Model
{
    protected $fillable = [
        'product_id',
        'transfer_number',
        'transfer_date',
        'from_location',
        'to_location',
        'quantity',
        'remarks',
        'is_active',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
