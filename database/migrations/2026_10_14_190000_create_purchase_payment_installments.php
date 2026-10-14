<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePurchasePaymentInstallments extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('purchase_payment_installments')) {
            Schema::create('purchase_payment_installments', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('purchase_order_id');
                $table->unsignedInteger('installment_number');
                $table->date('due_date');
                $table->decimal('planned_amount', 14, 2);
                $table->string('submission_token', 36)->unique();
                $table->timestamp('cancelled_at')->nullable();
                $table->timestamps();

                $table->foreign('purchase_order_id')->references('id')->on('purchase_orders')->onDelete('restrict');
                $table->unique(['purchase_order_id', 'installment_number'], 'purchase_payment_installment_number_unique');
                $table->index(['purchase_order_id', 'due_date']);
            });
        }

        if (!Schema::hasColumn('purchase_payments', 'installment_id')) {
            Schema::table('purchase_payments', function (Blueprint $table) {
                $table->unsignedBigInteger('installment_id')->nullable()->after('purchase_order_id');
                $table->foreign('installment_id')
                    ->references('id')
                    ->on('purchase_payment_installments')
                    ->onDelete('set null');
            });
        }
    }

    public function down()
    {
        if (Schema::hasColumn('purchase_payments', 'installment_id')) {
            Schema::table('purchase_payments', function (Blueprint $table) {
                $table->dropForeign(['installment_id']);
                $table->dropColumn('installment_id');
            });
        }

        Schema::dropIfExists('purchase_payment_installments');
    }
}
