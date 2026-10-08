<?php

namespace Tests\Feature;

use App\Category;
use App\Company;
use App\Hall;
use App\Product;
use App\Unit;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class StockValuationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_stock_valuation_shows_current_product_cost_and_read_only_totals()
    {
        list($category, $company, $hall, $product, $zeroStockProduct) = $this->createValuationFixture();
        $this->actingAs($this->makeUser());

        $this->get(route('stock-valuation.index', ['as_of' => now()->toDateString()]))
            ->assertOk()
            ->assertSee('Stock Valuation')
            ->assertSee($product->product_code)
            ->assertSee($category->name)
            ->assertSee($company->name)
            ->assertSee($hall->name)
            ->assertSee('₹60.00')
            ->assertSee('5.00')
            ->assertSee('₹12.00')
            ->assertDontSee('<td>' . $zeroStockProduct->product_code, false);

        $this->assertSame(5.0, (float) $product->fresh()->current_stock);
    }

    public function test_stock_valuation_filters_zero_stock_status_search_and_detail()
    {
        list($category, $company, $hall, $product, $zeroStockProduct) = $this->createValuationFixture();
        $this->actingAs($this->makeUser());

        $this->get(route('stock-valuation.index', [
            'category_id' => $category->id,
            'company_id' => $company->id,
            'location_id' => $hall->id,
            'product_id' => $product->id,
            'stock_status' => 'low_stock',
            'include_zero' => '1',
            'search' => $product->barcode,
        ]))
            ->assertOk()
            ->assertSee($product->product_code)
            ->assertSee('Low Stock')
            ->assertSee('₹60.00')
            ->assertDontSee('<td>' . $zeroStockProduct->product_code, false);

        $this->get(route('stock-valuation.index', ['stock_status' => 'out_of_stock', 'include_zero' => '1']))
            ->assertOk()
            ->assertSee($zeroStockProduct->product_code)
            ->assertSee('Out of Stock');

        $this->get(route('stock-valuation.show', $product->id))
            ->assertOk()
            ->assertSee($product->barcode)
            ->assertSee('Valuation')
            ->assertSee('₹60.00');
    }

    public function test_stock_valuation_exports_summaries_and_print_are_read_only_and_historical_date_is_rejected()
    {
        list($category, $company, $hall, $product) = $this->createValuationFixture();
        $this->actingAs($this->makeUser());
        $filters = [
            'as_of' => now()->toDateString(),
            'category_id' => $category->id,
            'company_id' => $company->id,
            'location_id' => $hall->id,
        ];
        $stockBefore = (float) $product->fresh()->current_stock;

        $details = $this->get(route('stock-valuation.export', array_merge(['format' => 'csv', 'report_type' => 'details'], $filters)));
        $details->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString($product->product_code, $details->streamedContent());

        $categoryExport = $this->get(route('stock-valuation.export', array_merge(['format' => 'excel', 'report_type' => 'category'], $filters)));
        $categoryExport->assertOk()->assertHeader('content-type', 'application/vnd.ms-excel; charset=UTF-8');
        $this->assertStringContainsString($category->name, $categoryExport->streamedContent());

        $locationExport = $this->get(route('stock-valuation.export', array_merge(['format' => 'csv', 'report_type' => 'location'], $filters)));
        $locationExport->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString($hall->name, $locationExport->streamedContent());

        $print = $this->get(route('stock-valuation.export', array_merge(['format' => 'print'], $filters)));
        $print->assertOk();
        $this->assertStringContainsString($product->product_code, $print->streamedContent());
        $this->get(route('stock-valuation.index', ['as_of' => '2026-10-01']))
            ->assertSessionHasErrors('as_of');

        $this->assertSame($stockBefore, (float) $product->fresh()->current_stock);
    }

    private function createValuationFixture()
    {
        $suffix = strtoupper(Str::random(7));
        $category = Category::create(['name' => 'Valuation Category ' . $suffix, 'is_active' => true]);
        $company = Company::create([
            'name' => 'Valuation Company ' . $suffix,
            'code' => 'VAL-' . $suffix,
            'is_active' => true,
        ]);
        $hall = Hall::create(['name' => 'Valuation Hall ' . $suffix, 'is_active' => true]);
        $unit = Unit::create([
            'name' => 'Valuation Unit ' . $suffix,
            'short_name' => 'VU',
            'is_active' => true,
        ]);
        $product = Product::create([
            'product_code' => 'VAL-P-' . $suffix,
            'name' => 'Valuation Product ' . $suffix,
            'barcode' => 'BC-' . $suffix,
            'category_id' => $category->id,
            'company_id' => $company->id,
            'hall_id' => $hall->id,
            'unit_id' => $unit->id,
            'purchase_price' => 12,
            'minimum_stock' => 6,
            'is_active' => true,
        ]);
        $product->current_stock = 5;
        $product->save();
        $zeroStockProduct = Product::create([
            'product_code' => 'VAL-Z-' . $suffix,
            'name' => 'Zero Valuation Product ' . $suffix,
            'category_id' => $category->id,
            'company_id' => $company->id,
            'hall_id' => $hall->id,
            'unit_id' => $unit->id,
            'purchase_price' => 20,
            'minimum_stock' => 0,
            'is_active' => true,
        ]);

        $storeCode = 'VST-' . $suffix;
        $storeValues = [
            'name' => 'Valuation Store ' . $suffix,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ];
        if (DB::getSchemaBuilder()->hasColumn('stores', 'store_code')) {
            $storeValues['store_code'] = $storeCode;
        }
        if (DB::getSchemaBuilder()->hasColumn('stores', 'code')) {
            $storeValues['code'] = $storeCode;
        }
        $storeId = DB::table('stores')->insertGetId($storeValues);
        DB::table('stock_transactions')->insert([
            'store_id' => $storeId,
            'product_id' => $product->id,
            'transaction_type' => 'purchase',
            'reference_type' => 'stock_inward',
            'reference_id' => null,
            'quantity_in' => 5,
            'quantity_out' => 0,
            'balance_quantity' => 5,
            'unit_price' => 12,
            'transaction_date' => '2026-10-07',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$category, $company, $hall, $product, $zeroStockProduct];
    }

    private function makeUser()
    {
        return User::create([
            'name' => 'Valuation Report User',
            'email' => Str::uuid() . '@example.test',
            'password' => Hash::make('test-password'),
        ]);
    }
}
