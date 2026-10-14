<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddReleasedPurchaseOrderStatus extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('purchase_orders') || DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE purchase_orders MODIFY status ENUM('draft','pending','approved','released','ordered','partially_received','received','cancelled') NOT NULL DEFAULT 'draft'");
    }

    public function down()
    {
        if (!Schema::hasTable('purchase_orders') || DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::table('purchase_orders')->where('status', 'released')->update(['status' => 'approved']);
        DB::statement("ALTER TABLE purchase_orders MODIFY status ENUM('draft','pending','approved','ordered','partially_received','received','cancelled') NOT NULL DEFAULT 'draft'");
    }
}
