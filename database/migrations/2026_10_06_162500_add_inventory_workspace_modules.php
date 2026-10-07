<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddInventoryWorkspaceModules extends Migration
{
    public function up()
    {
        if (!Schema::hasColumn('products', 'opening_stock')) {
            Schema::table('products', function (Blueprint $table) {
                $table->decimal('opening_stock', 12, 2)->default(0);
            });
        }

        if (!Schema::hasColumn('users', 'role')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('role')->default('viewer');
            });
        }

        if (!Schema::hasColumn('users', 'is_active')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('is_active')->default(true);
            });
        }

        if (!Schema::hasColumn('users', 'phone')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('phone', 30)->nullable();
            });
        }

        if (!Schema::hasColumn('users', 'last_login_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->timestamp('last_login_at')->nullable();
            });
        }

        if (!Schema::hasTable('stock_transfers')) {
            Schema::create('stock_transfers', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('product_id');
                $table->string('transfer_number')->unique();
                $table->date('transfer_date');
                $table->string('from_location');
                $table->string('to_location');
                $table->decimal('quantity', 12, 2);
                $table->text('remarks')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->foreign('product_id')
                    ->references('id')
                    ->on('products')
                    ->onDelete('restrict');
            });
        }

        if (!Schema::hasTable('stock_adjustments')) {
            Schema::create('stock_adjustments', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('product_id');
                $table->string('reference_number')->unique();
                $table->date('adjustment_date');
                $table->decimal('quantity_before', 12, 2);
                $table->decimal('adjustment_quantity', 12, 2);
                $table->decimal('quantity_after', 12, 2);
                $table->string('reason');
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->foreign('product_id')
                    ->references('id')
                    ->on('products')
                    ->onDelete('restrict');
            });
        }

        if (!Schema::hasTable('activity_logs')) {
            Schema::create('activity_logs', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('actor_name');
                $table->string('action');
                $table->string('subject')->nullable();
                $table->text('description');
                $table->timestamps();
            });
        }
    }

    public function down()
    {
        if (Schema::hasColumn('activity_logs', 'actor_name')
            && !Schema::hasColumn('activity_logs', 'user_id')) {
            Schema::dropIfExists('activity_logs');
        }

        if (Schema::hasColumn('stock_adjustments', 'reference_number')
            && Schema::hasColumn('stock_adjustments', 'adjustment_quantity')) {
            Schema::dropIfExists('stock_adjustments');
        }

        if (Schema::hasColumn('stock_transfers', 'from_location')
            && Schema::hasColumn('stock_transfers', 'product_id')) {
            Schema::dropIfExists('stock_transfers');
        }
    }
}
