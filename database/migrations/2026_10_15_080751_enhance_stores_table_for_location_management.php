<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class EnhanceStoresTableForLocationManagement extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Check for duplicate location names (case-insensitive, trimmed)
        $duplicates = DB::select("
            SELECT LOWER(TRIM(name)) as normalized_name, COUNT(*) as count
            FROM stores
            GROUP BY LOWER(TRIM(name))
            HAVING COUNT(*) > 1
        ");

        if (count($duplicates) > 0) {
            $duplicateNames = implode(', ', array_column($duplicates, 'normalized_name'));
            throw new \RuntimeException(
                'Cannot add unique constraint to stores.name because duplicate location names exist: ' 
                . $duplicateNames 
                . '. Please ensure all location names are unique before running this migration.'
            );
        }

        // Add description field for additional location details (optional)
        if (!Schema::hasColumn('stores', 'description')) {
            Schema::table('stores', function (Blueprint $table) {
                $table->text('description')->nullable()->after('location');
            });
        }

        // Note: We cannot add a true case-insensitive unique constraint in MySQL without 
        // using a generated column or changing collation. Instead, we'll enforce uniqueness
        // at the application level in the Store model and controller validation.
        // The existing 'code' field already has a unique constraint.

        // Ensure all stores have valid codes
        $storesWithoutCode = DB::table('stores')->whereNull('code')->orWhere('code', '')->get();
        foreach ($storesWithoutCode as $store) {
            $code = 'LOC-' . str_pad($store->id, 6, '0', STR_PAD_LEFT);
            $suffix = 1;
            
            while (DB::table('stores')->where('code', $code)->exists()) {
                $code = 'LOC-' . str_pad($store->id, 6, '0', STR_PAD_LEFT) . '-' . $suffix;
                $suffix++;
            }
            
            DB::table('stores')->where('id', $store->id)->update(['code' => $code]);
        }

        // Ensure is_active has a default value
        DB::statement('UPDATE stores SET is_active = 1 WHERE is_active IS NULL');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasColumn('stores', 'description')) {
            Schema::table('stores', function (Blueprint $table) {
                $table->dropColumn('description');
            });
        }
    }
}
