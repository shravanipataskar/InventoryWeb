<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddQuotationReferenceToPurchaseOrders extends Migration
{
    public function up()
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('purchase_orders', 'quotation_id')) {
                $table->unsignedBigInteger('quotation_id')->nullable()->unique();
                $table->foreign('quotation_id')->references('id')->on('quotations')->onDelete('set null');
            }
            if (!Schema::hasColumn('purchase_orders', 'quotation_request_id')) {
                $table->unsignedBigInteger('quotation_request_id')->nullable();
                $table->foreign('quotation_request_id')->references('id')->on('quotation_requests')->onDelete('set null');
            }
            if (!Schema::hasColumn('purchase_orders', 'cgst_total')) {
                $table->decimal('cgst_total', 15, 2)->default(0);
            }
            if (!Schema::hasColumn('purchase_orders', 'sgst_total')) {
                $table->decimal('sgst_total', 15, 2)->default(0);
            }
        });
        Schema::table('purchase_order_items', function (Blueprint $table) {
            if (!Schema::hasColumn('purchase_order_items', 'cgst_rate')) {
                $table->decimal('cgst_rate', 5, 2)->default(0);
                $table->decimal('cgst_amount', 15, 2)->default(0);
                $table->decimal('sgst_rate', 5, 2)->default(0);
                $table->decimal('sgst_amount', 15, 2)->default(0);
            }
        });
    }

    public function down()
    {
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->dropColumn(['cgst_rate', 'cgst_amount', 'sgst_rate', 'sgst_amount']);
        });
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropForeign(['quotation_id']);
            $table->dropForeign(['quotation_request_id']);
            $table->dropColumn(['quotation_id', 'quotation_request_id', 'cgst_total', 'sgst_total']);
        });
    }
}
