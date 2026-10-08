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

    public function test_multi_item_transfer_is_atomic_and_preserves_total_location_stock()
    {
        $this->actingAs(User::create([
            'name' => 'Multi Transfer Test User',
            'email' => Str::uuid() . '@example.test',
            'password' => Hash::make('test-password'),
        ]));

        $suffix = strtoupper(Str::random(8));
        $unit = Unit::create([
            'name' => 'Multi Transfer Unit ' . $suffix,
            'short_name' => 'MTU',
            'is_active' => true,
        ]);
        $products = [];
        foreach (['Laptop', 'Mouse'] as $name) {
            $category = Category::create([
                'name' => 'Multi Transfer ' . $name . ' ' . $suffix,
                'is_active' => true,
            ]);
            $product = Product::create([
                'product_code' => 'MT-' . strtoupper($name) . '-' . $suffix,
                'name' => 'Multi Transfer ' . $name,
                'category_id' => $category->id,
                'unit_id' => $unit->id,
                'purchase_price' => 10,
                'selling_price' => 15,
                'minimum_stock' => 1,
                'current_stock' => $name === 'Laptop' ? 10 : 5,
                'is_active' => true,
            ]);
            $products[] = ['product' => $product, 'category' => $category];
        }

        $fromName = 'Multi From ' . $suffix;
        $toName = 'Multi To ' . $suffix;
        $fromId = $this->createStore($fromName, 'MF-' . $suffix);
        $toId = $this->createStore($toName, 'MT-' . $suffix);
        foreach ($products as $index => $row) {
            $this->seedLocationBalance($row['product']->id, $fromId, $index === 0 ? 10 : 5, 'MULTI-' . $suffix . '-' . $index);
        }

        $items = [
            [
                'category_id' => $products[0]['category']->id,
                'product_id' => $products[0]['product']->id,
                'quantity' => 3,
                'remarks' => 'For Hall A',
            ],
            [
                'category_id' => $products[1]['category']->id,
                'product_id' => $products[1]['product']->id,
                'quantity' => 2,
                'remarks' => 'Replenishment',
            ],
        ];
        $submissionKey = (string) Str::uuid();

        $this->post(route('stock-transfers.store'), [
            'from_location' => $fromName,
            'to_location' => $toName,
            'transfer_date' => now()->toDateString(),
            'transfer_reason' => 'Stock Replenishment',
            'submission_key' => $submissionKey,
            'items' => $items,
        ])->assertSessionHasNoErrors()
            ->assertRedirect(route('stock-transfers.index'));

        $transferId = DB::table('stock_transfers')
            ->where('from_store_id', $fromId)
            ->where('to_store_id', $toId)
            ->value('id');
        $this->assertNotNull($transferId);
        $this->get(route('stock-transfers.show', $transferId))
            ->assertOk()
            ->assertSee('Transfer Information')
            ->assertSee('Stock Preview')
            ->assertSee('Multi Transfer Laptop')
            ->assertSee('Multi Transfer Mouse');
        $this->get(route('stock-movement.index'))
            ->assertOk()
            ->assertSee('Transfer Out')
            ->assertSee('Transfer In')
            ->assertSee($fromName)
            ->assertSee($toName);
        $this->assertSame(4, DB::table('stock_transactions')
            ->where('reference_type', 'stock_transfer')
            ->where('reference_id', $transferId)
            ->count());
        $this->post(route('stock-transfers.store'), [
            'from_location' => $fromName,
            'to_location' => $toName,
            'transfer_date' => now()->toDateString(),
            'transfer_reason' => 'Stock Replenishment',
            'submission_key' => $submissionKey,
            'items' => $items,
        ])->assertSessionHasErrors('submission_key');

        foreach ($products as $index => $row) {
            $expectedFrom = $index === 0 ? 7 : 3;
            $expectedTo = $index === 0 ? 3 : 2;
            $this->assertSame((float) $expectedFrom, $this->locationBalance($row['product']->id, $fromId));
            $this->assertSame((float) $expectedTo, $this->locationBalance($row['product']->id, $toId));
            $this->assertSame((float) ($index === 0 ? 10 : 5), (float) DB::table('stock_transactions')
                ->where('product_id', $row['product']->id)
                ->sum(DB::raw('quantity_in - quantity_out')));
        }

        $transfersBeforeInvalidAttempt = DB::table('stock_transfers')->count();
        $invalidItems = $items;
        $invalidItems[0]['quantity'] = 1;
        $invalidItems[1]['quantity'] = 20;
        $this->post(route('stock-transfers.store'), [
            'from_location' => $fromName,
            'to_location' => $toName,
            'transfer_date' => now()->toDateString(),
            'transfer_reason' => 'Stock Replenishment',
            'items' => $invalidItems,
        ])->assertSessionHasErrors('items.1.quantity');

        $this->assertSame($transfersBeforeInvalidAttempt, DB::table('stock_transfers')->count());
        foreach ($products as $index => $row) {
            $this->assertSame((float) ($index === 0 ? 7 : 3), $this->locationBalance($row['product']->id, $fromId));
            $this->assertSame((float) ($index === 0 ? 3 : 2), $this->locationBalance($row['product']->id, $toId));
        }
    }

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
        DB::table('stock_transactions')->insert(array_intersect_key([
            'store_id' => $fromId,
            'product_id' => $product->id,
            'transaction_type' => 'opening',
            'reference_type' => 'opening_stock',
            'reference_id' => null,
            'reference_number' => 'OPEN-' . $suffix,
            'quantity_in' => 50,
            'quantity_out' => 0,
            'balance_quantity' => 50,
            'unit_price' => 10,
            'transaction_date' => now()->toDateString(),
            'remarks' => 'Stock transfer test seed',
            'created_by' => auth()->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ], array_flip(Schema::getColumnListing('stock_transactions'))));

        $this->get(route('stock-transfers.create'))
            ->assertOk()
            ->assertSee('Transfer Information')
            ->assertSee('Transfer Items')
            ->assertSee('Stock Preview')
            ->assertSee('Validation Status')
            ->assertDontSee('Important Notes')
            ->assertSee('Add Product');

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
        $this->assertSame(45.0, (float) DB::table('stock_transactions')
            ->where('product_id', $product->id)
            ->where('store_id', $fromId)
            ->sum(DB::raw('quantity_in - quantity_out')));
        $this->assertSame(5.0, (float) DB::table('stock_transactions')
            ->where('product_id', $product->id)
            ->where('store_id', $toId)
            ->sum(DB::raw('quantity_in - quantity_out')));
        $this->assertSame(50.0, (float) DB::table('stock_transactions')
            ->where('product_id', $product->id)
            ->sum(DB::raw('quantity_in - quantity_out')));
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

    private function seedLocationBalance($productId, $storeId, $quantity, $reference)
    {
        DB::table('stock_transactions')->insert(array_intersect_key([
            'store_id' => $storeId,
            'product_id' => $productId,
            'transaction_type' => 'opening',
            'reference_type' => 'opening_stock',
            'reference_id' => null,
            'reference_number' => $reference,
            'quantity_in' => $quantity,
            'quantity_out' => 0,
            'balance_quantity' => $quantity,
            'unit_price' => 10,
            'transaction_date' => now()->toDateString(),
            'remarks' => 'Multi-item stock transfer test seed',
            'created_by' => auth()->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ], array_flip(Schema::getColumnListing('stock_transactions'))));
    }

    private function locationBalance($productId, $storeId)
    {
        return (float) DB::table('stock_transactions')
            ->where('product_id', $productId)
            ->where('store_id', $storeId)
            ->sum(DB::raw('quantity_in - quantity_out'));
    }
}
