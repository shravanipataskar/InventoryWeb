<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    protected $fillable = [
        'supplier_code',
        'name',
        'company_name',
        'email',
        'phone',
        'address',
        'city',
        'state',
        'pincode',
        'gst_number',
        'pan_number',
        'payment_terms',
        'bank_name',
        'account_holder_name',
        'account_number',
        'ifsc_code',
        'branch_name',
        'notes',
        'opening_balance',
        'is_active',
    ];

    public function stockInwards()
    {
        return $this->hasMany(StockInward::class);
    }
}