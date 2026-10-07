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
        'company_id',
        'unit_id',
        'barcode',
        'image',
        'purchase_price',
        'selling_price',
        'minimum_stock',
        'reorder_level',
        'reorder_quantity',
        'description',
        'image',
        'is_active'
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function stockInwards()
    {
        return $this->hasMany(StockInward::class);
    }

    public function stockOutwards()
    {
        return $this->hasMany(StockOutward::class);
    }
}