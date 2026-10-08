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

    public function test_multiple_opening_stock_products_post_as_one_setup_event()
    {
        $user = User::create([
            'name' => 'Opening Stock User',
            'email' => Str::uuid() . '@example.test',
            'password' => Hash::make('test-password'),
        ]);
        $this->actingAs($user);
        $this->get(route('opening-stock.create'))
            ->assertOk()
            ->assertSee('Opening Stock Items')
            ->assertSee('Unit Purchase Cost')
            ->assertSee('Stock Preview');

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
        $secondProduct = Product::create([
            'product_code' => 'INIT2-' . $suffix,
            'name' => 'Second Opening Product ' . $suffix,
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'purchase_price' => 20,
            'selling_price' => 30,
            'minimum_stock' => 1,
            'current_stock' => 0,
            'is_active' => true,
        ]);
        $storeId = $this->createStore('Initial Store ' . $suffix, $suffix);

        $response = $this->post(route('opening-stock.store'), [
            'store_id' => $storeId,
            'transaction_date' => now()->toDateString(),
            'items' => [
                [
                    'category_id' => $category->id,
                    'product_id' => $product->id,
                    'quantity' => 10,
                    'unit_purchase_cost' => 55000,
                    'remarks' => 'Initial inventory setup',
                ],
                [
                    'category_id' => $category->id,
                    'product_id' => $secondProduct->id,
                    'quantity' => 2,
                    'unit_purchase_cost' => 20,
                ],
            ],
        ]);

        $response->assertSessionHasNoErrors()
            ->assertRedirect(route('opening-stock.index'));

        $this->assertEquals(10, (float) $product->fresh()->current_stock);
        $this->assertEquals(10, (float) $product->fresh()->opening_stock);
        $this->assertEquals(2, (float) $secondProduct->fresh()->current_stock);
        $this->assertEquals(2, (float) $secondProduct->fresh()->opening_stock);
        $ledgerEntry = DB::table('stock_transactions')->where('product_id', $product->id)->first();
        $this->assertNotNull($ledgerEntry);
        $header = DB::table('opening_stock_headers')->where('id', $ledgerEntry->reference_id)->first();
        $this->assertNotNull($header);
        $this->assertStringStartsWith('OPN-' . date('Ymd') . '-', $header->opening_number);
        $this->assertEquals(55000, (float) $ledgerEntry->unit_price);
        $this->assertSame((string) $user->id, (string) $ledgerEntry->created_by);
        $this->assertEquals(550000, (float) $ledgerEntry->quantity_in * (float) $ledgerEntry->unit_price);
        $this->assertSame(1, DB::table('opening_stock_headers')->count());
        $this->assertSame(2, DB::table('opening_stock_items')->count());
        $this->assertSame(2, DB::table('stock_transactions')->where('reference_id', $header->id)->count());
        $this->get(route('opening-stock.index'))
            ->assertOk()
            ->assertSee('Opening Stock')
            ->assertSee($header->opening_number)
            ->assertSee('550,040.00')
            ->assertSee($user->name)
            ->assertSee('CREATED AT');
        $this->get(route('stock-movement.index'))
            ->assertOk()
            ->assertSee('Opening Stock')
            ->assertSee($header->opening_number);
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
                'store_id' => $storeId,
                'transaction_date' => now()->toDateString(),
                'items' => [
                    [
                        'category_id' => $category->id,
                        'product_id' => $product->id,
                        'quantity' => 10,
                        'unit_purchase_cost' => 55000,
                    ],
                ],
            ])
            ->assertSessionHasErrors('items');

        $this->assertEquals(10, (float) $product->fresh()->current_stock);
        $this->assertSame(2, DB::table('stock_transactions')->whereIn('product_id', [$product->id, $secondProduct->id])->count());
        $this->assertSame(1, DB::table('opening_stock_headers')->count());
    }

    public function test_duplicate_rows_and_invalid_serial_tracking_do_not_post_partial_stock()
    {
        $user = User::create([
            'name' => 'Opening Stock User',
            'email' => Str::uuid() . '@example.test',
            'password' => Hash::make('test-password'),
        ]);
        $this->actingAs($user);
        $suffix = strtoupper(Str::random(8));
        $category = Category::create(['name' => 'Tracked Category ' . $suffix, 'is_active' => true]);
        $unit = Unit::create(['name' => 'Tracked Unit ' . $suffix, 'short_name' => 'TU', 'is_active' => true]);
        $product = Product::create([
            'product_code' => 'TRACK-' . $suffix,
            'name' => 'Tracked Product ' . $suffix,
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'purchase_price' => 10,
            'minimum_stock' => 0,
            'current_stock' => 0,
            'track_batch' => true,
            'track_serial' => true,
            'is_active' => true,
        ]);
        $storeId = $this->createStore('Tracked Store ' . $suffix, $suffix);

        $this->from(route('opening-stock.create'))
            ->post(route('opening-stock.store'), [
                'store_id' => $storeId,
                'transaction_date' => now()->toDateString(),
                'items' => [
                    [
                        'category_id' => $category->id,
                        'product_id' => $product->id,
                        'quantity' => 2,
                        'unit_purchase_cost' => 10,
                        'batch_lot' => 'LOT-A',
                        'serial_numbers' => "SERIAL-1\nSERIAL-1",
                    ],
                ],
            ])
            ->assertSessionHasErrors('items.0.serial_numbers');

        $this->assertSame(0, DB::table('opening_stock_headers')->count());
        $this->assertSame(0, DB::table('stock_transactions')->where('product_id', $product->id)->count());
        $this->assertEquals(0, (float) $product->fresh()->current_stock);

        $ordinaryProduct = Product::create([
            'product_code' => 'PLAIN-' . $suffix,
            'name' => 'Non-batch Product ' . $suffix,
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'purchase_price' => 10,
            'minimum_stock' => 0,
            'is_active' => true,
        ]);
        $this->from(route('opening-stock.create'))
            ->post(route('opening-stock.store'), [
                'store_id' => $storeId,
                'transaction_date' => now()->toDateString(),
                'items' => [
                    [
                        'category_id' => $category->id,
                        'product_id' => $ordinaryProduct->id,
                        'quantity' => 1,
                        'unit_purchase_cost' => 10,
                    ],
                    [
                        'category_id' => $category->id,
                        'product_id' => $ordinaryProduct->id,
                        'quantity' => 1,
                        'unit_purchase_cost' => 10,
                    ],
                ],
            ])
            ->assertSessionHasErrors('items.1.product_id');
        $this->assertSame(0, DB::table('opening_stock_headers')->count());
        $this->assertSame(0, DB::table('stock_transactions')->count());
    }

    public function test_batch_and_serial_tracked_product_posts_distinct_batches_together()
    {
        $user = User::create([
            'name' => 'Tracked Opening User',
            'email' => Str::uuid() . '@example.test',
            'password' => Hash::make('test-password'),
        ]);
        $this->actingAs($user);
        $suffix = strtoupper(Str::random(8));
        $category = Category::create(['name' => 'Batch Category ' . $suffix, 'is_active' => true]);
        $unit = Unit::create(['name' => 'Batch Unit ' . $suffix, 'short_name' => 'BU', 'is_active' => true]);
        $product = Product::create([
            'product_code' => 'BATCH-' . $suffix,
            'name' => 'Batch Product ' . $suffix,
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'purchase_price' => 15,
            'minimum_stock' => 0,
            'current_stock' => 0,
            'track_batch' => true,
            'track_serial' => true,
            'is_active' => true,
        ]);
        $storeId = $this->createStore('Batch Store ' . $suffix, $suffix);

        $this->post(route('opening-stock.store'), [
            'store_id' => $storeId,
            'transaction_date' => now()->toDateString(),
            'items' => [
                [
                    'category_id' => $category->id,
                    'product_id' => $product->id,
                    'quantity' => 2,
                    'unit_purchase_cost' => 15,
                    'batch_lot' => 'LOT-A',
                    'serial_numbers' => "SERIAL-A1\nSERIAL-A2",
                ],
                [
                    'category_id' => $category->id,
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'unit_purchase_cost' => 20,
                    'batch_lot' => 'LOT-B',
                    'serial_numbers' => 'SERIAL-B1',
                ],
            ],
        ])->assertSessionHasNoErrors()
            ->assertRedirect(route('opening-stock.index'));

        $this->assertSame(2, DB::table('opening_stock_items')->where('product_id', $product->id)->count());
        $this->assertSame(3, DB::table('opening_stock_serials')->where('product_id', $product->id)->count());
        $this->assertSame(1, DB::table('stock_transactions')->where('product_id', $product->id)->count());
        $this->assertEquals(3, (float) DB::table('stock_transactions')->where('product_id', $product->id)->value('quantity_in'));
        $this->assertEquals(50, (float) DB::table('opening_stock_items')->where('product_id', $product->id)->sum('opening_value'));
        $this->assertEquals(3, (float) $product->fresh()->current_stock);
    }

    public function test_existing_current_stock_requires_confirmation_and_is_added_not_overwritten()
    {
        $user = User::create([
            'name' => 'Existing Stock User',
            'email' => Str::uuid() . '@example.test',
            'password' => Hash::make('test-password'),
        ]);
        $this->actingAs($user);
        $suffix = strtoupper(Str::random(8));
        $category = Category::create(['name' => 'Existing Category ' . $suffix, 'is_active' => true]);
        $unit = Unit::create(['name' => 'Existing Unit ' . $suffix, 'short_name' => 'EU', 'is_active' => true]);
        $product = Product::create([
            'product_code' => 'EXIST-' . $suffix,
            'name' => 'Existing Stock Product ' . $suffix,
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'purchase_price' => 10,
            'minimum_stock' => 0,
            'is_active' => true,
        ]);
        $product->current_stock = 5;
        $product->save();
        $storeId = $this->createStore('Existing Store ' . $suffix, $suffix);
        $payload = [
            'store_id' => $storeId,
            'transaction_date' => now()->toDateString(),
            'items' => [[
                'category_id' => $category->id,
                'product_id' => $product->id,
                'quantity' => 2,
                'unit_purchase_cost' => 10,
            ]],
        ];

        $this->from(route('opening-stock.create'))
            ->post(route('opening-stock.store'), $payload)
            ->assertSessionHasErrors('confirm_existing_stock');
        $this->assertSame(0, DB::table('opening_stock_headers')->count());

        $payload['confirm_existing_stock'] = 1;
        $this->post(route('opening-stock.store'), $payload)
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('opening-stock.index'));

        $this->assertEquals(7, (float) $product->fresh()->current_stock);
        $this->assertEquals(2, (float) $product->fresh()->opening_stock);
        $this->assertSame(1, DB::table('opening_stock_headers')->count());
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
