<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Shelf extends Model
{
    protected $fillable = [
        'rack_id',
        'name',
        'is_active',
    ];

    public function rack()
    {
        return $this->belongsTo(Rack::class);
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }
}
