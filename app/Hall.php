<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Hall extends Model
{
    protected $fillable = [
        'store_id',
        'name',
        'is_active',
    ];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function racks()
    {
        return $this->hasMany(Rack::class);
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }
}
