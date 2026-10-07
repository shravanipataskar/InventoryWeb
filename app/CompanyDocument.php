<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CompanyDocument extends Model
{
    protected $fillable = [
        'company_id',
        'file_name',
        'storage_path',
        'file_size',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
