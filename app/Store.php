<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Store extends Model
{
    protected $fillable = [
        'name',
        'code',
        'location',
        'description',
        'company_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Halls belonging to this inventory location
     */
    public function halls()
    {
        return $this->hasMany(Hall::class);
    }

    /**
     * Product storage configurations for this location
     */
    public function productStorageLocations()
    {
        return $this->hasMany(ProductStorageLocation::class);
    }

    /**
     * Company/brand associated with this location
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Scope query to only active locations
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get current stock balance for a product at this location
     */
    public function getStockBalance($productId)
    {
        if (!DB::getSchemaBuilder()->hasTable('stock_transactions')) {
            return 0;
        }

        return (float) DB::table('stock_transactions')
            ->where('product_id', $productId)
            ->where('store_id', $this->id)
            ->sum(DB::raw('quantity_in - quantity_out'));
    }

    /**
     * Get all stock balances for this location
     */
    public function getAllStockBalances()
    {
        if (!DB::getSchemaBuilder()->hasTable('stock_transactions')) {
            return [];
        }

        $balances = DB::table('stock_transactions')
            ->select('product_id')
            ->selectRaw('SUM(quantity_in - quantity_out) as quantity')
            ->where('store_id', $this->id)
            ->groupBy('product_id')
            ->having('quantity', '>', 0)
            ->get();

        $result = [];
        foreach ($balances as $balance) {
            $result[$balance->product_id] = (float) $balance->quantity;
        }

        return $result;
    }

    /**
     * Check if location can be deactivated (has no current stock)
     */
    public function canBeDeactivated()
    {
        if (!DB::getSchemaBuilder()->hasTable('stock_transactions')) {
            return true;
        }

        $hasStock = DB::table('stock_transactions')
            ->where('store_id', $this->id)
            ->havingRaw('SUM(quantity_in - quantity_out) > 0')
            ->groupBy('product_id')
            ->exists();

        return !$hasStock;
    }

    /**
     * Toggle active status
     */
    public function toggleStatus()
    {
        $this->is_active = !$this->is_active;
        return $this->save();
    }

    /**
     * Check if name is unique (case-insensitive, trimmed)
     */
    public static function isNameUnique($name, $excludeId = null)
    {
        $query = static::whereRaw('LOWER(TRIM(name)) = ?', [strtolower(trim($name))]);
        
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }
        
        return !$query->exists();
    }

    /**
     * Check if code is unique
     */
    public static function isCodeUnique($code, $excludeId = null)
    {
        $query = static::where('code', trim($code));
        
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }
        
        return !$query->exists();
    }
}
