<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'product_code',
        'name',
        'hall_id',
        'rack_id',
        'shelf_id',
        'category_id',
        'unit_id',
        'barcode',
        'purchase_price',
        'selling_price',
        'minimum_stock',
        'description',
        'image',
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

    public function hall()
    {
        return $this->belongsTo(Hall::class);
    }

    public function rack()
    {
        return $this->belongsTo(Rack::class);
    }

    public function shelf()
    {
        return $this->belongsTo(Shelf::class);
    }
}