<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreatePurchaseReceivingWorkflow extends Migration
{
    public function up()
    {
        if (!Schema::hasColumn('suppliers', 'supplier_code')) {
            Schema::table('suppliers', function (Blueprint $table) {
                $table->string('supplier_code')->nullable()->unique();
            });
        }
        if (!Schema::hasColumn('suppliers', 'gst_number')) {
            Schema::table('suppliers', function (Blueprint $table) {
                $table->string('gst_number', 50)->nullable();
            });
        }
        foreach (['city', 'state', 'pincode'] as $column) {
            if (!Schema::hasColumn('suppliers', $column)) {
                Schema::table('suppliers', function (Blueprint $table) use ($column) {
                    $table->string($column)->nullable();
                });
            }
        }

        if (!Schema::hasTable('purchase_orders')) {
            Schema::create('purchase_orders', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('po_number')->unique();
                $table->unsignedBigInteger('supplier_id');
                $table->unsignedBigInteger('store_id');
                $table->date('po_date');
                $table->date('expected_date')->nullable();
                $table->string('status')->default('approved');
                $table->decimal('subtotal', 14, 2)->default(0);
                $table->decimal('discount_amount', 14, 2)->default(0);
                $table->decimal('tax_amount', 14, 2)->default(0);
                $table->decimal('grand_total', 14, 2)->default(0);
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
                $table->foreign('supplier_id')->references('id')->on('suppliers')->onDelete('restrict');
                $table->foreign('store_id')->references('id')->on('stores')->onDelete('restrict');
                $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            });
        }

        if (!Schema::hasTable('purchase_order_items')) {
            Schema::create('purchase_order_items', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('purchase_order_id');
                $table->unsignedBigInteger('product_id');
                $table->unsignedBigInteger('unit_id')->nullable();
                $table->decimal('quantity', 12, 2);
                $table->decimal('received_quantity', 12, 2)->default(0);
                $table->decimal('purchase_rate', 12, 2);
                $table->decimal('discount_percent', 5, 2)->default(0);
                $table->decimal('tax_percent', 5, 2)->default(0);
                $table->decimal('total_amount', 14, 2);
                $table->timestamps();
                $table->foreign('purchase_order_id')->references('id')->on('purchase_orders')->onDelete('cascade');
                $table->foreign('product_id')->references('id')->on('products')->onDelete('restrict');
                $table->foreign('unit_id')->references('id')->on('units')->onDelete('set null');
            });
        }

        if (!Schema::hasTable('goods_receipts')) {
            Schema::create('goods_receipts', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('grn_number')->nullable()->unique();
                $table->string('submission_token', 36)->nullable()->unique();
                $table->unsignedBigInteger('purchase_order_id');
                $table->unsignedBigInteger('supplier_id');
                $table->unsignedBigInteger('store_id');
                $table->date('received_date');
                $table->string('invoice_number')->nullable();
                $table->text('remarks')->nullable();
                $table->string('status')->default('posted');
                $table->unsignedBigInteger('received_by')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
                $table->foreign('purchase_order_id')->references('id')->on('purchase_orders')->onDelete('restrict');
                $table->foreign('supplier_id')->references('id')->on('suppliers')->onDelete('restrict');
                $table->foreign('store_id')->references('id')->on('stores')->onDelete('restrict');
                $table->foreign('received_by')->references('id')->on('users')->onDelete('set null');
                $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            });
        }

        if (!Schema::hasTable('goods_receipt_items')) {
            Schema::create('goods_receipt_items', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('goods_receipt_id');
                $table->unsignedBigInteger('purchase_order_item_id');
                $table->unsignedBigInteger('product_id');
                $table->decimal('ordered_quantity', 12, 2);
                $table->decimal('received_quantity', 12, 2);
                $table->decimal('rejected_quantity', 12, 2);
                $table->decimal('accepted_quantity', 12, 2);
                $table->decimal('purchase_rate', 12, 2);
                $table->timestamps();
                $table->foreign('goods_receipt_id')->references('id')->on('goods_receipts')->onDelete('cascade');
                $table->foreign('purchase_order_item_id')->references('id')->on('purchase_order_items')->onDelete('restrict');
                $table->foreign('product_id')->references('id')->on('products')->onDelete('restrict');
            });
        }

        if (!$this->hasReceiptItemUniqueIndex()) {
            Schema::table('goods_receipt_items', function (Blueprint $table) {
                $table->unique(['goods_receipt_id', 'purchase_order_item_id'], 'gr_receipt_po_item_unique');
            });
        }

        Schema::table('stock_inwards', function (Blueprint $table) {
            $table->string('inward_number')->nullable()->unique();
            $table->unsignedBigInteger('goods_receipt_item_id')->nullable()->unique();
            $table->unsignedBigInteger('store_id')->nullable();
            $table->decimal('received_quantity', 12, 2)->default(0);
            $table->decimal('rejected_quantity', 12, 2)->default(0);
            $table->string('status')->default('posted');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->foreign('goods_receipt_item_id')->references('id')->on('goods_receipt_items')->onDelete('restrict');
            $table->foreign('store_id')->references('id')->on('stores')->onDelete('restrict');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
        });

        if (!Schema::hasTable('stock_transactions')) {
            Schema::create('stock_transactions', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('store_id')->nullable();
                $table->unsignedBigInteger('product_id');
                $table->string('transaction_type');
                $table->string('reference_type')->nullable();
                $table->unsignedBigInteger('reference_id')->nullable();
                $table->decimal('quantity_in', 12, 2)->default(0);
                $table->decimal('quantity_out', 12, 2)->default(0);
                $table->decimal('balance_quantity', 12, 2);
                $table->decimal('unit_price', 12, 2)->default(0);
                $table->date('transaction_date');
                $table->text('remarks')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
                $table->foreign('store_id')->references('id')->on('stores')->onDelete('restrict');
                $table->foreign('product_id')->references('id')->on('products')->onDelete('restrict');
                $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
                $table->unique(['reference_type', 'reference_id', 'product_id'], 'stock_transactions_source_product_unique');
                $table->index(['store_id', 'product_id', 'transaction_date'], 'stock_transactions_balance_index');
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('stock_transactions');

        Schema::table('stock_inwards', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropForeign(['store_id']);
            $table->dropForeign(['goods_receipt_item_id']);
            $table->dropColumn([
                'inward_number',
                'goods_receipt_item_id',
                'store_id',
                'received_quantity',
                'rejected_quantity',
                'status',
                'created_by',
            ]);
        });

        Schema::dropIfExists('goods_receipt_items');
        Schema::dropIfExists('goods_receipts');
        // Purchase order tables and stock_transactions may predate this migration.
    }

    private function hasReceiptItemUniqueIndex()
    {
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            return count(DB::select("SHOW INDEX FROM `goods_receipt_items` WHERE `Key_name` = 'gr_receipt_po_item_unique'")) > 0;
        }

        if ($driver === 'sqlite') {
            foreach (DB::select("PRAGMA index_list('goods_receipt_items')") as $index) {
                if ($index->name === 'gr_receipt_po_item_unique') {
                    return true;
                }
            }

            return false;
        }

        throw new \RuntimeException('Goods receipt migration does not support database driver: ' . $driver);
    }
}
