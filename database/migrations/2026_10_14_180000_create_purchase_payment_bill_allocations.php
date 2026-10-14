<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePurchasePaymentBillAllocations extends Migration
{
    public function up()
    {
        if (Schema::hasTable('purchase_payment_bill_allocations')) {
            return;
        }

        Schema::create('purchase_payment_bill_allocations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('purchase_payment_id');
            $table->unsignedBigInteger('supplier_bill_id');
            $table->decimal('amount', 15, 2);
            $table->timestamps();

            $table->foreign('purchase_payment_id')->references('id')->on('purchase_payments')->onDelete('restrict');
            $table->foreign('supplier_bill_id')->references('id')->on('supplier_bills')->onDelete('restrict');
            $table->unique(['purchase_payment_id', 'supplier_bill_id'], 'ppba_payment_bill_unique');
            $table->index('supplier_bill_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('purchase_payment_bill_allocations');
    }
}
