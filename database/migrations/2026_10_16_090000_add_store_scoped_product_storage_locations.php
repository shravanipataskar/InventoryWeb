<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddStoreScopedProductStorageLocations extends Migration
{
    public function up()
    {
        Schema::table('halls', function (Blueprint $table) {
            $table->dropUnique('halls_name_unique');
            $table->unsignedBigInteger('store_id')->nullable()->after('id');
            $table->foreign('store_id')->references('id')->on('stores')->onDelete('restrict');
            $table->unique(['store_id', 'name']);
        });

        Schema::create('product_storage_locations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('store_id');
            $table->unsignedBigInteger('hall_id');
            $table->unsignedBigInteger('rack_id')->nullable();
            $table->unsignedBigInteger('shelf_id')->nullable();
            $table->timestamps();
            $table->unique(['product_id', 'store_id']);
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('store_id')->references('id')->on('stores')->onDelete('restrict');
            $table->foreign('hall_id')->references('id')->on('halls')->onDelete('restrict');
            $table->foreign('rack_id')->references('id')->on('racks')->onDelete('restrict');
            $table->foreign('shelf_id')->references('id')->on('shelves')->onDelete('restrict');
        });

        $stores = DB::table('stores')->orderBy('id')->get();
        $legacyHalls = DB::table('halls')->whereNull('store_id')->orderBy('id')->get();
        $hallMap = [];
        $rackMap = [];
        $shelfMap = [];

        foreach ($stores as $store) {
            foreach ($legacyHalls as $hall) {
                $hallMap[$store->id][$hall->id] = DB::table('halls')->insertGetId([
                    'store_id' => $store->id,
                    'name' => $hall->name,
                    'is_active' => $hall->is_active,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                foreach (DB::table('racks')->where('hall_id', $hall->id)->orderBy('id')->get() as $rack) {
                    $rackMap[$store->id][$rack->id] = DB::table('racks')->insertGetId([
                        'hall_id' => $hallMap[$store->id][$hall->id],
                        'name' => $rack->name,
                        'is_active' => $rack->is_active,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    foreach (DB::table('shelves')->where('rack_id', $rack->id)->orderBy('id')->get() as $shelf) {
                        $shelfMap[$store->id][$shelf->id] = DB::table('shelves')->insertGetId([
                            'rack_id' => $rackMap[$store->id][$rack->id],
                            'name' => $shelf->name,
                            'is_active' => $shelf->is_active,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            }
        }

        $products = DB::table('products')->whereNotNull('hall_id')->get();
        foreach ($stores as $store) {
            foreach ($products as $product) {
                if (!isset($hallMap[$store->id][$product->hall_id])) {
                    continue;
                }

                DB::table('product_storage_locations')->insert([
                    'product_id' => $product->id,
                    'store_id' => $store->id,
                    'hall_id' => $hallMap[$store->id][$product->hall_id],
                    'rack_id' => $product->rack_id && isset($rackMap[$store->id][$product->rack_id])
                        ? $rackMap[$store->id][$product->rack_id]
                        : null,
                    'shelf_id' => $product->shelf_id && isset($shelfMap[$store->id][$product->shelf_id])
                        ? $shelfMap[$store->id][$product->shelf_id]
                        : null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down()
    {
        Schema::dropIfExists('product_storage_locations');

        Schema::table('halls', function (Blueprint $table) {
            $table->dropUnique(['store_id', 'name']);
            $table->dropForeign(['store_id']);
            $table->dropColumn('store_id');
        });
    }
}
