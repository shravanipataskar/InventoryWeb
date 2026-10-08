<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddStockTransferItemStockSnapshots extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('stock_transfer_items')) {
            return;
        }

        Schema::table('stock_transfer_items', function (Blueprint $table) {
            if (!Schema::hasColumn('stock_transfer_items', 'source_before')) {
                $table->decimal('source_before', 12, 2)->nullable();
            }
            if (!Schema::hasColumn('stock_transfer_items', 'destination_before')) {
                $table->decimal('destination_before', 12, 2)->nullable();
            }
        });
    }

    public function down()
    {
        // Transfer history is retained when rolling back application changes.
    }
}
