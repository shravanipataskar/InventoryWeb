<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePurchasePaymentsTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('purchase_payments')) {
            return;
        }

        Schema::create('purchase_payments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('purchase_order_id');
            $table->date('payment_date');
            $table->decimal('amount', 14, 2);
            $table->string('payment_method', 30);
            $table->string('reference_number', 100)->nullable();
            $table->text('remarks')->nullable();
            $table->string('status', 20)->default('paid');
            $table->string('submission_token', 36)->unique();
            $table->unsignedBigInteger('entered_by')->nullable();
            $table->timestamps();

            $table->foreign('purchase_order_id')->references('id')->on('purchase_orders')->onDelete('restrict');
            $table->foreign('entered_by')->references('id')->on('users')->onDelete('set null');
            $table->index(['purchase_order_id', 'payment_date']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('purchase_payments');
    }
}
