<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSupplierDocumentsAndLocations extends Migration
{
    public function up()
    {
        Schema::table('suppliers', function (Blueprint $table) {
            if (!Schema::hasColumn('suppliers', 'warehouse_location')) {
                $table->string('warehouse_location', 1000)->nullable();
            }
            if (!Schema::hasColumn('suppliers', 'aadhaar_card')) {
                $table->string('aadhaar_card')->nullable();
            }
            if (!Schema::hasColumn('suppliers', 'pan_card')) {
                $table->string('pan_card')->nullable();
            }
        });
    }

    public function down()
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $columns = [];
            foreach (['warehouse_location', 'aadhaar_card', 'pan_card'] as $column) {
                if (Schema::hasColumn('suppliers', $column)) {
                    $columns[] = $column;
                }
            }
            if ($columns) {
                $table->dropColumn($columns);
            }
        });
    }
}
