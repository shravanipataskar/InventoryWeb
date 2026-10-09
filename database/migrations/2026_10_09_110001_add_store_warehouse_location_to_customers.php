<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddStoreWarehouseLocationToCustomers extends Migration
{
    public function up()
    {
        if (Schema::hasTable('customers') && !Schema::hasColumn('customers', 'store_warehouse_location')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->string('store_warehouse_location', 1000)->nullable();
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('customers') && Schema::hasColumn('customers', 'store_warehouse_location')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->dropColumn('store_warehouse_location');
            });
        }
    }
}
