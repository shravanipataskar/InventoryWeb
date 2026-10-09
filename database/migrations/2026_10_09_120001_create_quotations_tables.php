<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateQuotationsTables extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('quotation_requests')) {
            Schema::create('quotation_requests', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('quotation_request_code')->unique();
                $table->date('request_date');
                $table->date('required_date')->nullable();
                $table->unsignedBigInteger('store_id');
                $table->unsignedBigInteger('created_by')->nullable();
                $table->string('status')->default('draft');
                $table->text('remarks')->nullable();
                $table->timestamps();
                $table->foreign('store_id')->references('id')->on('stores')->onDelete('restrict');
                $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            });
        }
        if (!Schema::hasTable('quotations')) {
            Schema::create('quotations', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('quotation_request_id');
                $table->unsignedBigInteger('supplier_id');
                $table->string('quotation_code')->unique();
                $table->date('quotation_date');
                $table->string('status')->default('draft');
                $table->decimal('subtotal', 15, 2)->default(0);
                $table->decimal('cgst_total', 15, 2)->default(0);
                $table->decimal('sgst_total', 15, 2)->default(0);
                $table->decimal('tax_total', 15, 2)->default(0);
                $table->decimal('grand_total', 15, 2)->default(0);
                $table->timestamp('submitted_at')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('rejected_at')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('submitted_by')->nullable();
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->unsignedBigInteger('rejected_by')->nullable();
                $table->text('rejection_reason')->nullable();
                $table->unsignedBigInteger('purchase_order_id')->nullable();
                $table->timestamps();
                $table->foreign('quotation_request_id')->references('id')->on('quotation_requests')->onDelete('cascade');
                $table->foreign('supplier_id')->references('id')->on('suppliers')->onDelete('restrict');
                $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
                $table->foreign('submitted_by')->references('id')->on('users')->onDelete('set null');
                $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');
                $table->foreign('rejected_by')->references('id')->on('users')->onDelete('set null');
                $table->foreign('purchase_order_id')->references('id')->on('purchase_orders')->onDelete('set null');
                $table->index(['quotation_request_id', 'status']);
            });
        }
        if (!Schema::hasTable('quotation_items')) {
            Schema::create('quotation_items', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('quotation_id');
                $table->unsignedBigInteger('product_id');
                $table->unsignedBigInteger('category_id');
                $table->unsignedBigInteger('unit_id')->nullable();
                $table->decimal('quantity', 12, 2);
                $table->decimal('supplier_rate', 15, 2)->default(0);
                $table->decimal('basic_amount', 15, 2)->default(0);
                $table->decimal('gst_rate', 5, 2)->default(0);
                $table->decimal('cgst_rate', 5, 2)->default(0);
                $table->decimal('cgst_amount', 15, 2)->default(0);
                $table->decimal('sgst_rate', 5, 2)->default(0);
                $table->decimal('sgst_amount', 15, 2)->default(0);
                $table->decimal('tax_amount', 15, 2)->default(0);
                $table->decimal('total_amount', 15, 2)->default(0);
                $table->timestamps();
                $table->foreign('quotation_id')->references('id')->on('quotations')->onDelete('cascade');
                $table->foreign('product_id')->references('id')->on('products')->onDelete('restrict');
                $table->foreign('category_id')->references('id')->on('categories')->onDelete('restrict');
                $table->foreign('unit_id')->references('id')->on('units')->onDelete('set null');
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('quotation_items');
        Schema::dropIfExists('quotations');
        Schema::dropIfExists('quotation_requests');
    }
}
