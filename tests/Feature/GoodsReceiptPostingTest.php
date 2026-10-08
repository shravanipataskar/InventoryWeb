<?php

namespace Tests\Feature;

use App\Category;
use App\GoodsReceipt;
use App\Product;
use App\PurchaseOrder;
use App\StockInward;
use App\Supplier;
use App\Unit;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class GoodsReceiptPostingTest extends TestCase
{
    use DatabaseTransactions;

    public function test_posting_goods_received_creates_one_inward_and_updates_stock_once()
    {
        list($user, $supplier, $product, $store, $purchaseOrder) = $this->makePurchase();
        $this->actingAs($user);
        $this->get(route('purchase-orders.index'))->assertOk();
        $this->get(route('purchase-orders.create'))->assertOk();
        $this->get(route('purchase-orders.show', $purchaseOrder->id))->assertOk();
        $this->get(route('goods-receipts.create', $purchaseOrder->id))->assertOk();
        $this->assertSame(10.0, (float) $product->fresh()->current_stock);
        $token = (string) Str::uuid();
        $payload = $this->receiptPayload($store, $purchaseOrder, $token);

        $this->post(route('goods-receipts.store', $purchaseOrder->id), $payload)
            ->assertRedirect();
        $receipt = GoodsReceipt::where('submission_token', $token)->firstOrFail();
        $inward = StockInward::where('goods_receipt_item_id', $receipt->items()->value('id'))
            ->firstOrFail();

        $this->assertSame('GRN-' . now()->format('Ymd') . '-' . str_pad($receipt->id, 3, '0', STR_PAD_LEFT), $receipt->grn_number);
        $this->assertSame('INW-' . now()->format('Ymd') . '-' . str_pad($inward->id, 4, '0', STR_PAD_LEFT), $inward->inward_number);
        $this->assertSame(5.0, (float) $inward->received_quantity);
        $this->assertSame(1.0, (float) $inward->rejected_quantity);
        $this->assertSame(4.0, (float) $inward->quantity);
        $this->assertSame(220000.0, (float) $inward->total_amount);
        $this->assertSame(14.0, (float) $product->fresh()->current_stock);
        $this->assertSame(1, StockInward::where('goods_receipt_item_id', $receipt->items()->value('id'))->count());

        $transaction = DB::table('stock_transactions')
            ->where('reference_type', 'stock_inward')
            ->where('reference_id', $inward->id)
            ->first();
        $this->assertNotNull($transaction);
        $this->assertSame((int) $store, (int) $transaction->store_id);
        $this->assertSame(4.0, (float) $transaction->quantity_in);
        $this->assertSame(14.0, (float) $transaction->balance_quantity);
        $this->assertSame('received', $purchaseOrder->fresh()->status);
        $this->get(route('goods-receipts.index'))
            ->assertOk()
            ->assertSee($receipt->grn_number)
            ->assertSee($purchaseOrder->po_number)
            ->assertSee($supplier->name);
        $this->get(route('goods-receipts.show', $receipt->id))->assertOk();
        $this->get(route('purchase-orders.show', $purchaseOrder->id))
            ->assertOk()
            ->assertSee($receipt->grn_number)
            ->assertSee('Goods received')
            ->assertSee('RECEIVED ITEMS')
            ->assertSee($product->name)
            ->assertSee('Accepted: 4.00')
            ->assertSee('Rejected: 1.00');
        $this->get(route('stock-inwards.index'))
            ->assertOk()
            ->assertSee($inward->inward_number)
            ->assertSee($receipt->grn_number)
            ->assertSee($purchaseOrder->po_number)
            ->assertSee($product->name)
            ->assertSee($supplier->name)
            ->assertSee('₹55,000.00');
        $this->get(route('stock-inwards.show', $inward->id))->assertOk();
        $this->get(route('stock-movement.index'))->assertOk();
        $this->get(route('products.index'))
            ->assertOk()
            ->assertSee($product->name)
            ->assertSee('14.00');
        $this->get(route('current-stock.index'))
            ->assertOk()
            ->assertSee($product->name)
            ->assertSee('14.00');
    }

    public function test_replaying_a_receipt_post_with_the_same_token_does_not_post_stock_twice()
    {
        list($user, $supplier, $product, $store, $purchaseOrder) = $this->makePurchase();
        $this->actingAs($user);
        $payload = $this->receiptPayload($store, $purchaseOrder, (string) Str::uuid());

        $this->post(route('goods-receipts.store', $purchaseOrder->id), $payload)->assertRedirect();
        $this->post(route('goods-receipts.store', $purchaseOrder->id), $payload)
            ->assertRedirect();

        $this->assertSame(14.0, (float) $product->fresh()->current_stock);
        $this->assertSame(1, GoodsReceipt::where('purchase_order_id', $purchaseOrder->id)->count());
        $this->assertSame(1, StockInward::where('product_id', $product->id)->count());
        $this->assertSame(1, DB::table('stock_transactions')
            ->where('reference_type', 'stock_inward')
            ->count());
    }

    public function test_partial_receipts_complete_the_purchase_order_without_losing_prior_stock()
    {
        list($user, $supplier, $product, $store, $purchaseOrder) = $this->makePurchase();
        $this->actingAs($user);

        $firstReceipt = $this->receiptPayload($store, $purchaseOrder, (string) Str::uuid());
        $firstReceipt['items'][0]['received_quantity'] = 2;
        $firstReceipt['items'][0]['rejected_quantity'] = 0;
        $this->post(route('goods-receipts.store', $purchaseOrder->id), $firstReceipt)->assertRedirect();

        $this->assertSame('partially_received', $purchaseOrder->fresh()->status);
        $this->assertSame(12.0, (float) $product->fresh()->current_stock);

        $secondReceipt = $this->receiptPayload($store, $purchaseOrder, (string) Str::uuid());
        $secondReceipt['items'][0]['received_quantity'] = 3;
        $secondReceipt['items'][0]['rejected_quantity'] = 0;
        $this->post(route('goods-receipts.store', $purchaseOrder->id), $secondReceipt)->assertRedirect();

        $this->assertSame('received', $purchaseOrder->fresh()->status);
        $this->assertSame(15.0, (float) $product->fresh()->current_stock);
        $this->assertSame(2, StockInward::where('product_id', $product->id)->count());
        $this->assertSame(2, GoodsReceipt::where('purchase_order_id', $purchaseOrder->id)->count());
    }

    public function test_purchase_order_route_creates_a_numbered_order_with_product_lines()
    {
        list($user, $supplier, $product, $store) = $this->makePurchase();
        $this->actingAs($user);

        $this->post(route('purchase-orders.store'), [
            'supplier_id' => $supplier->id,
            'store_id' => $store,
            'order_date' => now()->toDateString(),
            'expected_date' => now()->addWeek()->toDateString(),
            'items' => [[
                'product_id' => $product->id,
                'ordered_quantity' => 3,
                'purchase_rate' => 55000,
            ]],
        ])->assertRedirect();

        $order = PurchaseOrder::where('supplier_id', $supplier->id)
            ->where('status', 'approved')
            ->orderByDesc('id')
            ->firstOrFail();
        $this->assertSame('PO-' . now()->format('Ymd') . '-' . str_pad($order->id, 3, '0', STR_PAD_LEFT), $order->po_number);
        $this->assertSame(3.0, (float) $order->items()->firstOrFail()->ordered_quantity);
        $this->assertSame(55000.0, (float) $order->items()->firstOrFail()->purchase_rate);
    }

    public function test_invalid_receipt_quantities_do_not_create_stock_or_receipt_rows()
    {
        list($user, $supplier, $product, $store, $purchaseOrder) = $this->makePurchase();
        $this->actingAs($user);

        $overReceived = $this->receiptPayload($store, $purchaseOrder, (string) Str::uuid());
        $overReceived['items'][0]['received_quantity'] = 6;
        $overReceived['items'][0]['rejected_quantity'] = 0;
        $this->post(route('goods-receipts.store', $purchaseOrder->id), $overReceived)
            ->assertSessionHasErrors('items');

        $rejectedTooMany = $this->receiptPayload($store, $purchaseOrder, (string) Str::uuid());
        $rejectedTooMany['items'][0]['received_quantity'] = 5;
        $rejectedTooMany['items'][0]['rejected_quantity'] = 6;
        $this->post(route('goods-receipts.store', $purchaseOrder->id), $rejectedTooMany)
            ->assertSessionHasErrors('items');

        $this->assertSame(10.0, (float) $product->fresh()->current_stock);
        $this->assertSame(0, GoodsReceipt::where('purchase_order_id', $purchaseOrder->id)->count());
        $this->assertSame(0, StockInward::where('product_id', $product->id)->count());
        $this->assertSame(0, DB::table('stock_transactions')
            ->where('reference_type', 'stock_inward')
            ->count());
    }

    public function test_non_admin_cannot_manually_create_stock_inward()
    {
        $user = $this->makeUser('store_manager');
        $this->actingAs($user);

        $this->get(route('current-stock.index'))
            ->assertOk()
            ->assertSee(route('purchase-orders.index'))
            ->assertSee('Receive Goods')
            ->assertDontSee(route('stock-inwards.create'));
        $this->get(route('stock-inwards.create'))->assertForbidden();
    }

    private function makePurchase()
    {
        $user = $this->makeUser('store_manager');
        $supplier = Supplier::create([
            'supplier_code' => 'GRN-' . strtoupper(Str::random(8)),
            'name' => 'Goods Receipt Supplier',
            'is_active' => true,
        ]);
        $category = Category::create([
            'name' => 'Goods Receipt Category ' . strtoupper(Str::random(8)),
            'is_active' => true,
        ]);
        $unit = Unit::create([
            'name' => 'Goods Receipt Unit ' . strtoupper(Str::random(8)),
            'short_name' => 'GRU',
            'is_active' => true,
        ]);
        $product = Product::create([
            'product_code' => 'GRN-' . strtoupper(Str::random(8)),
            'name' => 'Goods Receipt Test Product',
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'purchase_price' => 50000,
            'selling_price' => 55000,
            'minimum_stock' => 0,
            'is_active' => true,
        ]);
        $product->current_stock = 10;
        $product->save();
        $storeValues = [
            'name' => 'GRN Location ' . strtoupper(Str::random(6)),
            'code' => 'LOC-' . strtoupper(Str::random(8)),
            'location' => 'Test',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ];
        if (DB::getSchemaBuilder()->hasColumn('stores', 'store_code')) {
            $storeValues['store_code'] = 'GRN-' . strtoupper(Str::random(8));
        }
        $store = DB::table('stores')->insertGetId($storeValues);
        DB::table('stock_transactions')->insert([
            'store_id' => $store,
            'product_id' => $product->id,
            'transaction_type' => 'opening',
            'reference_type' => 'opening_stock',
            'reference_id' => null,
            'quantity_in' => 10,
            'quantity_out' => 0,
            'balance_quantity' => 10,
            'unit_price' => 50000,
            'transaction_date' => now()->toDateString(),
            'remarks' => 'Opening balance',
            'created_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $purchaseOrder = PurchaseOrder::create([
            'po_number' => 'PO-' . strtoupper(Str::random(8)),
            'supplier_id' => $supplier->id,
            'store_id' => $store,
            'po_date' => now()->toDateString(),
            'status' => 'approved',
            'created_by' => $user->id,
        ]);
        $purchaseOrder->items()->create([
            'product_id' => $product->id,
            'unit_id' => $product->unit_id,
            'quantity' => 5,
            'received_quantity' => 0,
            'purchase_rate' => 55000,
            'discount_percent' => 0,
            'tax_percent' => 0,
            'total_amount' => 275000,
        ]);

        return [$user, $supplier, $product, $store, $purchaseOrder];
    }

    private function receiptPayload($store, PurchaseOrder $purchaseOrder, $token)
    {
        return [
            'store_id' => $store,
            'received_date' => now()->toDateString(),
            'submission_token' => $token,
            'invoice_number' => 'INV-GRN-001',
            'remarks' => 'Received in good condition.',
            'items' => [[
                'purchase_order_item_id' => $purchaseOrder->items()->value('id'),
                'received_quantity' => 5,
                'rejected_quantity' => 1,
            ]],
        ];
    }

    private function makeUser($role)
    {
        return User::create([
            'name' => 'Goods Receipt Test User',
            'email' => Str::uuid() . '@example.test',
            'password' => Hash::make('test-password'),
            'role' => $role,
        ]);
    }
}
