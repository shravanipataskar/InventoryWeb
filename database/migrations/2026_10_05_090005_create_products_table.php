<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
   public function up()
{
    Schema::create('products', function (Blueprint $table) {
        $table->bigIncrements('id');

        $table->string('product_code')->unique();
        $table->string('name');

        $table->unsignedBigInteger('category_id');
        $table->unsignedBigInteger('unit_id');

        $table->string('barcode')->nullable()->unique();

        $table->decimal('purchase_price', 12, 2)->default(0);
        $table->decimal('selling_price', 12, 2)->default(0);
        $table->decimal('minimum_stock', 12, 2)->default(0);

        $table->text('description')->nullable();
        $table->boolean('is_active')->default(1);

        $table->timestamps();

        $table->foreign('category_id')
              ->references('id')
              ->on('categories')
              ->onDelete('restrict');

        $table->foreign('unit_id')
              ->references('id')
              ->on('units')
              ->onDelete('restrict');
    });
}

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('products');
    }
}
