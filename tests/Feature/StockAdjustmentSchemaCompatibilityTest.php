<?php

namespace Tests\Feature;

use App\Category;
use App\Product;
use App\Unit;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class StockAdjustmentSchemaCompatibilityTest extends TestCase
{
    use DatabaseTransactions;

    public function test_adjustment_saves_with_the_installed_schema_and_updates_store_and_product_stock()
    {
        $this->actingAs(User::create([
            'name' => 'Adjustment Test User',
            'email' => Str::uuid() . '@example.test',
            'password' => Hash::make('test-password'),
        ]));

        $suffix = strtoupper(Str::random(8));
        $category = Category::create([
            'name' => 'Adjustment Category ' . $suffix,
            'is_active' => true,
        ]);
        $unit = Unit::create([
            'name' => 'Adjustment Unit ' . $suffix,
            'short_name' => 'AU',
            'is_active' => true,
        ]);
        $product = Product::create([
            'product_code' => 'ADJ-' . $suffix,
            'name' => 'Adjustment Test Product',
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'purchase_price' => 10,
            'selling_price' => 15,
            'minimum_stock' => 1,
            'current_stock' => 20,
            'is_active' => true,
        ]);
        $product->current_stock = 20;
        $product->save();

        $this->get(route('stock-adjustments.create'))
            ->assertOk()
            ->assertSee('Category')
            ->assertSee('data-category-filter', false)
            ->assertSee('data-category-product', false)
            ->assertSee('data-category="' . $category->id . '"', false);

        $storeId = null;
        if (Schema::hasColumn('stock_adjustments', 'store_id')) {
            $storeId = $this->createStore('Adjustment Location ' . $suffix, $suffix);
            DB::table('stock_transactions')->insert([
                'store_id' => $storeId,
                'product_id' => $product->id,
                'transaction_type' => 'opening',
                'reference_type' => 'test',
                'reference_id' => null,
                'quantity_in' => 20,
                'quantity_out' => 0,
                'balance_quantity' => 20,
                'unit_price' => 10,
                'transaction_date' => now()->toDateString(),
                'remarks' => 'Test opening balance',
                'created_by' => auth()->id(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $response = $this->post(route('stock-adjustments.store'), array_filter([
            'category_id' => $category->id,
            'product_id' => $product->id,
            'store_id' => $storeId,
            'adjustment_type' => 'decrease',
            'quantity' => 5,
            'reason' => 'Count correction',
            'adjustment_date' => now()->toDateString(),
        ], function ($value) {
            return $value !== null;
        }));

        $response->assertSessionHasNoErrors()
            ->assertRedirect(route('stock-adjustments.index'));
        $this->assertSame('15.00', $product->fresh()->current_stock);

        $differentCategory = Category::create([
            'name' => 'Different Adjustment Category ' . $suffix,
            'is_active' => true,
        ]);
        $this->post(route('stock-adjustments.store'), array_filter([
            'category_id' => $differentCategory->id,
            'product_id' => $product->id,
            'store_id' => $storeId,
            'adjustment_type' => 'increase',
            'quantity' => 5,
            'reason' => 'Mismatched category check',
            'adjustment_date' => now()->toDateString(),
        ], function ($value) {
            return $value !== null;
        }))->assertSessionHasErrors('category_id');
        $this->assertSame('15.00', $product->fresh()->current_stock);

        $this->get(route('stock-adjustments.index'))
            ->assertOk()
            ->assertSee('Adjustment Test Product')
            ->assertSee('Count correction');

        if (Schema::hasColumn('stock_adjustments', 'adjustment_number')) {
            $this->assertDatabaseHas('stock_adjustments', [
                'product_id' => $product->id,
                'store_id' => $storeId,
                'type' => 'decrease',
                'quantity' => 5,
                'reason' => 'Count correction',
            ]);
            $this->assertDatabaseHas('stock_transactions', [
                'store_id' => $storeId,
                'product_id' => $product->id,
                'transaction_type' => 'adjustment_out',
                'quantity_out' => 5,
                'balance_quantity' => 15,
            ]);
        } else {
            $this->assertDatabaseHas('stock_adjustments', [
                'product_id' => $product->id,
                'adjustment_quantity' => -5,
                'quantity_before' => 20,
                'quantity_after' => 15,
            ]);
        }
    }

    private function createStore($name, $suffix)
    {
        $values = [
            'name' => $name,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ];
        if (Schema::hasColumn('stores', 'store_code')) {
            $values['store_code'] = 'ADJ-' . $suffix;
        }
        if (Schema::hasColumn('stores', 'code')) {
            $values['code'] = 'ADJ-' . $suffix;
        }

        return DB::table('stores')->insertGetId($values);
    }
}
