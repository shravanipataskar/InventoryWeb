<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIsActiveToStockMovements extends Migration
{
    public function up()
    {
        Schema::table('stock_inwards', function (Blueprint $table) {
            $table->boolean('is_active')->default(true);
        });

        Schema::table('stock_outwards', function (Blueprint $table) {
            $table->boolean('is_active')->default(true);
        });
    }

    public function down()
    {
        Schema::table('stock_outwards', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });

        Schema::table('stock_inwards', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
}
