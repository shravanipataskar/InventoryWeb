<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIdentityDocumentsToCustomers extends Migration
{
    public function up()
    {
        if (Schema::hasTable('customers')
            && (!Schema::hasColumn('customers', 'aadhaar_card') || !Schema::hasColumn('customers', 'pan_card'))) {
            Schema::table('customers', function (Blueprint $table) {
                if (!Schema::hasColumn('customers', 'aadhaar_card')) {
                    $table->string('aadhaar_card')->nullable();
                }
                if (!Schema::hasColumn('customers', 'pan_card')) {
                    $table->string('pan_card')->nullable();
                }
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('customers')
            && (Schema::hasColumn('customers', 'aadhaar_card') || Schema::hasColumn('customers', 'pan_card'))) {
            Schema::table('customers', function (Blueprint $table) {
                $columns = [];
                if (Schema::hasColumn('customers', 'aadhaar_card')) {
                    $columns[] = 'aadhaar_card';
                }
                if (Schema::hasColumn('customers', 'pan_card')) {
                    $columns[] = 'pan_card';
                }
                if ($columns) {
                    $table->dropColumn($columns);
                }
            });
        }
    }
}
