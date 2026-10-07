<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateCustomersAndLinkStockOutwards extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('customers')) {
            Schema::create('customers', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('customer_code')->unique();
                $table->string('name');
                $table->string('customer_type', 50)->default('Other');
                $table->string('contact_person')->nullable();
                $table->string('phone', 30)->nullable();
                $table->string('email')->nullable();
                $table->text('address')->nullable();
                $table->string('city')->nullable();
                $table->string('state')->nullable();
                $table->string('pincode', 20)->nullable();
                $table->string('gstin', 20)->nullable();
                $table->string('pan', 20)->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        } else {
            $this->addCustomerColumn('customer_code', function (Blueprint $table) {
                $table->string('customer_code')->nullable()->unique();
            });
            $this->addCustomerColumn('customer_type', function (Blueprint $table) {
                $table->string('customer_type', 50)->default('Other');
            });
            $this->addCustomerColumn('contact_person', function (Blueprint $table) {
                $table->string('contact_person')->nullable();
            });
            $this->addCustomerColumn('phone', function (Blueprint $table) {
                $table->string('phone', 30)->nullable();
            });
            $this->addCustomerColumn('email', function (Blueprint $table) {
                $table->string('email')->nullable();
            });
            $this->addCustomerColumn('address', function (Blueprint $table) {
                $table->text('address')->nullable();
            });
            $this->addCustomerColumn('city', function (Blueprint $table) {
                $table->string('city')->nullable();
            });
            $this->addCustomerColumn('state', function (Blueprint $table) {
                $table->string('state')->nullable();
            });
            $this->addCustomerColumn('pincode', function (Blueprint $table) {
                $table->string('pincode', 20)->nullable();
            });
            $this->addCustomerColumn('gstin', function (Blueprint $table) {
                $table->string('gstin', 20)->nullable();
            });
            $this->addCustomerColumn('pan', function (Blueprint $table) {
                $table->string('pan', 20)->nullable();
            });
            $this->addCustomerColumn('is_active', function (Blueprint $table) {
                $table->boolean('is_active')->default(true);
            });
            $this->addCustomerColumn('created_at', function (Blueprint $table) {
                $table->timestamp('created_at')->nullable();
            });
            $this->addCustomerColumn('updated_at', function (Blueprint $table) {
                $table->timestamp('updated_at')->nullable();
            });
        }

        if (!Schema::hasColumn('stock_outwards', 'customer_id')) {
            Schema::table('stock_outwards', function (Blueprint $table) {
                $table->unsignedBigInteger('customer_id')->nullable();
                $table->foreign('customer_id')
                    ->references('id')
                    ->on('customers')
                    ->onDelete('set null');
            });
        }

        if (Schema::hasColumn('stock_outwards', 'issued_to')) {
            $recipients = DB::table('stock_outwards')
                ->whereNotNull('issued_to')
                ->where('issued_to', '<>', '')
                ->select('id', 'issued_to')
                ->orderBy('id')
                ->get();

            foreach ($recipients as $recipient) {
                $name = trim($recipient->issued_to);
                if ($name === '') {
                    continue;
                }

                $customer = DB::table('customers')->where('name', $name)->first();
                if (!$customer) {
                    $values = ['name' => $name];
                    if (Schema::hasColumn('customers', 'customer_code')) {
                        $values['customer_code'] = $this->legacyCustomerCode($recipient->id);
                    }
                    if (Schema::hasColumn('customers', 'customer_type')) {
                        $values['customer_type'] = 'Other';
                    }
                    if (Schema::hasColumn('customers', 'is_active')) {
                        $values['is_active'] = true;
                    }
                    if (Schema::hasColumn('customers', 'created_at')) {
                        $values['created_at'] = now();
                    }
                    if (Schema::hasColumn('customers', 'updated_at')) {
                        $values['updated_at'] = now();
                    }

                    $customerId = DB::table('customers')->insertGetId($values);
                } else {
                    $customerId = $customer->id;
                }

                DB::table('stock_outwards')
                    ->where('id', $recipient->id)
                    ->whereNull('customer_id')
                    ->update(['customer_id' => $customerId]);
            }
        }

        if (Schema::hasColumn('customers', 'customer_code')) {
            foreach (DB::table('customers')->whereNull('customer_code')->orderBy('id')->get(['id']) as $customer) {
                DB::table('customers')->where('id', $customer->id)->update([
                    'customer_code' => $this->legacyCustomerCode($customer->id),
                ]);
            }
        }
    }

    public function down()
    {
        if (Schema::hasColumn('stock_outwards', 'customer_id')) {
            Schema::table('stock_outwards', function (Blueprint $table) {
                $table->dropForeign(['customer_id']);
                $table->dropColumn('customer_id');
            });
        }

    }

    private function addCustomerColumn($column, $definition)
    {
        if (!Schema::hasColumn('customers', $column)) {
            Schema::table('customers', $definition);
        }
    }

    private function legacyCustomerCode($id)
    {
        $code = 'CUS-LEGACY-' . str_pad($id, 8, '0', STR_PAD_LEFT);
        $suffix = 1;

        while (DB::table('customers')->where('customer_code', $code)->exists()) {
            $code = 'CUS-LEGACY-' . str_pad($id, 8, '0', STR_PAD_LEFT) . '-' . $suffix;
            $suffix++;
        }

        return $code;
    }
}
