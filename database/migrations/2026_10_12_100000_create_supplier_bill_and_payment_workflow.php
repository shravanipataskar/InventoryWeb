<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateSupplierBillAndPaymentWorkflow extends Migration
{
    private $permissionModules = ['bills', 'payments'];

    public function up()
    {
        if (!Schema::hasColumn('goods_receipt_items', 'damaged_quantity')) {
            Schema::table('goods_receipt_items', function (Blueprint $table) {
                // Portion of rejected_quantity that was damaged; rejected_quantity stays the total not accepted.
                $table->decimal('damaged_quantity', 12, 2)->default(0);
            });
        }

        foreach (['cancelled_at', 'closed_at'] as $column) {
            if (!Schema::hasColumn('purchase_orders', $column)) {
                Schema::table('purchase_orders', function (Blueprint $table) use ($column) {
                    $table->timestamp($column)->nullable();
                });
            }
        }
        if (!Schema::hasColumn('purchase_orders', 'status_reason')) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                $table->text('status_reason')->nullable();
            });
        }

        if (!Schema::hasTable('supplier_bills')) {
            Schema::create('supplier_bills', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('bill_number')->unique();
                $table->string('submission_token', 36)->unique();
                $table->unsignedBigInteger('supplier_id');
                $table->unsignedBigInteger('purchase_order_id');
                $table->string('invoice_number', 100);
                // supplier_id|normalized invoice number; cleared when the bill is rejected or cancelled.
                $table->string('duplicate_key', 191)->nullable()->unique();
                $table->date('invoice_date');
                $table->date('due_date')->nullable();
                $table->decimal('subtotal', 15, 2)->default(0);
                $table->decimal('discount_amount', 15, 2)->default(0);
                $table->decimal('tax_amount', 15, 2)->default(0);
                $table->decimal('total_amount', 15, 2)->default(0);
                $table->decimal('invoice_total', 15, 2)->default(0);
                $table->decimal('paid_amount', 15, 2)->default(0);
                $table->string('status')->default('under_verification');
                $table->string('match_status')->default('pending');
                $table->text('match_result')->nullable();
                $table->boolean('exception_approved')->default(false);
                $table->text('status_reason')->nullable();
                $table->string('attachment_path')->nullable();
                $table->string('attachment_name')->nullable();
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamps();
                $table->foreign('supplier_id')->references('id')->on('suppliers')->onDelete('restrict');
                $table->foreign('purchase_order_id')->references('id')->on('purchase_orders')->onDelete('restrict');
                $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
                $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');
                $table->index(['supplier_id', 'status']);
                $table->index(['status', 'due_date']);
            });
        }

        if (!Schema::hasTable('supplier_bill_items')) {
            Schema::create('supplier_bill_items', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('supplier_bill_id');
                $table->unsignedBigInteger('purchase_order_item_id');
                $table->unsignedBigInteger('product_id');
                $table->decimal('quantity', 12, 2);
                $table->decimal('rate', 15, 2);
                $table->decimal('discount_percent', 5, 2)->default(0);
                $table->decimal('tax_percent', 5, 2)->default(0);
                $table->decimal('basic_amount', 15, 2)->default(0);
                $table->decimal('discount_amount', 15, 2)->default(0);
                $table->decimal('tax_amount', 15, 2)->default(0);
                $table->decimal('total_amount', 15, 2)->default(0);
                $table->timestamps();
                $table->foreign('supplier_bill_id')->references('id')->on('supplier_bills')->onDelete('cascade');
                $table->foreign('purchase_order_item_id')->references('id')->on('purchase_order_items')->onDelete('restrict');
                $table->foreign('product_id')->references('id')->on('products')->onDelete('restrict');
                $table->unique(['supplier_bill_id', 'purchase_order_item_id'], 'sbi_bill_po_item_unique');
            });
        }

        if (!Schema::hasTable('supplier_payments')) {
            Schema::create('supplier_payments', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('payment_number')->unique();
                $table->string('submission_token', 36)->unique();
                $table->unsignedBigInteger('supplier_id');
                $table->decimal('amount', 15, 2);
                $table->string('payment_method', 20);
                $table->date('payment_date')->nullable();
                $table->string('reference_number', 100)->nullable();
                $table->string('account_reference', 100)->nullable();
                $table->string('status')->default('pending_approval');
                $table->text('notes')->nullable();
                $table->text('status_reason')->nullable();
                $table->string('proof_path')->nullable();
                $table->string('proof_name')->nullable();
                $table->unsignedBigInteger('requested_by')->nullable();
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->unsignedBigInteger('completed_by')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
                $table->foreign('supplier_id')->references('id')->on('suppliers')->onDelete('restrict');
                $table->foreign('requested_by')->references('id')->on('users')->onDelete('set null');
                $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');
                $table->foreign('completed_by')->references('id')->on('users')->onDelete('set null');
                $table->index(['supplier_id', 'status']);
                $table->index(['payment_method', 'reference_number']);
            });
        }

        if (!Schema::hasTable('payment_allocations')) {
            Schema::create('payment_allocations', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('supplier_payment_id');
                $table->unsignedBigInteger('supplier_bill_id');
                $table->decimal('amount', 15, 2);
                $table->timestamps();
                $table->foreign('supplier_payment_id')->references('id')->on('supplier_payments')->onDelete('restrict');
                $table->foreign('supplier_bill_id')->references('id')->on('supplier_bills')->onDelete('restrict');
                $table->unique(['supplier_payment_id', 'supplier_bill_id'], 'payment_allocation_unique');
            });
        }

        if (!Schema::hasTable('procurement_audit_logs')) {
            Schema::create('procurement_audit_logs', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('entity_type', 50);
                $table->unsignedBigInteger('entity_id');
                $table->string('event', 60);
                $table->string('from_status', 40)->nullable();
                $table->string('to_status', 40)->nullable();
                $table->text('reason')->nullable();
                $table->text('meta')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('user_name')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->timestamp('created_at')->nullable();
                $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
                $table->index(['entity_type', 'entity_id']);
            });
        }

        $this->seedPermissions();
    }

    public function down()
    {
        // Rolling back drops only the tables introduced here. Export supplier_bills,
        // supplier_payments, payment_allocations and procurement_audit_logs first if they hold real data.
        Schema::dropIfExists('procurement_audit_logs');
        Schema::dropIfExists('payment_allocations');
        Schema::dropIfExists('supplier_payments');
        Schema::dropIfExists('supplier_bill_items');
        Schema::dropIfExists('supplier_bills');

        if (Schema::hasTable('permissions')) {
            $ids = DB::table('permissions')->whereIn('module', $this->permissionModules)->pluck('id');
            DB::table('permission_role')->whereIn('permission_id', $ids)->delete();
            DB::table('permissions')->whereIn('id', $ids)->delete();
        }
        // goods_receipt_items.damaged_quantity and purchase_orders status columns are kept:
        // dropping them would discard recorded business data.
    }

    private function seedPermissions()
    {
        if (!Schema::hasTable('permissions') || !Schema::hasTable('roles')) {
            return;
        }

        $actions = ['view', 'create', 'edit', 'delete', 'approve', 'reject'];
        foreach ($this->permissionModules as $module) {
            foreach ($actions as $action) {
                if (!DB::table('permissions')->where('module', $module)->where('action', $action)->exists()) {
                    DB::table('permissions')->insert([
                        'module' => $module,
                        'action' => $action,
                        'label' => ucwords($module) . ' ' . ucfirst($action),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        $roles = DB::table('roles')->pluck('id', 'name');
        $permissions = DB::table('permissions')->whereIn('module', $this->permissionModules)->get(['id', 'action']);
        $grants = [
            'ADMIN' => $actions,
            'PURCHASE_OFFICER' => ['view', 'create', 'edit'],
            'OWNER' => ['view', 'approve', 'reject'],
        ];
        foreach ($grants as $roleName => $allowed) {
            if (!isset($roles[$roleName])) {
                continue;
            }
            foreach ($permissions as $permission) {
                if (in_array($permission->action, $allowed, true)) {
                    DB::table('permission_role')->updateOrInsert([
                        'role_id' => $roles[$roleName],
                        'permission_id' => $permission->id,
                    ], []);
                }
            }
        }
    }
}
