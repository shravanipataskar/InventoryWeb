<?php

namespace Tests\Feature;

use App\Category;
use App\Product;
use App\Unit;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class OpeningStockSetupTest extends TestCase
{
    use DatabaseTransactions;

    public function test_initial_stock_is_kept_as_a_single_setup_event()
    {
        $user = User::create([
            'name' => 'Opening Stock User',
            'email' => Str::uuid() . '@example.test',
            'password' => Hash::make('test-password'),
        ]);
        $this->actingAs($user);
        $this->get(route('opening-stock.create'))
            ->assertOk()
            ->assertSee('Opening Stock Setup')
            ->assertSee('Unit purchase rate')
            ->assertSee('Opening value');

        $suffix = strtoupper(Str::random(8));
        $category = Category::create([
            'name' => 'Opening Category ' . $suffix,
            'is_active' => true,
        ]);
        $unit = Unit::create([
            'name' => 'Opening Unit ' . $suffix,
            'short_name' => 'OU',
            'is_active' => true,
        ]);
        $product = Product::create([
            'product_code' => 'INIT-' . $suffix,
            'name' => 'Initial Stock Product ' . $suffix,
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'purchase_price' => 25,
            'selling_price' => 30,
            'minimum_stock' => 1,
            'current_stock' => 0,
            'is_active' => true,
        ]);
        $storeId = $this->createStore('Initial Store ' . $suffix, $suffix);

        $response = $this->post(route('opening-stock.store'), [
            'category_id' => $category->id,
            'product_id' => $product->id,
            'store_id' => $storeId,
            'quantity' => 10,
            'unit_purchase_rate' => 55000,
            'transaction_date' => now()->toDateString(),
            'remarks' => 'Initial inventory setup',
        ]);

        $response->assertSessionHasNoErrors()
            ->assertRedirect(route('opening-stock.index'));

        $this->assertSame('10.00', $product->fresh()->current_stock);
        $this->assertSame('10.00', $product->fresh()->opening_stock);
        $ledgerEntry = DB::table('stock_transactions')->where('product_id', $product->id)->first();
        $this->assertNotNull($ledgerEntry);
        $this->assertStringStartsWith('OPEN-', $ledgerEntry->reference_number);
        $this->assertSame('55000.00', $ledgerEntry->unit_price);
        $this->assertSame((string) $user->id, (string) $ledgerEntry->created_by);
        $this->assertEquals(550000, (float) $ledgerEntry->quantity_in * (float) $ledgerEntry->unit_price);
        $this->get(route('opening-stock.index'))
            ->assertOk()
            ->assertSee('Opening Stock')
            ->assertSee($ledgerEntry->reference_number)
            ->assertSee('550,000.00')
            ->assertSee($user->name)
            ->assertSee('CREATED AT');
        $this->assertDatabaseHas('stock_transactions', [
            'product_id' => $product->id,
            'store_id' => $storeId,
            'transaction_type' => 'opening',
            'reference_type' => 'opening_stock',
            'quantity_in' => 10,
            'quantity_out' => 0,
        ]);

        $this->from(route('opening-stock.create'))
            ->post(route('opening-stock.store'), [
                'category_id' => $category->id,
                'product_id' => $product->id,
                'store_id' => $storeId,
                'quantity' => 10,
                'unit_purchase_rate' => 55000,
                'transaction_date' => now()->toDateString(),
                'remarks' => 'Second attempt',
            ])
            ->assertSessionHasErrors('product_id');

        $this->assertSame('10.00', $product->fresh()->current_stock);
        $this->assertSame(1, DB::table('stock_transactions')->where('product_id', $product->id)->count());
    }

    private function createStore(string $name, string $suffix): int
    {
        $values = [
            'name' => $name,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if (DB::getSchemaBuilder()->hasColumn('stores', 'store_code')) {
            $values['store_code'] = 'INIT-' . $suffix;
        }

        if (DB::getSchemaBuilder()->hasColumn('stores', 'code')) {
            $values['code'] = 'INIT-' . $suffix;
        }

        return DB::table('stores')->insertGetId($values);
    }
}
