<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPurchaseOrderReleaseTracking extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('purchase_orders')) {
            return;
        }

        Schema::table('purchase_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('purchase_orders', 'released_by')) {
                $table->unsignedBigInteger('released_by')->nullable();
            }
            if (!Schema::hasColumn('purchase_orders', 'released_at')) {
                $table->timestamp('released_at')->nullable();
            }
        });
    }

    public function down()
    {
        if (!Schema::hasTable('purchase_orders')) {
            return;
        }

        Schema::table('purchase_orders', function (Blueprint $table) {
            $columns = [];
            if (Schema::hasColumn('purchase_orders', 'released_by')) {
                $columns[] = 'released_by';
            }
            if (Schema::hasColumn('purchase_orders', 'released_at')) {
                $columns[] = 'released_at';
            }
            if ($columns) {
                $table->dropColumn($columns);
            }
        });
    }
}
