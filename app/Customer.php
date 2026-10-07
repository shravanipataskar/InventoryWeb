<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $fillable = [
        'customer_code',
        'name',
        'customer_type',
        'contact_person',
        'phone',
        'email',
        'address',
        'city',
        'state',
        'pincode',
        'gstin',
        'pan',
        'is_active',
    ];

    public function stockOutwards()
    {
        return $this->hasMany(StockOutward::class);
    }
}
