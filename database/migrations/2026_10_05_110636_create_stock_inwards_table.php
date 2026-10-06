<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateStockInwardsTable extends Migration
{
    public function up()
    {
        Schema::create('stock_inwards', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('supplier_id');

            $table->string('invoice_number')->nullable();

            $table->date('inward_date');

            $table->decimal('quantity', 12, 2);

            $table->decimal('purchase_price', 12, 2);

            $table->decimal('total_amount', 14, 2);

            $table->text('remarks')->nullable();

            $table->timestamps();

            $table->foreign('product_id')
                ->references('id')
                ->on('products')
                ->onDelete('restrict');

            $table->foreign('supplier_id')
                ->references('id')
                ->on('suppliers')
                ->onDelete('restrict');
        });
    }

    public function down()
    {
        Schema::dropIfExists('stock_inwards');
    }
}