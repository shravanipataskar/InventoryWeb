<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddBillCopyToQuotations extends Migration
{
    public function up()
    {
        if (Schema::hasTable('quotations')
            && (!Schema::hasColumn('quotations', 'bill_copy_path') || !Schema::hasColumn('quotations', 'bill_copy_name'))) {
            Schema::table('quotations', function (Blueprint $table) {
                if (!Schema::hasColumn('quotations', 'bill_copy_path')) {
                    $table->string('bill_copy_path')->nullable();
                }
                if (!Schema::hasColumn('quotations', 'bill_copy_name')) {
                    $table->string('bill_copy_name')->nullable();
                }
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('quotations')
            && (Schema::hasColumn('quotations', 'bill_copy_path') || Schema::hasColumn('quotations', 'bill_copy_name'))) {
            Schema::table('quotations', function (Blueprint $table) {
                $columns = [];
                if (Schema::hasColumn('quotations', 'bill_copy_path')) {
                    $columns[] = 'bill_copy_path';
                }
                if (Schema::hasColumn('quotations', 'bill_copy_name')) {
                    $columns[] = 'bill_copy_name';
                }
                if ($columns) {
                    $table->dropColumn($columns);
                }
            });
        }
    }
}
