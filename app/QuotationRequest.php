<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class QuotationRequest extends Model
{
    protected $fillable = [
        'quotation_request_code', 'request_date', 'required_date', 'store_id',
        'created_by', 'status', 'remarks',
    ];

    public function quotations()
    {
        return $this->hasMany(Quotation::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }
}
