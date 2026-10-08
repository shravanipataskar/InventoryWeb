<?php

namespace Tests\Feature;

use App\Category;
use App\GoodsReceipt;
use App\GoodsReceiptItem;
use App\Product;
use App\PurchaseOrder;
use App\Supplier;
use App\Unit;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class PurchaseReportsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_purchase_reports_use_received_purchase_data_and_filters()
    {
        list($user, $supplier, $product, $store, $receipt) = $this->createReceivedPurchase();
        $this->actingAs($user);

        $this->get(route('purchase-reports.index', [
            'date_from' => now()->startOfMonth()->toDateString(),
            'date_to' => now()->toDateString(),
            'supplier_id' => $supplier->id,
            'product_id' => $product->id,
            'category_id' => $product->category_id,
            'store_id' => $store,
            'status' => 'Partial',
            'search' => $product->product_code,
        ]))
            ->assertOk()
            ->assertSee('Purchase Reports')
            ->assertSee($receipt->grn_number)
            ->assertSee($supplier->name)
            ->assertSee($product->name)
            ->assertSee('4.00')
            ->assertSee('₹118.00')
            ->assertSee('₹29.50')
            ->assertSee('Supplier Purchase Summary')
            ->assertDontSee('Purchase Transactions</h2><p>0 matching');

        $this->get(route('purchase-reports.index', [
            'date_from' => '2020-01-01',
            'date_to' => '2020-01-31',
        ]))
            ->assertOk()
            ->assertSee('No purchase transactions found.')
            ->assertSee('Reset Filters');
    }

    public function test_purchase_report_details_exports_and_print_view_are_read_only()
    {
        list($user, $supplier, $product, $store, $receipt) = $this->createReceivedPurchase();
        $this->actingAs($user);

        $this->get(route('purchase-reports.show', ['kind' => 'receipt', 'id' => $receipt->id]))
            ->assertOk()
            ->assertSee($receipt->grn_number)
            ->assertSee($receipt->purchaseOrder->po_number)
            ->assertSee($supplier->name)
            ->assertSee($product->name)
            ->assertSee('10.00')
            ->assertSee('6.00')
            ->assertSee('2.00')
            ->assertSee('4.00');

        $filters = [
            'date_from' => now()->startOfMonth()->toDateString(),
            'date_to' => now()->toDateString(),
            'supplier_id' => $supplier->id,
        ];
        $this->get(route('purchase-reports.export', array_merge(['format' => 'csv'], $filters)))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->get(route('purchase-reports.export', array_merge(['format' => 'excel'], $filters)))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.ms-excel; charset=UTF-8');
        $this->get(route('purchase-reports.export', array_merge([
            'format' => 'csv',
            'report_type' => 'summary',
        ], $filters)))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->get(route('purchase-reports.export', array_merge([
            'format' => 'csv',
            'report_type' => 'suppliers',
        ], $filters)))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->get(route('purchase-reports.export', array_merge(['format' => 'print'], $filters)))
            ->assertOk()
            ->assertSee($receipt->grn_number)
            ->assertSee($product->name);

        $this->assertSame(4, (int) $product->fresh()->current_stock);
        $this->assertSame(1, GoodsReceipt::whereKey($receipt->id)->count());
    }

    public function test_pending_purchase_orders_are_reported_without_increasing_received_quantity()
    {
        list($user, $supplier, $product, $store) = $this->createPurchaseReferences();
        $this->actingAs($user);
        $order = PurchaseOrder::create([
            'po_number' => 'PO-PENDING-' . strtoupper(Str::random(6)),
            'supplier_id' => $supplier->id,
            'store_id' => $store,
            'po_date' => now()->toDateString(),
            'status' => 'approved',
            'created_by' => $user->id,
        ]);
        $order->items()->create([
            'product_id' => $product->id,
            'unit_id' => $product->unit_id,
            'quantity' => 7,
            'received_quantity' => 0,
            'purchase_rate' => 30,
            'discount_percent' => 0,
            'tax_percent' => 0,
            'total_amount' => 210,
        ]);

        $this->get(route('purchase-reports.index', [
            'date_from' => now()->startOfMonth()->toDateString(),
            'date_to' => now()->toDateString(),
        ]))
            ->assertOk()
            ->assertSee($order->po_number)
            ->assertSee('Pending');
        $this->assertSame(0, (int) DB::table('goods_receipt_items')->count());
        $this->assertEquals(0, (float) $product->fresh()->current_stock);
    }

    private function createReceivedPurchase()
    {
        list($user, $supplier, $product, $store) = $this->createPurchaseReferences();
        $order = PurchaseOrder::create([
            'po_number' => 'PO-REPORT-' . strtoupper(Str::random(6)),
            'supplier_id' => $supplier->id,
            'store_id' => $store,
            'po_date' => now()->toDateString(),
            'status' => 'partially_received',
            'subtotal' => 250,
            'grand_total' => 250,
            'created_by' => $user->id,
        ]);
        $orderItem = $order->items()->create([
            'product_id' => $product->id,
            'unit_id' => $product->unit_id,
            'quantity' => 10,
            'received_quantity' => 6,
            'purchase_rate' => 25,
            'discount_percent' => 0,
            'tax_percent' => 0,
            'total_amount' => 250,
        ]);
        $receipt = GoodsReceipt::create([
            'grn_number' => 'GRN-REPORT-' . strtoupper(Str::random(6)),
            'purchase_order_id' => $order->id,
            'supplier_id' => $supplier->id,
            'store_id' => $store,
            'received_date' => now()->toDateString(),
            'invoice_number' => 'INV-REPORT-001',
            'status' => 'posted',
            'received_by' => $user->id,
            'created_by' => $user->id,
        ]);
        $receiptItem = GoodsReceiptItem::create([
            'goods_receipt_id' => $receipt->id,
            'purchase_order_item_id' => $orderItem->id,
            'product_id' => $product->id,
            'ordered_quantity' => 10,
            'received_quantity' => 6,
            'rejected_quantity' => 2,
            'accepted_quantity' => 4,
            'purchase_rate' => 25,
        ]);
        DB::table('stock_inwards')->insert([
            'product_id' => $product->id,
            'supplier_id' => $supplier->id,
            'invoice_number' => $receipt->invoice_number,
            'inward_date' => now()->toDateString(),
            'quantity' => 4,
            'purchase_price' => 25,
            'total_amount' => 100,
            'sgst_rate' => 9,
            'cgst_rate' => 9,
            'sgst_amount' => 9,
            'cgst_amount' => 9,
            'tax_total' => 18,
            'subtotal' => 100,
            'grand_total' => 118,
            'goods_receipt_item_id' => $receiptItem->id,
            'store_id' => $store,
            'received_quantity' => 6,
            'rejected_quantity' => 2,
            'status' => 'posted',
            'created_by' => $user->id,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $product->current_stock = 4;
        $product->save();

        return [$user, $supplier, $product, $store, $receipt->fresh(['purchaseOrder'])];
    }

    private function createPurchaseReferences()
    {
        $suffix = strtoupper(Str::random(7));
        $user = User::create([
            'name' => 'Purchase Report User',
            'email' => Str::uuid() . '@example.test',
            'password' => Hash::make('test-password'),
        ]);
        $supplier = Supplier::create([
            'name' => 'Report Supplier ' . $suffix,
            'supplier_code' => 'SUP-' . $suffix,
            'is_active' => true,
        ]);
        $category = Category::create(['name' => 'Report Category ' . $suffix, 'is_active' => true]);
        $unit = Unit::create(['name' => 'Report Unit ' . $suffix, 'short_name' => 'RU', 'is_active' => true]);
        $product = Product::create([
            'product_code' => 'REPORT-' . $suffix,
            'name' => 'Report Product ' . $suffix,
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'purchase_price' => 25,
            'minimum_stock' => 0,
            'is_active' => true,
        ]);
        $storeValues = [
            'name' => 'Report Store ' . $suffix,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ];
        if (DB::getSchemaBuilder()->hasColumn('stores', 'store_code')) {
            $storeValues['store_code'] = 'RPT-' . $suffix;
        }
        if (DB::getSchemaBuilder()->hasColumn('stores', 'code')) {
            $storeValues['code'] = 'RPT-' . $suffix;
        }
        $store = DB::table('stores')->insertGetId($storeValues);

        return [$user, $supplier, $product, $store];
    }
}
