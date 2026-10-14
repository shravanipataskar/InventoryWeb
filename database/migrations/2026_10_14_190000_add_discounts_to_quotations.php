<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDiscountsToQuotations extends Migration
{
    public function up()
    {
        if (!Schema::hasColumn('quotation_items', 'discount_percent')) {
            Schema::table('quotation_items', function (Blueprint $table) {
                $table->decimal('discount_percent', 5, 2)->default(0)->after('basic_amount');
            });
        }

        if (!Schema::hasColumn('quotations', 'discount_total')) {
            Schema::table('quotations', function (Blueprint $table) {
                $table->decimal('discount_total', 15, 2)->default(0)->after('subtotal');
            });
        }
    }

    public function down()
    {
        if (Schema::hasColumn('quotations', 'discount_total')) {
            Schema::table('quotations', function (Blueprint $table) {
                $table->dropColumn('discount_total');
            });
        }

        if (Schema::hasColumn('quotation_items', 'discount_percent')) {
            Schema::table('quotation_items', function (Blueprint $table) {
                $table->dropColumn('discount_percent');
            });
        }
    }
}
