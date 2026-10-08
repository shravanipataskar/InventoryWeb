<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpgradeStockTransfersForMultiItemWorkflow extends Migration
{
    public function up()
    {
        if (Schema::hasTable('stock_transfers')) {
            Schema::table('stock_transfers', function (Blueprint $table) {
                if (!Schema::hasColumn('stock_transfers', 'from_store_id')) {
                    $table->unsignedBigInteger('from_store_id')->nullable();
                }
                if (!Schema::hasColumn('stock_transfers', 'to_store_id')) {
                    $table->unsignedBigInteger('to_store_id')->nullable();
                }
                if (!Schema::hasColumn('stock_transfers', 'transfer_reason')) {
                    $table->string('transfer_reason', 100)->nullable();
                }
                if (!Schema::hasColumn('stock_transfers', 'transfer_type')) {
                    $table->string('transfer_type', 50)->default('Internal Stock Transfer');
                }
                if (!Schema::hasColumn('stock_transfers', 'reference_no')) {
                    $table->string('reference_no', 100)->nullable();
                }
                if (!Schema::hasColumn('stock_transfers', 'status')) {
                    $table->string('status', 30)->default('completed');
                }
                if (!Schema::hasColumn('stock_transfers', 'created_by')) {
                    $table->unsignedBigInteger('created_by')->nullable();
                }
                if (!Schema::hasColumn('stock_transfers', 'approved_by')) {
                    $table->unsignedBigInteger('approved_by')->nullable();
                }
                if (!Schema::hasColumn('stock_transfers', 'submission_key')) {
                    $table->uuid('submission_key')->nullable()->unique();
                }
            });
        }

        if (!Schema::hasTable('stock_transfer_items')) {
            Schema::create('stock_transfer_items', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('stock_transfer_id');
                $table->unsignedBigInteger('product_id');
                $table->decimal('quantity', 12, 2);
                $table->unsignedBigInteger('category_id')->nullable();
                $table->text('remarks')->nullable();
                $table->decimal('source_before', 12, 2)->nullable();
                $table->decimal('destination_before', 12, 2)->nullable();
                $table->timestamps();
                $table->index(['stock_transfer_id', 'product_id']);
            });
        } else {
            Schema::table('stock_transfer_items', function (Blueprint $table) {
                if (!Schema::hasColumn('stock_transfer_items', 'category_id')) {
                    $table->unsignedBigInteger('category_id')->nullable();
                }
                if (!Schema::hasColumn('stock_transfer_items', 'remarks')) {
                    $table->text('remarks')->nullable();
                }
                if (!Schema::hasColumn('stock_transfer_items', 'source_before')) {
                    $table->decimal('source_before', 12, 2)->nullable();
                }
                if (!Schema::hasColumn('stock_transfer_items', 'destination_before')) {
                    $table->decimal('destination_before', 12, 2)->nullable();
                }
            });
        }
    }

    public function down()
    {
        // Transfer records are retained when rolling back application changes.
    }
}
