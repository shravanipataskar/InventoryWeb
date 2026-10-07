<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddProductReorderSettings extends Migration
{
    public function up()
    {
        if (!Schema::hasColumn('products', 'reorder_level')) {
            Schema::table('products', function (Blueprint $table) {
                $table->decimal('reorder_level', 12, 2)->default(0);
            });
        }

        if (!Schema::hasColumn('products', 'reorder_quantity')) {
            Schema::table('products', function (Blueprint $table) {
                $table->decimal('reorder_quantity', 12, 2)->default(0);
            });
        }

        if (!Schema::hasColumn('products', 'image')) {
            Schema::table('products', function (Blueprint $table) {
                $table->string('image')->nullable();
            });
        }
    }

    public function down()
    {
        if (Schema::hasColumn('products', 'reorder_quantity')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('reorder_quantity');
            });
        }

        if (Schema::hasColumn('products', 'reorder_level')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('reorder_level');
            });
        }
    }
}
