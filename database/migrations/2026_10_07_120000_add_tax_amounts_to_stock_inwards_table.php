<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddTaxAmountsToStockInwardsTable extends Migration
{
    public function up()
    {
        Schema::table('stock_inwards', function (Blueprint $table) {
            $table->decimal('sgst_rate', 5, 2)->default(0);
            $table->decimal('cgst_rate', 5, 2)->default(0);
            $table->decimal('sgst_amount', 14, 2)->default(0);
            $table->decimal('cgst_amount', 14, 2)->default(0);
            $table->decimal('tax_total', 14, 2)->default(0);
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('grand_total', 14, 2)->default(0);
        });

        DB::table('stock_inwards')->update([
            'subtotal' => DB::raw('total_amount'),
            'grand_total' => DB::raw('total_amount'),
        ]);
    }

    public function down()
    {
        Schema::table('stock_inwards', function (Blueprint $table) {
            $table->dropColumn([
                'sgst_rate',
                'cgst_rate',
                'sgst_amount',
                'cgst_amount',
                'tax_total',
                'subtotal',
                'grand_total',
            ]);
        });
    }
}
