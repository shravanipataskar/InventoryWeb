<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddReferenceNumberToStockTransactionsTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('stock_transactions')
            && !Schema::hasColumn('stock_transactions', 'reference_number')) {
            Schema::table('stock_transactions', function (Blueprint $table) {
                $table->string('reference_number', 100)->nullable()->unique();
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('stock_transactions')
            && Schema::hasColumn('stock_transactions', 'reference_number')) {
            Schema::table('stock_transactions', function (Blueprint $table) {
                $table->dropUnique(['reference_number']);
                $table->dropColumn('reference_number');
            });
        }
    }
}
