<?php

namespace Tests\Feature;

use App\Category;
use App\GoodsReceipt;
use App\Product;
use App\ProcurementAuditLog;
use App\PurchaseOrder;
use App\StockInward;
use App\Supplier;
use App\SupplierBill;
use App\Unit;
use App\User;
use App\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class GoodsReceiptPostingTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(VerifyCsrfToken::class);
    }

    public function test_purchase_order_detail_displays_the_order_summary_and_sections()
    {
        list($user, $supplier, $product, $store, $purchaseOrder) = $this->makePurchase();
        $user->role = 'ADMIN';
        $user->save();
        $this->actingAs($user);

        $this->get(route('purchase-orders.show', $purchaseOrder->id))
            ->assertOk()
            ->assertSee($purchaseOrder->po_number)
            ->assertSee('Total Order Value')
            ->assertSee('Total Items')
            ->assertSee('Pending Value')
            ->assertSee('Supplier Information')
            ->assertSee('Order Information')
            ->assertSee('Order Items')
            ->assertSee('Goods Received')
            ->assertSee('Supplier Bills &amp; Payments', false)
            ->assertSee('Audit History')
            ->assertSee($supplier->name)
            ->assertSee($product->name);
    }

    public function test_purchase_order_print_view_uses_selected_order_and_masks_supplier_bank_account()
    {
        list($user, $supplier, $product, $store, $purchaseOrder) = $this->makePurchase();
        $this->actingAs($user);
        $supplier->bank_name = 'Example Bank';
        $supplier->account_holder_name = 'Goods Receipt Supplier';
        $supplier->account_number = '1234567890123456';
        $supplier->ifsc_code = 'EXAM0001234';
        $supplier->branch_name = 'Pune';
        $supplier->save();

        $purchaseOrder->status = 'partially_received';
        $purchaseOrder->subtotal = 275000;
        $purchaseOrder->discount_amount = 27500;
        $purchaseOrder->tax_amount = 44550;
        $purchaseOrder->cgst_total = 22275;
        $purchaseOrder->sgst_total = 22275;
        $purchaseOrder->grand_total = 292050;
        $purchaseOrder->notes = 'Deliver during business hours.';
        $purchaseOrder->save();
        $orderItem = $purchaseOrder->items()->firstOrFail();
        $orderItem->discount_percent = 10;
        $orderItem->tax_percent = 18;
        $orderItem->cgst_rate = 9;
        $orderItem->cgst_amount = 22275;
        $orderItem->sgst_rate = 9;
        $orderItem->sgst_amount = 22275;
        $orderItem->save();
        DB::table('system_settings')->updateOrInsert(
            ['key' => 'business_address'],
            ['value' => '42 Example Street, Pune', 'created_at' => now(), 'updated_at' => now()]
        );

        $this->get(route('purchase-orders.print', $purchaseOrder->id))
            ->assertOk()
            ->assertSee('PURCHASE ORDER')
            ->assertSee($purchaseOrder->po_number)
            ->assertSee($product->name)
            ->assertSee('42 Example Street, Pune')
            ->assertSee('Example Bank')
            ->assertSee('••••••••••••3456')
            ->assertDontSee('1234567890123456')
            ->assertSee('₹275,000.00')
            ->assertSee('₹27,500.00')
            ->assertSee('₹44,550.00')
            ->assertSee('₹292,050.00')
            ->assertSee('Partially Received')
            ->assertSee('Terms &amp; Conditions', false)
            ->assertSee('Print / Save PDF')
            ->assertDontSee('Dashboard')
            ->assertDontSee('Payment History')
            ->assertDontSee('Authorized By')
            ->assertDontSee('Supplier Acceptance');

        $supplier->account_number = null;
        $supplier->save();
        $this->get(route('purchase-orders.print', $purchaseOrder->id))
            ->assertOk()
            ->assertSee('Account Number')
            ->assertSee('—');

        $purchaseOrder->status = 'received';
        $purchaseOrder->save();
        $this->get(route('purchase-orders.print', $purchaseOrder->id))
            ->assertOk()
            ->assertSee('Fully Received');
    }

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
            ->assertSee('Goods Received')
            ->assertSee('RECEIVED ITEMS')
            ->assertSee($product->name)
            ->assertSee('Accepted: 4.00')
            ->assertSee('Rejected: 1.00');
        $this->get(route('stock-inwards.index'))
            ->assertOk()
            ->assertSee('New Inward')
            ->assertDontSee('New Quotation')
            ->assertSee(route('purchase-orders.index'), false)
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
            ->assertSee('14.00')
            ->assertSee('₹770,000.00');
    }

    public function test_goods_receipt_draft_does_not_change_stock_and_can_be_posted_once()
    {
        list($user, $supplier, $product, $store, $purchaseOrder) = $this->makePurchase();
        $this->actingAs($user);
        $token = (string) Str::uuid();
        $initialInwardMovements = DB::table('stock_transactions')
            ->where('reference_type', 'stock_inward')
            ->count();
        $payload = $this->receiptPayload($store, $purchaseOrder, $token);
        $payload['action'] = 'draft';

        $this->post(route('goods-receipts.store', $purchaseOrder->id), $payload)
            ->assertRedirect();

        $receipt = GoodsReceipt::where('submission_token', $token)->firstOrFail();
        $this->assertSame('draft', $receipt->status);
        $this->assertSame(10.0, (float) $product->fresh()->current_stock);
        $this->assertSame(0.0, (float) $purchaseOrder->items()->value('received_quantity'));
        $this->assertSame(0, StockInward::where('goods_receipt_item_id', $receipt->items()->value('id'))->count());
        $this->assertSame(
            $initialInwardMovements,
            DB::table('stock_transactions')->where('reference_type', 'stock_inward')->count()
        );

        $this->get(route('goods-receipts.index'))
            ->assertOk()
            ->assertSee('Edit Draft');
        $this->get(route('goods-receipts.create', [
            'purchaseOrder' => $purchaseOrder->id,
            'draft_id' => $receipt->id,
        ]))
            ->assertOk()
            ->assertSee('Save as Draft')
            ->assertSee('Post Inward');

        $payload['action'] = 'post';
        $this->post(route('goods-receipts.store', $purchaseOrder->id), $payload)
            ->assertRedirect();

        $receipt->refresh();
        $this->assertSame('posted', $receipt->status);
        $this->assertSame(14.0, (float) $product->fresh()->current_stock);
        $this->assertSame(5.0, (float) $purchaseOrder->items()->value('received_quantity'));
        $this->assertSame(1, StockInward::where('goods_receipt_item_id', $receipt->items()->value('id'))->count());
        $this->assertSame(
            $initialInwardMovements + 1,
            DB::table('stock_transactions')->where('reference_type', 'stock_inward')->count()
        );

        $this->post(route('goods-receipts.store', $purchaseOrder->id), $payload)
            ->assertRedirect();
        $this->assertSame(14.0, (float) $product->fresh()->current_stock);
        $this->assertSame(1, StockInward::where('goods_receipt_item_id', $receipt->items()->value('id'))->count());
    }

    public function test_posted_inward_uses_purchase_order_discount_and_gst_rates()
    {
        list($user, $supplier, $product, $store, $purchaseOrder) = $this->makePurchase();
        $this->actingAs($user);
        $orderItem = $purchaseOrder->items()->firstOrFail();
        $orderItem->discount_percent = 10;
        $orderItem->tax_percent = 18;
        $orderItem->cgst_rate = 9;
        $orderItem->sgst_rate = 9;
        $orderItem->save();

        $payload = $this->receiptPayload($store, $purchaseOrder, (string) Str::uuid());
        $this->post(route('goods-receipts.store', $purchaseOrder->id), $payload)->assertRedirect();

        $receipt = GoodsReceipt::where('purchase_order_id', $purchaseOrder->id)->firstOrFail();
        $inward = StockInward::where('goods_receipt_item_id', $receipt->items()->value('id'))->firstOrFail();
        $this->assertSame(220000.0, (float) $inward->subtotal);
        $this->assertSame(198000.0, (float) $inward->total_amount);
        $this->assertSame(17820.0, (float) $inward->cgst_amount);
        $this->assertSame(17820.0, (float) $inward->sgst_amount);
        $this->assertSame(35640.0, (float) $inward->tax_total);
        $this->assertSame(233640.0, (float) $inward->grand_total);

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('stock-inwards.show', $inward->id))
            ->assertOk()
            ->assertSee('₹22,000.00')
            ->assertSee('CGST Amount (₹)')
            ->assertSee('₹17,820.00')
            ->assertSee('₹35,640.00');
    }

    public function test_legacy_inward_tax_uses_po_rates_and_matching_supplier_bill_invoice_date()
    {
        list($user, $supplier, $product, $store, $purchaseOrder) = $this->makePurchase();
        $this->actingAs($user);
        $orderItem = $purchaseOrder->items()->firstOrFail();
        $orderItem->discount_percent = 10;
        $orderItem->tax_percent = 18;
        $orderItem->cgst_rate = 9;
        $orderItem->sgst_rate = 9;
        $orderItem->save();

        $payload = $this->receiptPayload($store, $purchaseOrder, (string) Str::uuid());
        $this->post(route('goods-receipts.store', $purchaseOrder->id), $payload)->assertRedirect();
        $receipt = GoodsReceipt::where('purchase_order_id', $purchaseOrder->id)->firstOrFail();
        $inward = StockInward::where('goods_receipt_item_id', $receipt->items()->value('id'))->firstOrFail();
        $inward->forceFill([
            'cgst_amount' => 0,
            'sgst_amount' => 0,
            'tax_total' => 0,
        ])->save();

        SupplierBill::create([
            'bill_number' => 'BILL-' . strtoupper(Str::random(8)),
            'submission_token' => (string) Str::uuid(),
            'supplier_id' => $supplier->id,
            'purchase_order_id' => $purchaseOrder->id,
            'invoice_number' => $receipt->invoice_number,
            'duplicate_key' => SupplierBill::duplicateKey($supplier->id, $receipt->invoice_number),
            'invoice_date' => '2026-10-01',
            'subtotal' => 198000,
            'discount_amount' => 22000,
            'tax_amount' => 35640,
            'total_amount' => 233640,
            'invoice_total' => 233640,
            'created_by' => $user->id,
        ]);

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('stock-inwards.show', $inward->id))
            ->assertOk()
            ->assertSee('₹17,820.00')
            ->assertSee('₹35,640.00')
            ->assertSee('CGST Amount (₹)')
            ->assertSee('SGST Amount (₹)')
            ->assertSee('IGST Amount (₹)')
            ->assertSee('Supplier Invoice Date')
            ->assertSee('01 Oct 2026');
    }

    public function test_stock_inward_details_modal_displays_all_actual_lines_for_the_receipt()
    {
        list($user, $supplier, $product, $store, $purchaseOrder) = $this->makePurchase();
        $this->actingAs($user);
        $secondProduct = Product::create([
            'product_code' => 'GRN-' . strtoupper(Str::random(8)),
            'name' => 'Second Goods Receipt Test Product',
            'category_id' => $product->category_id,
            'unit_id' => $product->unit_id,
            'purchase_price' => 250,
            'selling_price' => 300,
            'minimum_stock' => 0,
            'is_active' => true,
        ]);
        $secondOrderItem = $purchaseOrder->items()->create([
            'product_id' => $secondProduct->id,
            'unit_id' => $secondProduct->unit_id,
            'quantity' => 3,
            'received_quantity' => 0,
            'purchase_rate' => 250,
            'discount_percent' => 0,
            'tax_percent' => 0,
            'total_amount' => 750,
        ]);
        $payload = $this->receiptPayload($store, $purchaseOrder, (string) Str::uuid());
        $payload['items'][] = [
            'purchase_order_item_id' => $secondOrderItem->id,
            'received_quantity' => 3,
            'rejected_quantity' => 0,
        ];
        $this->post(route('goods-receipts.store', $purchaseOrder->id), $payload)->assertRedirect();

        $receipt = GoodsReceipt::where('purchase_order_id', $purchaseOrder->id)->firstOrFail();
        $inward = StockInward::whereHas('goodsReceiptItem', function ($query) use ($receipt) {
            $query->where('goods_receipt_id', $receipt->id);
        })->firstOrFail();
        $this->get(route('stock-inwards.index', ['supplier_id' => $supplier->id]))
            ->assertOk()
            ->assertSee('New Inward')
            ->assertDontSee('New Quotation')
            ->assertSee(route('purchase-orders.index'), false)
            ->assertSee('Total Quantity In')
            ->assertSee('8.00');
        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('stock-inwards.show', $inward->id))
            ->assertOk()
            ->assertSee('View Stock Inward Details')
            ->assertSee('Inward Information')
            ->assertSee('Items Received')
            ->assertSee($product->name)
            ->assertSee($secondProduct->name)
            ->assertSee($secondProduct->product_code)
            ->assertSee('Previously Received')
            ->assertSee('Quantity Summary')
            ->assertSee('Financial Summary')
            ->assertSee('IGST Amount (₹)')
            ->assertSee('Supplier Invoice Date')
            ->assertSee('<div><small>Supplier Invoice Date</small><strong>—</strong></div>', false)
            ->assertSee('<small>IGST Amount (₹)</small><strong>₹0.00</strong>', false)
            ->assertSee('Related Documents')
            ->assertSee('Stock Movement Timeline')
            ->assertSee('data-stock-inward-print', false)
            ->assertSee('data-stock-inward-close', false)
            ->assertDontSee('Edit Inward');
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
            ->where('product_id', $product->id)
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

        $latestReceipt = GoodsReceipt::where('purchase_order_id', $purchaseOrder->id)->orderByDesc('id')->firstOrFail();
        $latestInward = StockInward::whereHas('goodsReceiptItem', function ($query) use ($latestReceipt) {
            $query->where('goods_receipt_id', $latestReceipt->id);
        })->firstOrFail();
        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('stock-inwards.show', $latestInward->id))
            ->assertOk()
            ->assertSee('Previously Received')
            ->assertSee('2.00');
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
            ->where('status', 'draft')
            ->orderByDesc('id')
            ->firstOrFail();
        $this->assertSame('PO-' . now()->format('Ymd') . '-' . str_pad($order->id, 3, '0', STR_PAD_LEFT), $order->po_number);
        $this->assertSame(3.0, (float) $order->items()->firstOrFail()->ordered_quantity);
        $this->assertSame(55000.0, (float) $order->items()->firstOrFail()->purchase_rate);
    }

    public function test_new_purchase_order_cannot_be_received_until_released()
    {
        list($user, $supplier, $product, $store) = $this->makePurchase();
        $user->role = 'ADMIN';
        $user->save();
        $this->actingAs($user);
        $this->post(route('purchase-orders.store'), [
            'supplier_id' => $supplier->id,
            'store_id' => $store,
            'order_date' => now()->toDateString(),
            'items' => [[
                'product_id' => $product->id,
                'ordered_quantity' => 3,
                'purchase_rate' => 55000,
            ]],
        ])->assertRedirect();
        $order = PurchaseOrder::where('supplier_id', $supplier->id)->orderByDesc('id')->firstOrFail();
        $this->assertSame('draft', $order->status);

        $this->get(route('goods-receipts.create', $order->id))->assertRedirect(route('purchase-orders.show', $order->id));
        $this->assertSame(0, GoodsReceipt::where('purchase_order_id', $order->id)->count());

        $this->post(route('purchase-orders.release', $order->id))->assertRedirect();
        $this->assertSame('released', $order->fresh()->status);
        $this->assertSame($user->id, (int) $order->fresh()->released_by);
        $this->assertNotNull($order->fresh()->released_at);
        $this->assertTrue(ProcurementAuditLog::where('entity_type', 'PurchaseOrder')->where('entity_id', $order->id)->where('event', 'po_released')->exists());
        $this->get(route('goods-receipts.create', $order->id))->assertOk();
    }

    public function test_draft_purchase_order_can_be_edited_and_recalculates_tax_totals()
    {
        list($user, $supplier, $product, $store) = $this->makePurchase();
        $this->actingAs($user);
        $payload = [
            'supplier_id' => $supplier->id,
            'store_id' => $store,
            'order_date' => now()->toDateString(),
            'expected_date' => now()->addDays(5)->toDateString(),
            'items' => [[
                'product_id' => $product->id,
                'ordered_quantity' => 2,
                'purchase_rate' => 100,
                'tax_percent' => 18,
            ]],
        ];

        $this->post(route('purchase-orders.store'), $payload)->assertRedirect();
        $order = PurchaseOrder::where('supplier_id', $supplier->id)->where('status', 'draft')->orderByDesc('id')->firstOrFail();
        $this->get(route('purchase-orders.edit', $order->id))
            ->assertOk()
            ->assertSee('Save Draft Changes')
            ->assertSee('GST %');

        $payload['items'][0]['ordered_quantity'] = 4;
        $this->put(route('purchase-orders.update', $order->id), $payload)
            ->assertRedirect(route('purchase-orders.show', $order->id));
        $order->refresh();

        $this->assertSame(400.0, (float) $order->subtotal);
        $this->assertSame(72.0, (float) $order->tax_amount);
        $this->assertSame(472.0, (float) $order->grand_total);
        $this->assertSame(4.0, (float) $order->items()->firstOrFail()->ordered_quantity);
        $this->assertSame(18.0, (float) $order->items()->firstOrFail()->tax_percent);

        $this->post(route('purchase-orders.release', $order->id))->assertRedirect();
        $this->get(route('purchase-orders.edit', $order->id))->assertNotFound();
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
            ->where('product_id', $product->id)
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
        $user = $this->makeUser('ADMIN');
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
