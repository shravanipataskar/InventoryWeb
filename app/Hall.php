<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Hall extends Model
{
    protected $fillable = [
        'name',
        'is_active',
    ];

    public function racks()
    {
        return $this->hasMany(Rack::class);
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }
}
