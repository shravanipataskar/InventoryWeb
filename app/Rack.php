<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Rack extends Model
{
    protected $fillable = [
        'hall_id',
        'name',
        'is_active',
    ];

    public function hall()
    {
        return $this->belongsTo(Hall::class);
    }

    public function shelves()
    {
        return $this->hasMany(Shelf::class);
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }
}
