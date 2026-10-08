<?php

namespace Tests\Feature;

use App\Category;
use App\Product;
use App\Unit;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class InventoryReportsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_reports_use_filtered_current_stock_and_purchase_cost()
    {
        $this->actingAs($this->createReportUser());
        $suffix = strtoupper(Str::random(8));
        $category = Category::create([
            'name' => 'Report Category ' . $suffix,
            'is_active' => true,
        ]);
        $unit = Unit::create([
            'name' => 'Report Unit ' . $suffix,
            'short_name' => 'RU',
            'is_active' => true,
        ]);
        $product = $this->createProduct($category->id, $unit->id, 'Report Laptop ' . $suffix, 'RP-' . $suffix, 7, 4);
        $unrelatedProduct = $this->createProduct($category->id, $unit->id, 'Unrelated Keyboard ' . $suffix, 'RK-' . $suffix, 90, 100);

        $this->withoutExceptionHandling();
        $response = $this->get(route('reports.index', ['product_id' => $product->id]))
            ->assertOk()
            ->assertSee('Inventory Reports')
            ->assertSee('Report Laptop ' . $suffix)
            ->assertSee('Stock Outward')
            ->assertSee('Stock Transfer')
            ->assertSee('Purchase Reports')
            ->assertSee('General Settings')
            ->assertSee('7.00')
            ->assertSee('₹28.00');
        $this->assertSame(1, preg_match('/<button\b(?=[^>]*aria-expanded="true")(?=[^>]*aria-controls="reports-submenu")[^>]*>/s', $response->getContent()));
        $this->assertSame(1, preg_match('/<button\b(?=[^>]*aria-expanded="false")(?=[^>]*aria-controls="stock-submenu")[^>]*>/s', $response->getContent()));
        $response->assertDontSee('<div class="sidebar-caption">MASTER</div>', false)
            ->assertDontSee('<div class="sidebar-caption sidebar-caption-spaced">INVENTORY</div>', false)
            ->assertDontSee('<div class="sidebar-caption sidebar-caption-spaced">REPORTS</div>', false);

        $stockResponse = $this->get(route('current-stock.index'))->assertOk();
        $this->assertSame(1, preg_match('/<button\b(?=[^>]*aria-expanded="true")(?=[^>]*aria-controls="stock-submenu")[^>]*>/s', $stockResponse->getContent()));
        $this->assertSame(1, preg_match('/<button\b(?=[^>]*aria-expanded="false")(?=[^>]*aria-controls="reports-submenu")[^>]*>/s', $stockResponse->getContent()));

        $response = $this->get(route('reports.download', [
            'type' => 'stock-details',
            'format' => 'csv',
            'product_id' => $product->id,
        ]))->assertOk();
        $export = $response->streamedContent();
        $this->assertStringContainsString('Report Laptop ' . $suffix, $export);
        $this->assertStringNotContainsString('Unrelated Keyboard ' . $suffix, $export);

        $valuationExport = $this->get(route('reports.download', [
            'type' => 'stock-valuation',
            'format' => 'csv',
            'product_id' => $product->id,
        ]))->streamedContent();
        $valuationLines = explode("\n", trim($valuationExport));
        $this->assertCount(10, str_getcsv(end($valuationLines)));

        $this->assertNotSame($product->id, $unrelatedProduct->id);
    }

    public function test_detail_tabs_render_and_reorder_links_prefill_purchase_order()
    {
        $this->actingAs($this->createReportUser());
        $suffix = strtoupper(Str::random(8));
        $category = Category::create([
            'name' => 'Reorder Category ' . $suffix,
            'is_active' => true,
        ]);
        $unit = Unit::create([
            'name' => 'Reorder Unit ' . $suffix,
            'short_name' => 'RU',
            'is_active' => true,
        ]);
        $product = $this->createProduct($category->id, $unit->id, 'Reorder Mouse ' . $suffix, 'RM-' . $suffix, 2, 5);
        $product->reorder_level = 5;
        $product->reorder_quantity = 12;
        $product->save();
        $storeId = $this->createStore('Report Store ' . $suffix, 'RPT-' . $suffix);
        DB::table('stock_transactions')->insert(array_intersect_key([
            'store_id' => $storeId,
            'product_id' => $product->id,
            'transaction_type' => 'opening',
            'reference_type' => 'opening_stock',
            'reference_id' => null,
            'reference_number' => 'OPEN-REPORT-' . $suffix,
            'quantity_in' => 2,
            'quantity_out' => 0,
            'balance_quantity' => 2,
            'unit_price' => 5,
            'transaction_date' => now()->toDateString(),
            'remarks' => 'Report test opening',
            'created_by' => auth()->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ], array_flip(Schema::getColumnListing('stock_transactions'))));

        $this->get(route('reports.index', ['tab' => 'stock-valuation', 'product_id' => $product->id]))
            ->assertOk()
            ->assertSee('Stock Valuation')
            ->assertSee('Reorder Mouse ' . $suffix);
        $this->get(route('reports.index', ['tab' => 'low-reorder', 'product_id' => $product->id]))
            ->assertOk()
            ->assertSee('Create PO')
            ->assertSee('Reorder Mouse ' . $suffix);
        $this->get(route('current-stock.index', [
            'stock' => 'low',
            'search' => 'RM-' . $suffix,
        ]))
            ->assertOk()
            ->assertSee('Reorder Mouse ' . $suffix);
        $this->get(route('reports.index', [
            'tab' => 'stock-valuation',
            'product_id' => $product->id,
            'location_id' => $storeId,
        ]))
            ->assertOk()
            ->assertSee('2.00')
            ->assertSee('Report Store ' . $suffix);
        $this->get(route('reports.index', ['tab' => 'stock-movement', 'product_id' => $product->id]))
            ->assertOk()
            ->assertSee('OPEN-REPORT-' . $suffix)
            ->assertSee('Report test opening');
        foreach (['purchase-inward', 'outward', 'transfer', 'adjustment'] as $tab) {
            $this->get(route('reports.index', ['tab' => $tab, 'product_id' => $product->id]))
                ->assertOk();
        }
        $this->get(route('purchase-orders.create', [
            'product_id' => $product->id,
            'ordered_quantity' => 12,
        ]))
            ->assertOk()
            ->assertSee('value="12"', false)
            ->assertSee('Reorder Mouse ' . $suffix);
    }

    public function test_as_of_date_reconstructs_quantity_from_later_inward_activity()
    {
        $this->actingAs($this->createReportUser());
        $suffix = strtoupper(Str::random(8));
        $category = Category::create([
            'name' => 'Historical Report Category ' . $suffix,
            'is_active' => true,
        ]);
        $unit = Unit::create([
            'name' => 'Historical Report Unit ' . $suffix,
            'short_name' => 'HRU',
            'is_active' => true,
        ]);
        $product = $this->createProduct($category->id, $unit->id, 'Historical Report Product ' . $suffix, 'HR-' . $suffix, 10, 5);
        $supplierId = DB::table('suppliers')->insertGetId(array_intersect_key([
            'name' => 'Historical Supplier ' . $suffix,
            'supplier_code' => 'HRS-' . $suffix,
            'company_name' => null,
            'email' => null,
            'phone' => null,
            'address' => null,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ], array_flip(Schema::getColumnListing('suppliers'))));
        DB::table('stock_inwards')->insert(array_intersect_key([
            'product_id' => $product->id,
            'supplier_id' => $supplierId,
            'invoice_number' => 'HIST-' . $suffix,
            'inward_number' => 'INW-HIST-' . $suffix,
            'inward_date' => now()->toDateString(),
            'quantity' => 3,
            'received_quantity' => 3,
            'rejected_quantity' => 0,
            'purchase_price' => 5,
            'total_amount' => 15,
            'status' => 'posted',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ], array_flip(Schema::getColumnListing('stock_inwards'))));
        $openingStoreId = $this->createStore('Historical Store ' . $suffix, 'HST-' . $suffix);
        DB::table('stock_transactions')->insert(array_intersect_key([
            'store_id' => $openingStoreId,
            'product_id' => $product->id,
            'transaction_type' => 'opening',
            'reference_type' => 'opening_stock',
            'reference_id' => null,
            'reference_number' => 'OPEN-HIST-' . $suffix,
            'quantity_in' => 2,
            'quantity_out' => 0,
            'balance_quantity' => 2,
            'unit_price' => 5,
            'transaction_date' => now()->toDateString(),
            'remarks' => 'Later opening balance',
            'created_by' => auth()->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ], array_flip(Schema::getColumnListing('stock_transactions'))));

        $asOf = now()->subDay()->toDateString();
        $this->get(route('reports.index', [
            'product_id' => $product->id,
            'date_from' => $asOf,
            'date_to' => now()->toDateString(),
            'as_of_date' => $asOf,
        ]))
            ->assertOk()
            ->assertSee('5.00')
            ->assertSee('₹25.00')
            ->assertSee('3.00');
    }

    private function createReportUser()
    {
        return User::create([
            'name' => 'Inventory Report Test User',
            'email' => Str::uuid() . '@example.test',
            'password' => Hash::make('test-password'),
        ]);
    }

    private function createProduct($categoryId, $unitId, $name, $code, $quantity, $cost)
    {
        $product = Product::create([
            'product_code' => $code,
            'name' => $name,
            'category_id' => $categoryId,
            'unit_id' => $unitId,
            'purchase_price' => $cost,
            'selling_price' => $cost + 2,
            'minimum_stock' => 1,
            'is_active' => true,
        ]);
        $product->current_stock = $quantity;
        $product->save();

        return $product;
    }

    private function createStore($name, $code)
    {
        return DB::table('stores')->insertGetId(array_intersect_key([
            'store_code' => $code,
            'name' => $name,
            'code' => $code,
            'location' => null,
            'company_id' => null,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ], array_flip(Schema::getColumnListing('stores'))));
    }
}
