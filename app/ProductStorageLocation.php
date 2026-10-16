<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ProductStorageLocation extends Model
{
    protected $fillable = [
        'product_id',
        'store_id',
        'hall_id',
        'rack_id',
        'shelf_id',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
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
