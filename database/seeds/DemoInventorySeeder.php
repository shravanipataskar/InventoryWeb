<?php

use App\Category;
use App\Product;
use App\Supplier;
use App\Unit;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DemoInventorySeeder extends Seeder
{
    public function run()
    {
        DB::transaction(function () {
            $this->removeLegacyTestData();

            $category = Category::firstOrCreate(
                ['name' => 'Dairy & Bakery'],
                ['description' => 'Everyday milk, dairy, and bakery products.', 'is_active' => true]
            );
            $litre = Unit::firstOrCreate(
                ['short_name' => 'L'],
                ['name' => 'Litre', 'is_active' => true]
            );
            $pack = Unit::firstOrCreate(
                ['short_name' => 'Pack'],
                ['name' => 'Pack', 'is_active' => true]
            );
            $supplierCode = 'SAMPLE-SUP-001';
            $supplierQuery = Supplier::query();
            if (Schema::hasColumn('suppliers', 'supplier_code')) {
                $supplierQuery->where('supplier_code', $supplierCode);
            } else {
                $supplierQuery->where('name', 'Local Dairy Supplier');
            }
            $supplier = $supplierQuery->first();

            if (!$supplier) {
                $supplierValues = [
                    'name' => 'Local Dairy Supplier',
                    'company_name' => 'Local Dairy Supplier',
                    'is_active' => true,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ];
                if (Schema::hasColumn('suppliers', 'supplier_code')) {
                    $supplierValues['supplier_code'] = $supplierCode;
                }

                $supplierId = DB::table('suppliers')->insertGetId($supplierValues);
                $supplier = Supplier::findOrFail($supplierId);
            }

            $storeValues = [
                'name' => 'Main Store',
                'location' => 'Main shop',
                'is_active' => true,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ];
            if (Schema::hasColumn('stores', 'store_code')) {
                $storeValues['store_code'] = 'SAMPLE-MAIN-STORE';
            }
            DB::table('stores')->updateOrInsert(['code' => 'SAMPLE-MAIN-STORE'], $storeValues);

            $products = [
                [
                    'code' => 'SAMPLE-MILK-001',
                    'name' => 'Milk - 1 Litre',
                    'unit' => $litre,
                    'purchase_price' => 55,
                    'selling_price' => 65,
                    'quantity' => 20,
                ],
                [
                    'code' => 'SAMPLE-CURD-001',
                    'name' => 'Curd - 500 g',
                    'unit' => $pack,
                    'purchase_price' => 30,
                    'selling_price' => 40,
                    'quantity' => 10,
                ],
                [
                    'code' => 'SAMPLE-BREAD-001',
                    'name' => 'Bread - 400 g',
                    'unit' => $pack,
                    'purchase_price' => 35,
                    'selling_price' => 45,
                    'quantity' => 12,
                ],
            ];

            foreach ($products as $item) {
                $product = Product::firstOrCreate(
                    ['product_code' => $item['code']],
                    [
                        'name' => $item['name'],
                        'category_id' => $category->id,
                        'unit_id' => $item['unit']->id,
                        'purchase_price' => $item['purchase_price'],
                        'selling_price' => $item['selling_price'],
                        'minimum_stock' => 5,
                        'reorder_level' => 5,
                        'reorder_quantity' => 10,
                        'description' => 'Simple sample item for exploring the inventory.',
                        'is_active' => true,
                    ]
                );

                if ($product->wasRecentlyCreated) {
                    $quantity = $item['quantity'];
                    $purchasePrice = $item['purchase_price'];

                    $product->stockInwards()->create([
                        'supplier_id' => $supplier->id,
                        'invoice_number' => 'SAMPLE-INV-' . str_replace('SAMPLE-', '', $item['code']),
                        'inward_date' => Carbon::today()->toDateString(),
                        'quantity' => $quantity,
                        'purchase_price' => $purchasePrice,
                        'total_amount' => $quantity * $purchasePrice,
                        'remarks' => 'Initial sample stock.',
                    ]);
                }
            }
        });
    }

    private function removeLegacyTestData()
    {
        $testProductIds = [];
        if (Schema::hasTable('products') && Schema::hasColumn('products', 'product_code')) {
            $testProductIds = DB::table('products')
                ->where('product_code', 'like', 'TEST-SKU-%')
                ->pluck('id')
                ->all();
        }
        $testStoreIds = [];
        if (Schema::hasTable('stores') && Schema::hasColumn('stores', 'code')) {
            $testStoreIds = DB::table('stores')
                ->where('code', 'like', 'TEST-LOC-%')
                ->pluck('id')
                ->all();
        }

        $this->deleteProductMovements('stock_inwards', 'invoice_number', 'TEST-IN-%', $testProductIds);
        $this->deleteProductMovements('stock_outwards', 'reference_number', 'TEST-OUT-%', $testProductIds);
        $this->deleteProductMovements('stock_adjustments', 'reference_number', 'TEST-ADJ-%', $testProductIds);
        $this->deleteProductMovements('stock_transfers', 'transfer_number', 'TEST-TRF-%', $testProductIds);

        if (Schema::hasTable('stock_transactions')) {
            $hasProductId = Schema::hasColumn('stock_transactions', 'product_id');
            $hasStoreId = Schema::hasColumn('stock_transactions', 'store_id');

            if (($hasProductId && $testProductIds) || ($hasStoreId && $testStoreIds)) {
                DB::table('stock_transactions')->where(function ($query) use ($hasProductId, $hasStoreId, $testProductIds, $testStoreIds) {
                    if ($hasProductId && $testProductIds) {
                        $query->whereIn('product_id', $testProductIds);
                    }
                    if ($hasStoreId && $testStoreIds) {
                        $query->orWhereIn('store_id', $testStoreIds);
                    }
                })->delete();
            }
        }

        if (Schema::hasTable('activity_logs')) {
            $hasActorName = Schema::hasColumn('activity_logs', 'actor_name');
            $hasSubject = Schema::hasColumn('activity_logs', 'subject');

            if ($hasActorName || $hasSubject) {
                DB::table('activity_logs')->where(function ($query) use ($hasActorName, $hasSubject) {
                    if ($hasActorName) {
                        $query->where('actor_name', 'Demo Seeder');
                    }
                    if ($hasSubject) {
                        $query->orWhere('subject', 'like', 'TEST-%');
                    }
                })->delete();
            }
        }

        $this->deleteLike('customers', 'customer_code', 'TEST-CUST-%');
        $this->deleteWhereIn('users', 'email', [
            'admin@example.test',
            'manager@example.test',
            'operator@example.test',
            'viewer@example.test',
            'clerk@example.test',
        ]);

        if ($testProductIds) {
            DB::table('products')->whereIn('id', $testProductIds)->delete();
        }

        $this->deleteLike('stores', 'code', 'TEST-LOC-%');
        $this->deleteWhereIn('suppliers', 'email', [
            'supplier1@example.test',
            'supplier2@example.test',
            'supplier3@example.test',
            'supplier4@example.test',
            'supplier5@example.test',
        ]);
        $this->deleteLike('companies', 'code', 'TEST-COMP-%');
        $this->deleteLike('categories', 'name', 'TEST -%');
        $this->deleteWhereIn('units', 'short_name', ['tpc', 'tkg', 'tl', 'tbx', 'tm']);
    }

    private function deleteProductMovements($table, $referenceColumn, $referencePattern, array $productIds)
    {
        if (!Schema::hasTable($table)) {
            return;
        }

        $hasProductId = Schema::hasColumn($table, 'product_id') && $productIds;
        $hasReference = Schema::hasColumn($table, $referenceColumn);

        if (!$hasProductId && !$hasReference) {
            return;
        }

        DB::table($table)->where(function ($query) use ($table, $referenceColumn, $referencePattern, $productIds, $hasProductId, $hasReference) {
            if ($hasProductId) {
                $query->whereIn('product_id', $productIds);
            }
            if ($hasReference) {
                $query->orWhere($referenceColumn, 'like', $referencePattern);
            }
        })->delete();
    }

    private function deleteLike($table, $column, $pattern)
    {
        if (Schema::hasTable($table) && Schema::hasColumn($table, $column)) {
            DB::table($table)->where($column, 'like', $pattern)->delete();
        }
    }

    private function deleteWhereIn($table, $column, array $values)
    {
        if (Schema::hasTable($table) && Schema::hasColumn($table, $column)) {
            DB::table($table)->whereIn($column, $values)->delete();
        }
    }
}
