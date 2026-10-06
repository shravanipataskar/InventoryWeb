<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'product_code',
        'name',
        'hall',
        'rack',
        'shell',
        'category_id',
        'unit_id',
        'barcode',
        'purchase_price',
        'selling_price',
        'minimum_stock',
        'description',
        'is_active'
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }
}