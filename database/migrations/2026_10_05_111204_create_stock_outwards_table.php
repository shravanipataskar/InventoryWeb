<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateStockOutwardsTable extends Migration
{
    public function up()
    {
        Schema::create('stock_outwards', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('product_id');

            $table->string('reference_number')->nullable();

            $table->date('outward_date');

            $table->decimal('quantity', 12, 2);

            $table->decimal('selling_price', 12, 2);

            $table->decimal('total_amount', 14, 2);

            $table->string('issued_to')->nullable();

            $table->text('remarks')->nullable();

            $table->timestamps();

            $table->foreign('product_id')
                ->references('id')
                ->on('products')
                ->onDelete('restrict');
        });
    }

    public function down()
    {
        Schema::dropIfExists('stock_outwards');
    }
}