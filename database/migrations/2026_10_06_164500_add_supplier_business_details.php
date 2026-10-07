<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSupplierBusinessDetails extends Migration
{
    public function up()
    {
        Schema::table('suppliers', function (Blueprint $table) {
            if (!Schema::hasColumn('suppliers', 'pan_number')) {
                $table->string('pan_number', 20)->nullable();
            }
            if (!Schema::hasColumn('suppliers', 'payment_terms')) {
                $table->string('payment_terms', 50)->nullable();
            }
            if (!Schema::hasColumn('suppliers', 'bank_name')) {
                $table->string('bank_name', 255)->nullable();
            }
            if (!Schema::hasColumn('suppliers', 'account_holder_name')) {
                $table->string('account_holder_name', 255)->nullable();
            }
            if (!Schema::hasColumn('suppliers', 'account_number')) {
                $table->string('account_number', 100)->nullable();
            }
            if (!Schema::hasColumn('suppliers', 'ifsc_code')) {
                $table->string('ifsc_code', 20)->nullable();
            }
            if (!Schema::hasColumn('suppliers', 'branch_name')) {
                $table->string('branch_name', 255)->nullable();
            }
            if (!Schema::hasColumn('suppliers', 'notes')) {
                $table->text('notes')->nullable();
            }
        });
    }

    public function down()
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $columns = [];
            foreach ([
                'pan_number',
                'payment_terms',
                'bank_name',
                'account_holder_name',
                'account_number',
                'ifsc_code',
                'branch_name',
                'notes',
            ] as $column) {
                if (Schema::hasColumn('suppliers', $column)) {
                    $columns[] = $column;
                }
            }

            if ($columns) {
                $table->dropColumn($columns);
            }
        });
    }
}
