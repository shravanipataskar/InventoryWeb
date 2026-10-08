<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOpeningStockWorkflow extends Migration
{
    public function up()
    {
        foreach (['track_batch', 'track_serial'] as $column) {
            if (!Schema::hasColumn('products', $column)) {
                Schema::table('products', function (Blueprint $table) use ($column) {
                    $table->boolean($column)->default(false);
                });
            }
        }

        if (!Schema::hasTable('opening_stock_headers')) {
            Schema::create('opening_stock_headers', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('opening_number')->unique();
                $table->unsignedBigInteger('store_id');
                $table->date('opening_date');
                $table->unsignedBigInteger('created_by')->nullable();
                $table->string('status')->default('posted');
                $table->timestamps();

                $table->foreign('store_id')->references('id')->on('stores')->onDelete('restrict');
                $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
                $table->index(['store_id', 'opening_date'], 'opening_stock_location_date_index');
            });
        }

        if (!Schema::hasTable('opening_stock_items')) {
            Schema::create('opening_stock_items', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('opening_stock_header_id');
                $table->unsignedBigInteger('product_id');
                $table->unsignedBigInteger('category_id');
                $table->unsignedBigInteger('unit_id')->nullable();
                $table->decimal('quantity', 12, 2);
                $table->decimal('unit_purchase_cost', 12, 2);
                $table->decimal('opening_value', 14, 2);
                $table->string('batch_lot')->nullable();
                $table->text('remarks')->nullable();
                $table->timestamps();

                $table->foreign('opening_stock_header_id')->references('id')->on('opening_stock_headers')->onDelete('restrict');
                $table->foreign('product_id')->references('id')->on('products')->onDelete('restrict');
                $table->foreign('category_id')->references('id')->on('categories')->onDelete('restrict');
                $table->foreign('unit_id')->references('id')->on('units')->onDelete('set null');
                $table->index(['product_id', 'opening_stock_header_id'], 'opening_stock_item_product_index');
            });
        }

        if (!Schema::hasTable('opening_stock_serials')) {
            Schema::create('opening_stock_serials', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('opening_stock_item_id');
                $table->unsignedBigInteger('product_id');
                $table->unsignedBigInteger('store_id');
                $table->string('serial_number', 191);
                $table->timestamps();

                $table->foreign('opening_stock_item_id')->references('id')->on('opening_stock_items')->onDelete('restrict');
                $table->foreign('product_id')->references('id')->on('products')->onDelete('restrict');
                $table->foreign('store_id')->references('id')->on('stores')->onDelete('restrict');
                $table->unique(['product_id', 'serial_number'], 'opening_stock_product_serial_unique');
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('opening_stock_serials');
        Schema::dropIfExists('opening_stock_items');
        Schema::dropIfExists('opening_stock_headers');

        foreach (['track_batch', 'track_serial'] as $column) {
            if (Schema::hasColumn('products', $column)) {
                Schema::table('products', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }
}
