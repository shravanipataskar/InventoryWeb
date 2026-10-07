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

class StockTransferSchemaCompatibilityTest extends TestCase
{
    use DatabaseTransactions;

    public function test_stock_transfer_saves_using_the_installed_transfer_schema()
    {
        $this->actingAs(User::create([
            'name' => 'Stock Transfer Test User',
            'email' => Str::uuid() . '@example.test',
            'password' => Hash::make('test-password'),
        ]));

        $suffix = strtoupper(Str::random(8));
        $category = Category::create([
            'name' => 'Transfer Category ' . $suffix,
            'is_active' => true,
        ]);
        $unit = Unit::create([
            'name' => 'Transfer Unit ' . $suffix,
            'short_name' => 'TU',
            'is_active' => true,
        ]);
        $product = Product::create([
            'product_code' => 'TRF-' . $suffix,
            'name' => 'Transfer Test Product',
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'purchase_price' => 10,
            'selling_price' => 15,
            'minimum_stock' => 1,
            'current_stock' => 50,
            'is_active' => true,
        ]);
        $product->current_stock = 50;
        $product->save();

        $fromName = 'Transfer From ' . $suffix;
        $toName = 'Transfer To ' . $suffix;
        $fromId = $this->createStore($fromName, 'FROM-' . $suffix);
        $toId = $this->createStore($toName, 'TO-' . $suffix);

        $response = $this->post(route('stock-transfers.store'), [
            'product_id' => $product->id,
            'from_location' => $fromName,
            'to_location' => $toName,
            'quantity' => 5,
            'transfer_date' => now()->toDateString(),
            'remarks' => 'Transfer compatibility test',
        ]);
        $response->assertSessionHasNoErrors()
            ->assertRedirect(route('stock-transfers.index'));

        $this->assertSame('50.00', $product->fresh()->current_stock);
        $this->get(route('stock-transfers.index'))
            ->assertOk()
            ->assertSee('Transfer Test Product')
            ->assertSee($fromName)
            ->assertSee($toName);

        if (Schema::hasColumn('stock_transfers', 'from_store_id')) {
            $transfer = DB::table('stock_transfers')
                ->where('from_store_id', $fromId)
                ->where('to_store_id', $toId)
                ->first();
            $this->assertNotNull($transfer);
            $this->assertSame('completed', $transfer->status);
            $this->assertDatabaseHas('stock_transfer_items', [
                'stock_transfer_id' => $transfer->id,
                'product_id' => $product->id,
                'quantity' => 5,
            ]);
        } else {
            $this->assertDatabaseHas('stock_transfers', [
                'product_id' => $product->id,
                'from_location' => $fromName,
                'to_location' => $toName,
                'quantity' => 5,
            ]);
        }
    }

    private function createStore($name, $code)
    {
        $values = [
            'name' => $name,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('stores', 'store_code')) {
            $values['store_code'] = $code;
        }
        if (Schema::hasColumn('stores', 'code')) {
            $values['code'] = $code;
        }

        return DB::table('stores')->insertGetId($values);
    }
}
