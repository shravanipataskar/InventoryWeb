<?php

namespace Tests\Feature;

use App\Category;
use App\Product;
use App\StockInward;
use App\Supplier;
use App\Unit;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class StockInwardTaxTest extends TestCase
{
    use DatabaseTransactions;

    public function test_create_form_filters_products_by_category()
    {
        $this->actingAs($this->makeUser());
        list($category, $otherCategory, $product) = $this->makeInventory();
        $otherProduct = $this->makeProduct($otherCategory, 'OTHER-' . strtoupper(Str::random(8)));

        $this->get(route('stock-inwards.create'))
            ->assertOk()
            ->assertSee($category->name)
            ->assertSee($otherCategory->name)
            ->assertSee($product->name)
            ->assertSee($otherProduct->name)
            ->assertSee('function filterProducts')
            ->assertSee('category_id');
    }

    public function test_stock_inward_rejects_a_product_from_another_category()
    {
        $this->actingAs($this->makeUser());
        list($category, $otherCategory, $product, $supplier) = $this->makeInventory();
        $otherProduct = $this->makeProduct($otherCategory, 'OTHER-' . strtoupper(Str::random(8)));

        $this->post(route('stock-inwards.store'), $this->inwardPayload(
            $category,
            $otherProduct,
            $supplier
        ))->assertSessionHasErrors('product_id');

        $this->assertSame(0.0, (float) $product->fresh()->current_stock);
        $this->assertSame(0.0, (float) $otherProduct->fresh()->current_stock);
        $this->assertDatabaseMissing('stock_inwards', [
            'product_id' => $otherProduct->id,
            'supplier_id' => $supplier->id,
        ]);
    }

    public function test_stock_inward_recalculates_tax_totals_and_increases_stock()
    {
        $this->actingAs($this->makeUser());
        list($category, $otherCategory, $product, $supplier) = $this->makeInventory();

        $this->post(route('stock-inwards.store'), $this->inwardPayload(
            $category,
            $product,
            $supplier,
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
        ))->assertSessionHasNoErrors()
            ->assertRedirect(route('stock-inwards.index'));

        $inward = StockInward::where('product_id', $product->id)->firstOrFail();
        $this->assertSame(10000.0, (float) $inward->subtotal);
        $this->assertSame(900.0, (float) $inward->sgst_amount);
        $this->assertSame(900.0, (float) $inward->cgst_amount);
        $this->assertSame(9.0, (float) $inward->sgst_rate);
        $this->assertSame(9.0, (float) $inward->cgst_rate);
        $this->assertSame(1800.0, (float) $inward->tax_total);
        $this->assertSame(11800.0, (float) $inward->grand_total);
        $this->assertSame(10000.0, (float) $inward->total_amount);
        $this->assertSame(10.0, (float) $product->fresh()->current_stock);

        $this->get(route('stock-inwards.index'))
            ->assertOk()
            ->assertSee('SUBTOTAL')
            ->assertSee('GRAND TOTAL')
            ->assertSee('11,800.00');
    }

    public function test_stock_inward_requires_valid_positive_quantity_and_tax_rates()
    {
        $this->actingAs($this->makeUser());
        list($category, $otherCategory, $product, $supplier) = $this->makeInventory();
        $payload = $this->inwardPayload($category, $product, $supplier, [
            'quantity' => -1,
            'sgst_rate' => -1,
            'cgst_rate' => 101,
        ]);

        $this->post(route('stock-inwards.store'), $payload)
            ->assertSessionHasErrors(['quantity', 'sgst_rate', 'cgst_rate']);

        $this->post(route('stock-inwards.store'), [])
            ->assertSessionHasErrors([
                'inward_date',
                'category_id',
                'product_id',
                'supplier_id',
                'quantity',
                'purchase_price',
                'sgst_rate',
                'cgst_rate',
            ]);
    }

    public function test_stock_inward_can_be_edited_and_stock_is_reconciled()
    {
        $this->actingAs($this->makeUser());
        list($category, $otherCategory, $product, $supplier) = $this->makeInventory();

        $this->post(route('stock-inwards.store'), $this->inwardPayload(
            $category,
            $product,
            $supplier,
            [
                'quantity' => 10,
                'purchase_price' => 1000,
                'sgst_rate' => 9,
                'cgst_rate' => 9,
            ]
        ))->assertRedirect(route('stock-inwards.index'));

        $inward = StockInward::where('product_id', $product->id)->firstOrFail();
        $this->put(route('stock-inwards.update', $inward->id), [
            'category_id' => $category->id,
            'product_id' => $product->id,
            'supplier_id' => $supplier->id,
            'inward_date' => now()->toDateString(),
            'quantity' => 5,
            'purchase_price' => 1500,
            'sgst_rate' => 10,
            'cgst_rate' => 10,
            'invoice_number' => 'EDIT-100',
            'remarks' => 'Updated inward',
        ])->assertRedirect(route('stock-inwards.index'));

        $updated = $inward->fresh();
        $this->assertSame(5.0, (float) $updated->quantity);
        $this->assertSame(7500.0, (float) $updated->subtotal);
        $this->assertSame(750.0, (float) $updated->sgst_amount);
        $this->assertSame(750.0, (float) $updated->cgst_amount);
        $this->assertSame(1500.0, (float) $updated->tax_total);
        $this->assertSame(9000.0, (float) $updated->grand_total);
        $this->assertSame(5.0, (float) $product->fresh()->current_stock);
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

        return [$category, $otherCategory, $product, $supplier];
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

    private function inwardPayload(Category $category, Product $product, Supplier $supplier, array $overrides = [])
    {
        return array_merge([
            'category_id' => $category->id,
            'product_id' => $product->id,
            'supplier_id' => $supplier->id,
            'inward_date' => now()->toDateString(),
            'quantity' => 5,
            'purchase_price' => 2000,
            'sgst_rate' => 9,
            'cgst_rate' => 9,
        ], $overrides);
    }
}
