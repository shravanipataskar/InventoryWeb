<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSupplierInvoiceNumberToQuotations extends Migration
{
    public function up()
    {
        if (!Schema::hasColumn('quotations', 'supplier_invoice_number')) {
            Schema::table('quotations', function (Blueprint $table) {
                $table->string('supplier_invoice_number', 100)->nullable();
            });
        }
    }

    public function down()
    {
        if (Schema::hasColumn('quotations', 'supplier_invoice_number')) {
            Schema::table('quotations', function (Blueprint $table) {
                $table->dropColumn('supplier_invoice_number');
            });
        }
    }
}
