<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class QuotationItem extends Model
{
    protected $fillable = [
        'quotation_id', 'product_id', 'category_id', 'unit_id', 'quantity',
        'supplier_rate', 'basic_amount', 'gst_rate', 'cgst_rate', 'cgst_amount',
        'sgst_rate', 'sgst_amount', 'tax_amount', 'total_amount',
    ];

    public function quotation()
    {
        return $this->belongsTo(Quotation::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }
}
