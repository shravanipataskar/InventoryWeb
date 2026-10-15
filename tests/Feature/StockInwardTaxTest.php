<?php

namespace Tests\Feature;

use App\Category;
use App\Product;
use App\Supplier;
use App\Unit;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Http\Middleware\VerifyCsrfToken;
use Tests\TestCase;

class StockInwardTaxTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(VerifyCsrfToken::class);
    }

    public function test_new_inward_routes_to_purchase_order_receiving()
    {
        $this->actingAs($this->makeUser());

        $this->get(route('stock-inwards.create'))
            ->assertRedirect(route('purchase-orders.index'));
    }

    public function test_legacy_direct_stock_inward_creation_is_forbidden()
    {
        $this->actingAs($this->makeUser());
        list($category, $otherCategory, $product, $supplier, $store) = $this->makeInventory();
        $otherProduct = $this->makeProduct($otherCategory, 'OTHER-' . strtoupper(Str::random(8)));

        $this->post(route('stock-inwards.store'), $this->inwardPayload(
            $category,
            $otherProduct,
            $supplier,
            $store
        ))->assertForbidden();

        $this->assertSame(0.0, (float) $product->fresh()->current_stock);
        $this->assertSame(0.0, (float) $otherProduct->fresh()->current_stock);
        $this->assertDatabaseMissing('stock_inwards', [
            'product_id' => $otherProduct->id,
            'supplier_id' => $supplier->id,
        ]);
    }

    public function test_legacy_direct_inward_post_cannot_bypass_purchase_receiving()
    {
        $this->actingAs($this->makeUser());
        list($category, $otherCategory, $product, $supplier, $store) = $this->makeInventory();

        $this->post(route('stock-inwards.store'), $this->inwardPayload(
            $category,
            $product,
            $supplier,
            $store,
            [
                'quantity' => 10,
                'purchase_price' => 1000,
                'sgst_rate' => 9,
                'cgst_rate' => 9,
                'subtotal' => 1,
                'sgst_amount' => 1,
                'cgst_amount' => 1,
                'tax_total' => 1,
                'grand_total' => 1,
            ]
        ))->assertForbidden();
        $this->assertSame(0.0, (float) $product->fresh()->current_stock);
        $this->assertDatabaseMissing('stock_inwards', ['product_id' => $product->id]);
    }

    public function test_legacy_direct_stock_inward_endpoint_does_not_bypass_grn_validation()
    {
        $this->actingAs($this->makeUser());
        list($category, $otherCategory, $product, $supplier, $store) = $this->makeInventory();
        $payload = $this->inwardPayload($category, $product, $supplier, $store, [
            'quantity' => -1,
            'sgst_rate' => -1,
            'cgst_rate' => 101,
        ]);

        $this->post(route('stock-inwards.store'), $payload)
            ->assertForbidden();

        $this->post(route('stock-inwards.store'), [])
            ->assertForbidden();
    }

    public function test_legacy_direct_inward_post_cannot_create_duplicate_purchase_or_receipt()
    {
        $this->actingAs($this->makeUser());
        list($category, $otherCategory, $product, $supplier, $store) = $this->makeInventory();

        $this->post(route('stock-inwards.store'), $this->inwardPayload(
            $category,
            $product,
            $supplier,
            $store,
            [
                'quantity' => 10,
                'purchase_price' => 1000,
                'sgst_rate' => 9,
                'cgst_rate' => 9,
            ]
        ))->assertForbidden();
        $this->assertSame(0.0, (float) $product->fresh()->current_stock);
        $this->assertDatabaseMissing('purchase_orders', ['supplier_id' => $supplier->id]);
        $this->assertDatabaseMissing('goods_receipts', ['supplier_id' => $supplier->id]);
    }

    private function makeUser()
    {
        $user = User::create([
            'name' => 'Stock Inward Tax Test User',
            'email' => Str::uuid() . '@example.test',
            'password' => Hash::make('test-password'),
        ]);
        $user->role = 'admin';
        $user->save();

        return $user;
    }

    private function makeInventory()
    {
        $suffix = strtoupper(Str::random(8));
        $category = Category::create([
            'name' => 'Inward Category ' . $suffix,
            'is_active' => true,
        ]);
        $otherCategory = Category::create([
            'name' => 'Other Inward Category ' . $suffix,
            'is_active' => true,
        ]);
        $unit = Unit::create([
            'name' => 'Inward Unit ' . $suffix,
            'short_name' => 'IU',
            'is_active' => true,
        ]);
        $product = $this->makeProduct($category, 'IN-' . $suffix, $unit);
        $supplier = Supplier::create([
            'supplier_code' => 'SUP-' . $suffix,
            'name' => 'Inward Supplier ' . $suffix,
            'is_active' => true,
        ]);

        $storeCode = 'INW-' . $suffix;
        $storeValues = [
            'name' => 'Inward Store ' . $suffix,
            'code' => $storeCode,
            'location' => 'Test',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ];
        if (DB::getSchemaBuilder()->hasColumn('stores', 'store_code')) {
            $storeValues['store_code'] = $storeCode;
        }
        $store = DB::table('stores')->insertGetId($storeValues);

        return [$category, $otherCategory, $product, $supplier, $store];
    }

    private function makeProduct(Category $category, $code, Unit $unit = null)
    {
        if (!$unit) {
            $unit = Unit::create([
                'name' => 'Other Inward Unit ' . strtoupper(Str::random(8)),
                'short_name' => 'OIU',
                'is_active' => true,
            ]);
        }

        $product = Product::create([
            'product_code' => $code,
            'name' => 'Inward Test Product ' . $code,
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'purchase_price' => 1000,
            'selling_price' => 1200,
            'minimum_stock' => 1,
            'is_active' => true,
        ]);
        $product->current_stock = 0;
        $product->save();

        return $product;
    }

    private function inwardPayload(Category $category, Product $product, Supplier $supplier, $storeId, array $overrides = [])
    {
        return array_merge([
            'category_id' => $category->id,
            'product_id' => $product->id,
            'supplier_id' => $supplier->id,
            'store_id' => $storeId,
            'inward_date' => now()->toDateString(),
            'quantity' => 5,
            'purchase_price' => 2000,
            'sgst_rate' => 9,
            'cgst_rate' => 9,
        ], $overrides);
    }
}
