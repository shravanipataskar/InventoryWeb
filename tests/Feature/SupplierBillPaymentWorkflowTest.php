<?php

namespace Tests\Feature;

use App\Category;
use App\GoodsReceipt;
use App\ProcurementAuditLog;
use App\Product;
use App\PurchaseOrder;
use App\PurchasePayment;
use App\Quotation;
use App\QuotationRequest;
use App\Role;
use App\Supplier;
use App\SupplierBill;
use App\SupplierPayment;
use App\Unit;
use App\User;
use Carbon\Carbon;
use App\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class SupplierBillPaymentWorkflowTest extends TestCase
{
    use DatabaseTransactions;

    private $officer;
    private $owner;
    private $admin;
    private $suppliers;
    private $product;
    private $store;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(VerifyCsrfToken::class);
        $this->officer = $this->makeUser('PURCHASE_OFFICER');
        $this->owner = $this->makeUser('OWNER');
        $this->admin = $this->makeUser('ADMIN');

        $category = Category::create(['name' => 'Workflow Category ' . Str::random(8), 'is_active' => true]);
        $unit = Unit::create(['name' => 'Workflow Unit ' . Str::random(8), 'short_name' => 'WU', 'is_active' => true]);
        $this->product = Product::create([
            'product_code' => 'WF-' . strtoupper(Str::random(8)),
            'name' => 'Workflow Product',
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'purchase_price' => 100,
            'selling_price' => 150,
            'minimum_stock' => 0,
            'is_active' => true,
        ]);
        $this->product->current_stock = 0;
        $this->product->save();

        $this->suppliers = collect([1, 2])->map(function ($index) {
            return Supplier::create([
                'supplier_code' => 'WF' . $index . '-' . strtoupper(Str::random(6)),
                'name' => 'Workflow Supplier ' . $index,
                'is_active' => true,
            ]);
        });

        $storeValues = [
            'name' => 'Workflow Store ' . Str::random(6),
            'code' => 'WFS-' . strtoupper(Str::random(8)),
            'location' => 'Test',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ];
        if (DB::getSchemaBuilder()->hasColumn('stores', 'store_code')) {
            $storeValues['store_code'] = 'WFS-' . strtoupper(Str::random(8));
        }
        $this->store = DB::table('stores')->insertGetId($storeValues);
    }

    public function test_quotation_approval_defers_purchase_order_until_the_first_payment()
    {
        list($approved, $other) = $this->approvedQuotation();

        $this->assertSame('approved', $approved->fresh()->status);
        $this->assertSame('rejected', $other->fresh()->status);
        $this->assertSame('Another supplier quotation was approved.', $other->fresh()->rejection_reason);

        // Second approval for the same request is refused.
        $this->actingAs($this->owner)->post(route('quotations.approve', $other->id))->assertSessionHasErrors('quotation');
        // A rejected quotation cannot generate a PO.
        $this->post(route('quotations.generate-purchase', $other->id))->assertSessionHasErrors('quotation');

        $this->actingAs($this->owner)
            ->post(route('quotations.generate-purchase', $approved->id))
            ->assertRedirect(route('quotations.purchase-details', $approved->id));
        $this->assertSame(0, PurchaseOrder::where('quotation_id', $approved->id)->count());

        $items = [];
        foreach ($approved->items as $item) {
            $items[$item->id] = ['purchase_rate' => 100, 'discount_percent' => 0];
        }
        $this->post(route('quotations.purchase-payments.store', $approved->id), [
            'payment_date' => now()->toDateString(),
            'amount' => 200,
            'payment_method' => 'Bank Transfer',
            'submission_token' => (string) Str::uuid(),
            'items' => $items,
        ])->assertRedirect(route('quotations.purchase-details', $approved->id));
        $order = PurchaseOrder::where('quotation_id', $approved->id)->with('items')->firstOrFail();
        $this->post(route('quotations.purchase-payments.store', $approved->id), [
            'payment_date' => now()->toDateString(),
            'amount' => 200,
            'payment_method' => 'Bank Transfer',
            'submission_token' => $firstToken = DB::table('purchase_payments')->where('purchase_order_id', $order->id)->value('submission_token'),
            'items' => $items,
        ])->assertRedirect(route('quotations.purchase-details', $approved->id));
        $this->assertSame('1180.00', (string) $order->grand_total);
        $this->assertSame('18.00', (string) $order->items->first()->tax_percent);
        $this->assertSame(1, PurchaseOrder::where('quotation_id', $approved->id)->count());
        $this->assertSame(1, DB::table('purchase_payments')->where('purchase_order_id', $order->id)->count());
        $this->assertTrue(ProcurementAuditLog::where('entity_type', 'PurchaseOrder')->where('entity_id', $order->id)->where('event', 'po_created')->exists());
    }

    public function test_negotiated_price_discount_and_partial_purchase_payments_are_persisted()
    {
        list($quotation) = $this->approvedQuotation(null, false);
        $this->actingAs($this->owner)
            ->post(route('quotations.approve', $quotation->id))
            ->assertRedirect(route('quotations.show', $quotation->quotation_request_id));
        $quotation->refresh();
        $item = $quotation->items->first();

        $this->get(route('quotations.purchase-details', $quotation->id))
            ->assertOk()
            ->assertSee('Purchase Details')
            ->assertSee('Bank &amp; Payment Details', false)
            ->assertSee('Record Payment');

        $firstToken = (string) Str::uuid();
        $initialPayment = [
            'payment_date' => now()->toDateString(),
            'amount' => 300,
            'payment_method' => 'Bank Transfer',
            'reference_number' => 'TXN-FIRST',
            'remarks' => 'Advance',
            'submission_token' => $firstToken,
            'items' => [$item->id => ['purchase_rate' => 80, 'discount_percent' => 5]],
        ];
        $this->post(route('quotations.purchase-payments.store', $quotation->id), $initialPayment)
            ->assertRedirect(route('quotations.purchase-details', $quotation->id));

        $order = PurchaseOrder::where('quotation_id', $quotation->id)->with('items')->firstOrFail();
        $this->assertSame('draft', $order->status);
        $this->assertSame(800.0, (float) $order->subtotal);
        $this->assertSame(40.0, (float) $order->discount_amount);
        $this->assertSame(136.8, (float) $order->tax_amount);
        $this->assertSame(896.8, (float) $order->grand_total);
        $this->assertSame(80.0, (float) $order->items->first()->purchase_rate);
        $this->assertSame(5.0, (float) $order->items->first()->discount_percent);
        $this->get(route('purchase-orders.show', $order->id))
            ->assertOk()
            ->assertSee('Paid Amount')
            ->assertSee('Outstanding Amount')
            ->assertSee('Purchase Payment History')
            ->assertSee('TXN-FIRST');
        $this->get(route('suppliers.show', $quotation->supplier_id))
            ->assertOk()
            ->assertSee('Purchase History')
            ->assertSee('₹300.00')
            ->assertSee('₹596.80');

        $this->post(route('quotations.purchase-payments.store', $quotation->id), [
            'payment_date' => now()->addDay()->toDateString(),
            'amount' => 600,
            'payment_method' => 'UPI',
            'submission_token' => (string) Str::uuid(),
        ])->assertSessionHasErrors([
            'amount' => 'Payment amount cannot be greater than the eligible outstanding amount of ₹596.80.',
        ]);
        $this->assertSame(1, DB::table('purchase_payments')->where('purchase_order_id', $order->id)->count());

        $secondToken = (string) Str::uuid();
        $this->post(route('quotations.purchase-payments.store', $quotation->id), [
            'payment_date' => now()->addDay()->toDateString(),
            'amount' => 200,
            'payment_method' => 'UPI',
            'submission_token' => $secondToken,
        ])->assertRedirect();
        $this->post(route('quotations.purchase-payments.store', $quotation->id), [
            'payment_date' => now()->addDays(2)->toDateString(),
            'amount' => 396.8,
            'payment_method' => 'NEFT',
            'submission_token' => (string) Str::uuid(),
        ])->assertRedirect();

        $this->assertSame(3, DB::table('purchase_payments')->where('purchase_order_id', $order->id)->count());
        $this->assertSame(896.8, (float) DB::table('purchase_payments')->where('purchase_order_id', $order->id)->sum('amount'));
        $this->get(route('quotations.purchase-details', $quotation->id))
            ->assertOk()
            ->assertSee('Payment Completed')
            ->assertSee('Paid')
            ->assertDontSee('TXN-FIRST')
            ->assertSee('PP-');

        $this->post(route('quotations.purchase-payments.store', $quotation->id), [
            'payment_date' => now()->toDateString(),
            'amount' => 1,
            'payment_method' => 'Cash',
            'submission_token' => (string) Str::uuid(),
        ])->assertSessionHasErrors('amount');
        $this->assertSame(3, DB::table('purchase_payments')->where('purchase_order_id', $order->id)->count());
    }

    public function test_direct_partial_payments_accept_amount_or_percentage_without_an_installment_plan()
    {
        list($quotation) = $this->approvedQuotation();
        $items = [];
        foreach ($quotation->items as $item) {
            $items[$item->id] = ['purchase_rate' => 100, 'discount_percent' => 0];
        }
        $this->actingAs($this->owner)
            ->post(route('quotations.purchase-orders.generate', $quotation->id), ['items' => $items])
            ->assertRedirect(route('quotations.purchase-details', $quotation->id));
        $order = PurchaseOrder::where('quotation_id', $quotation->id)->firstOrFail();
        $order->update(['grand_total' => 100000]);

        $staff = User::create([
            'name' => 'Payment Restricted User',
            'email' => Str::uuid() . '@example.test',
            'password' => Hash::make('test-password'),
            'role' => 'STAFF',
        ]);
        $this->actingAs($staff)
            ->post(route('quotations.purchase-payments.store', $quotation->id), [
                'payment_date' => now()->toDateString(),
                'amount' => 20000,
                'payment_input_mode' => 'amount',
                'submission_token' => (string) Str::uuid(),
            ])->assertForbidden();

        $this->actingAs($this->owner)->get(route('quotations.purchase-details', $quotation->id))
            ->assertOk()
            ->assertSee('Payment Amount (₹)')
            ->assertSee('Payment Percentage (%)')
            ->assertSee('Payment History');

        $this->post(route('quotations.purchase-payments.store', $quotation->id), [
            'payment_date' => now()->toDateString(),
            'payment_input_mode' => 'percentage',
            'payment_percentage' => '20.00',
            'amount' => '999999.99',
            'submission_token' => (string) Str::uuid(),
        ])->assertRedirect();
        $this->post(route('quotations.purchase-payments.store', $quotation->id), [
            'payment_date' => now()->toDateString(),
            'payment_input_mode' => 'amount',
            'amount' => '30000.00',
            'payment_percentage' => '99.99',
            'submission_token' => (string) Str::uuid(),
        ])->assertRedirect();
        $this->post(route('quotations.purchase-payments.store', $quotation->id), [
            'payment_date' => now()->toDateString(),
            'payment_input_mode' => 'percentage',
            'payment_percentage' => '100.00',
            'submission_token' => (string) Str::uuid(),
        ])->assertRedirect();

        $payments = DB::table('purchase_payments')
            ->where('purchase_order_id', $order->id)
            ->orderBy('id')
            ->get();
        $this->assertSame(['20000.00', '30000.00', '50000.00'], $payments->pluck('amount')->all());
        $this->assertSame(0, $order->installments()->count());
        $this->assertSame(100000.0, (float) $payments->sum('amount'));
        $this->get(route('quotations.purchase-details', $quotation->id))
            ->assertOk()
            ->assertSee('₹100,000.00')
            ->assertSee('100.00%')
            ->assertSee('Paid')
            ->assertSee('20.00%')
            ->assertSee('30.00%')
            ->assertSee('50.00%')
            ->assertSee('PP-' . str_pad($payments->first()->id, 8, '0', STR_PAD_LEFT));
    }

    public function test_payment_percentage_is_based_on_current_eligible_outstanding_balance()
    {
        list($quotation) = $this->approvedQuotation();
        $items = [];
        foreach ($quotation->items as $item) {
            $items[$item->id] = ['purchase_rate' => 100, 'discount_percent' => 0];
        }
        $this->actingAs($this->owner)
            ->post(route('quotations.purchase-orders.generate', $quotation->id), ['items' => $items])
            ->assertRedirect();
        $order = PurchaseOrder::where('quotation_id', $quotation->id)->firstOrFail();
        $order->update(['grand_total' => 1235000]);

        $this->get(route('quotations.purchase-details', $quotation->id))
            ->assertOk()
            ->assertSee('eligible outstanding balance')
            ->assertSee('₹1,235,000.00')
            ->assertSee('Calculated against eligible outstanding balance')
            ->assertDontSee('Based on final payable');
        $this->withSession(['_old_input' => [
            'payment_input_mode' => 'percentage',
            'payment_percentage' => '50',
        ]])->get(route('quotations.purchase-details', $quotation->id))
            ->assertOk()
            ->assertSee('value="617500.00"', false);
        $this->post(route('quotations.purchase-payments.store', $quotation->id), [
            'payment_date' => now()->toDateString(),
            'payment_input_mode' => 'percentage',
            'payment_percentage' => '20.00',
            'submission_token' => (string) Str::uuid(),
        ])->assertRedirect();
        $this->assertSame(247000.0, (float) DB::table('purchase_payments')
            ->where('purchase_order_id', $order->id)
            ->sum('amount'));
        $this->get(route('quotations.purchase-details', $quotation->id))
            ->assertOk()
            ->assertSee('₹988,000.00')
            ->assertSee('20.00%');

        $this->post(route('quotations.purchase-payments.store', $quotation->id), [
            'payment_date' => now()->toDateString(),
            'payment_input_mode' => 'amount',
            'amount' => '247000.00',
            'submission_token' => (string) Str::uuid(),
        ])->assertRedirect();
        $this->assertSame(494000.0, (float) DB::table('purchase_payments')
            ->where('purchase_order_id', $order->id)
            ->sum('amount'));
        $this->get(route('quotations.purchase-details', $quotation->id))
            ->assertOk()
            ->assertSee('₹741,000.00')
            ->assertSee('40.00%');
    }

    public function test_purchase_discounts_are_editable_persisted_and_recalculate_tax_and_payable()
    {
        list($quotation) = $this->approvedQuotation();
        $items = [];
        foreach ($quotation->items as $item) {
            $items[$item->id] = ['purchase_rate' => 30000, 'discount_percent' => 0];
        }

        $this->actingAs($this->owner)
            ->post(route('quotations.purchase-orders.generate', $quotation->id), ['items' => $items])
            ->assertRedirect(route('quotations.purchase-details', $quotation->id));
        $order = PurchaseOrder::where('quotation_id', $quotation->id)->with('items')->firstOrFail();
        $this->assertSame(354000.0, (float) $order->grand_total);

        $purchaseItem = $order->items->first();
        $this->get(route('quotations.purchase-details', $quotation->id))
            ->assertOk()
            ->assertSee('name="items[' . $purchaseItem->id . '][discount_percent]"', false)
            ->assertDontSee('Payment Plan / Installment Schedule')
            ->assertDontSee('Add Installment');

        $staff = User::create([
            'name' => 'Discount Restricted User',
            'email' => Str::uuid() . '@example.test',
            'password' => Hash::make('test-password'),
            'role' => 'STAFF',
        ]);
        $this->actingAs($staff)->put(route('quotations.purchase-discounts.update', $quotation->id), [
            'items' => [$purchaseItem->id => ['discount_percent' => 10]],
        ])->assertForbidden();

        $this->actingAs($this->owner)->put(route('quotations.purchase-discounts.update', $quotation->id), [
            'items' => [$purchaseItem->id => ['discount_percent' => '100.01']],
        ])->assertSessionHasErrors('items.' . $purchaseItem->id . '.discount_percent');

        $this->actingAs($this->officer)->get(route('quotations.purchase-details', $quotation->id))
            ->assertOk()
            ->assertSee('name="items[' . $purchaseItem->id . '][discount_percent]"', false);
        $this->put(route('quotations.purchase-discounts.update', $quotation->id), [
            'items' => [$purchaseItem->id => ['discount_percent' => '5.50']],
        ])->assertRedirect(route('quotations.purchase-details', $quotation->id));
        $this->assertSame(334530.0, (float) $order->fresh()->grand_total);

        $this->actingAs($this->owner);
        $this->put(route('quotations.purchase-discounts.update', $quotation->id), [
            'items' => [$purchaseItem->id => ['discount_percent' => '10.00']],
        ])->assertRedirect(route('quotations.purchase-details', $quotation->id));

        $order->refresh();
        $purchaseItem->refresh();
        $this->assertSame(30000.0, (float) $purchaseItem->purchase_rate);
        $this->assertSame(10.0, (float) $purchaseItem->discount_percent);
        $this->assertSame(318600.0, (float) $purchaseItem->total_amount);
        $this->assertSame(24300.0, (float) $purchaseItem->cgst_amount);
        $this->assertSame(24300.0, (float) $purchaseItem->sgst_amount);
        $this->assertSame(300000.0, (float) $order->subtotal);
        $this->assertSame(30000.0, (float) $order->discount_amount);
        $this->assertSame(48600.0, (float) $order->tax_amount);
        $this->assertSame(318600.0, (float) $order->grand_total);

        $this->get(route('quotations.purchase-details', $quotation->id))
            ->assertOk()
            ->assertSee('10.00')
            ->assertSee('₹318,600.00')
            ->assertDontSee('Payment Plan / Installment Schedule');

        $this->put(route('quotations.purchase-discounts.update', $quotation->id), [
            'items' => [$purchaseItem->id => ['discount_percent' => '0.00']],
        ])->assertRedirect();
        $this->assertSame(354000.0, (float) $order->fresh()->grand_total);
        $this->put(route('quotations.purchase-discounts.update', $quotation->id), [
            'items' => [$purchaseItem->id => ['discount_percent' => '10.00']],
        ])->assertRedirect();
        $this->assertSame(318600.0, (float) $order->fresh()->grand_total);

        $this->post(route('quotations.purchase-payments.store', $quotation->id), [
            'payment_date' => now()->toDateString(),
            'payment_input_mode' => 'amount',
            'amount' => '100000.00',
            'submission_token' => (string) Str::uuid(),
        ])->assertRedirect();
        $this->put(route('quotations.purchase-discounts.update', $quotation->id), [
            'items' => [$purchaseItem->id => ['discount_percent' => '100.00']],
        ])->assertSessionHasErrors('items');
        $this->assertSame(318600.0, (float) $order->fresh()->grand_total);
    }

    public function test_installment_plan_tracks_partial_and_completed_payments_separately_from_purchase_balance()
    {
        Carbon::setTestNow(Carbon::parse('2026-10-14 12:00:00'));
        try {
            list($quotation) = $this->approvedQuotation(null, false);
            $this->actingAs($this->owner)->post(route('quotations.approve', $quotation->id))->assertSessionHasNoErrors();
            $quotation = $quotation->fresh('items');
            $items = [];
            foreach ($quotation->items as $item) {
                $items[$item->id] = ['purchase_rate' => 100, 'discount_percent' => 0];
            }

            $this->actingAs($this->owner)
                ->post(route('quotations.purchase-orders.generate', $quotation->id), ['items' => $items])
                ->assertRedirect(route('quotations.purchase-details', $quotation->id));
            $order = PurchaseOrder::where('quotation_id', $quotation->id)->firstOrFail();
            $order->grand_total = 1300000;
            $order->save();

            $unprivileged = User::create([
                'name' => 'Unprivileged User',
                'email' => Str::uuid() . '@example.test',
                'password' => Hash::make('test-password'),
                'role' => 'STAFF',
            ]);
            $this->actingAs($unprivileged)
                ->post(route('quotations.purchase-installments.store', $quotation->id), [
                    'due_date' => '2026-10-15',
                    'planned_amount' => 20000,
                    'submission_token' => (string) Str::uuid(),
                ])->assertForbidden();

            $firstInstallmentToken = (string) Str::uuid();
            $this->actingAs($this->owner)->post(route('quotations.purchase-installments.store', $quotation->id), [
                'due_date' => '2026-10-15',
                'planned_amount' => 20000,
                'submission_token' => $firstInstallmentToken,
            ])->assertRedirect();
            $this->post(route('quotations.purchase-installments.store', $quotation->id), [
                'due_date' => '2026-10-16',
                'planned_amount' => 20000,
                'submission_token' => (string) Str::uuid(),
            ])->assertRedirect();
            $this->post(route('quotations.purchase-installments.store', $quotation->id), [
                'due_date' => '2026-10-15',
                'planned_amount' => 20000,
                'submission_token' => $firstInstallmentToken,
            ])->assertRedirect();
            $this->assertSame(2, $order->installments()->count());
            $this->post(route('quotations.purchase-installments.store', $quotation->id), [
                'due_date' => '2026-10-17',
                'planned_amount' => 1260000.01,
                'submission_token' => (string) Str::uuid(),
            ])->assertSessionHasErrors('planned_amount');
            $this->post(route('quotations.purchase-installments.store', $quotation->id), [
                'due_date' => '2026-10-17',
                'planned_amount' => 1000,
                'submission_token' => (string) Str::uuid(),
            ])->assertRedirect();
            $cancelledInstallment = $order->installments()->where('installment_number', 3)->firstOrFail();
            $this->delete(route('quotations.purchase-installments.cancel', [$quotation->id, $cancelledInstallment->id]))
                ->assertRedirect();
            $this->assertSame('Cancelled', $cancelledInstallment->fresh()->status());

            $installments = $order->installments()->with('payments')->get();
            $first = $installments->first();
            $second = $installments->where('installment_number', 2)->first();
            $this->assertSame('Scheduled', $first->status());
            $this->assertSame('Scheduled', $second->status());
            Carbon::setTestNow(Carbon::parse('2026-10-15 12:00:00'));
            $this->assertSame('Due', $first->status());
            $this->assertSame('Scheduled', $second->status());
            Carbon::setTestNow(Carbon::parse('2026-10-16 12:00:00'));
            $this->assertSame('Overdue', $first->status());
            $this->assertSame('Due', $second->status());
            Carbon::setTestNow(Carbon::parse('2026-10-14 12:00:00'));

            $this->get(route('quotations.purchase-details', $quotation->id))
                ->assertOk()
                ->assertDontSee('Payment Plan / Installment Schedule')
                ->assertDontSee('Add Installment');

            $this->post(route('quotations.purchase-payments.store', $quotation->id), [
                'installment_id' => $first->id,
                'payment_date' => '2026-10-15',
                'amount' => 20000,
                'submission_token' => (string) Str::uuid(),
            ])->assertSessionHasErrors('payment_date');

            $this->post(route('quotations.purchase-payments.store', $quotation->id), [
                'installment_id' => $first->id,
                'payment_date' => '2026-10-14',
                'amount' => 20000,
                'submission_token' => (string) Str::uuid(),
            ])->assertRedirect();

            $first->refresh()->load('payments');
            $second->refresh()->load('payments');
            $this->assertSame('Paid', $first->status());
            $this->assertSame('Scheduled', $second->status());
            $this->get(route('quotations.purchase-details', $quotation->id))
                ->assertOk()
                ->assertDontSee('Payment completion:')
                ->assertSee('₹20,000.00')
                ->assertSee('₹1,280,000.00')
                ->assertSee('1.54%')
                ->assertSee('14 Oct 2026');
            $this->post(route('quotations.purchase-payments.store', $quotation->id), [
                'installment_id' => $first->id,
                'payment_date' => '2026-10-14',
                'amount' => 0.01,
                'submission_token' => (string) Str::uuid(),
            ])->assertSessionHasErrors('amount');

            $this->post(route('quotations.purchase-payments.store', $quotation->id), [
                'installment_id' => $second->id,
                'payment_date' => '2026-10-14',
                'amount' => 10000,
                'submission_token' => (string) Str::uuid(),
            ])->assertRedirect();
            $second->refresh()->load('payments');
            $this->assertSame('Partially Paid', $second->status());
            $this->get(route('quotations.purchase-details', $quotation->id))
                ->assertOk()
                ->assertDontSee('Payment completion:');

            $this->post(route('quotations.purchase-payments.store', $quotation->id), [
                'installment_id' => $second->id,
                'payment_date' => '2026-10-14',
                'amount' => 10000,
                'submission_token' => (string) Str::uuid(),
            ])->assertRedirect();
            $first->refresh()->load('payments');
            $second->refresh()->load('payments');
            $this->assertSame('Paid', $first->status());
            $this->assertSame('Paid', $second->status());
            $this->assertSame(40000.0, (float) DB::table('purchase_payments')->where('purchase_order_id', $order->id)->sum('amount'));

            $this->get(route('quotations.purchase-details', $quotation->id))
                ->assertOk()
                ->assertSee('₹1,260,000.00')
                ->assertSee('₹40,000.00')
                ->assertSee('₹0.00')
                ->assertSee('3.08%')
                ->assertSee('Paid')
                ->assertDontSee('Payment Plan / Installment Schedule');
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_approval_confirmation_approves_one_supplier_without_creating_a_purchase_order()
    {
        list($quotation, $other) = $this->approvedQuotation(null, false);

        $this->actingAs($this->owner)
            ->post(route('quotations.approve', $quotation->id))
            ->assertRedirect(route('quotations.show', $quotation->quotation_request_id))
            ->assertSessionHas('approval_quotation_id', $quotation->id);

        $quotation->refresh();
        $this->assertSame('approved', $quotation->status);
        $this->assertSame($this->owner->id, (int) $quotation->approved_by);
        $this->assertSame('rejected', $other->fresh()->status);
        $this->assertNull($quotation->purchase_order_id);
        $this->assertSame(0, PurchaseOrder::where('quotation_id', $quotation->id)->count());
        $this->get(route('quotations.purchase-details', $quotation->id))->assertOk();
    }

    public function test_supplier_quotation_bill_copy_is_stored_and_available_for_view_and_download()
    {
        Storage::fake('local');
        list($quotation) = $this->approvedQuotation(UploadedFile::fake()->image('supplier-quotation.jpg'));

        $quotation->refresh();
        $this->assertNotEmpty($quotation->bill_copy_path);
        $this->assertSame('supplier-quotation.jpg', $quotation->bill_copy_name);
        Storage::disk('local')->assertExists($quotation->bill_copy_path);

        $this->actingAs($this->owner)
            ->get(route('quotations.show', $quotation->quotation_request_id))
            ->assertOk()
            ->assertSee('Supplier bill copy')
            ->assertSee('supplier-quotation.jpg')
            ->assertSee('CGST 9.00% · SGST 9.00%')
            ->assertDontSee('<th>CGST %</th>', false)
            ->assertSee(route('quotations.bill-copy', $quotation->id), false);

        $this->get(route('quotations.bill-copy', $quotation->id))
            ->assertOk()
            ->assertHeader('content-type', 'image/jpeg');
        $this->get(route('quotations.bill-copy', [$quotation->id, 'download' => 1]))
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename=supplier-quotation.jpg');
    }

    public function test_partial_receipt_with_damaged_goods_only_adds_accepted_stock()
    {
        $order = $this->purchaseOrder();
        $this->receive($order, 6, 1, 1)->assertRedirect();

        $item = GoodsReceipt::where('purchase_order_id', $order->id)->firstOrFail()->items->first();
        $this->assertSame(6.0, (float) $item->received_quantity);
        $this->assertSame(2.0, (float) $item->rejected_quantity);
        $this->assertSame(1.0, (float) $item->damaged_quantity);
        $this->assertSame(4.0, (float) $item->accepted_quantity);
        $this->assertSame(4.0, (float) $this->product->fresh()->current_stock);
        $this->assertSame('partially_received', $order->fresh()->status);

        $this->receive($order, 4, 3, 2)->assertSessionHasErrors('items');
        $this->assertSame(4.0, (float) $this->product->fresh()->current_stock);
    }

    public function test_matched_bill_is_approved_and_paid_in_installments_with_overpayment_blocked()
    {
        $order = $this->purchaseOrder();
        $this->receive($order, 10)->assertRedirect();

        $this->actingAs($this->officer)->post(route('supplier-bills.store', $order->id), $this->billPayload($order, 10, '1180.00'))->assertRedirect();
        $bill = SupplierBill::where('purchase_order_id', $order->id)->firstOrFail();
        $this->assertSame('under_verification', $bill->status);
        $this->assertSame('matched', $bill->match_status);
        $this->assertSame('1180.00', (string) $bill->total_amount);

        // Officer lacks approval rights; owner approves.
        $this->post(route('supplier-bills.approve', $bill->id))->assertForbidden();
        $this->actingAs($this->owner)->post(route('supplier-bills.approve', $bill->id))->assertSessionHasNoErrors();
        $this->assertSame('partially_paid', $bill->fresh()->status);
        $this->assertSame('200.00', $bill->fresh()->paid_amount);
        $this->assertSame('980.00', $bill->fresh()->outstanding_amount);

        // First installment.
        $first = $this->requestPayment($bill, '500.00');
        $this->assertSame('pending_approval', $first->status);
        // A second request cannot exceed what remains after the pending one.
        $this->actingAs($this->officer)->post(route('supplier-payments.store'), $this->paymentPayload($bill, '500.01'))->assertSessionHasErrors('allocations');

        // Requester cannot approve own payment (admin requested here to test separation of duties).
        $own = $this->requestPayment($bill, '100.00', $this->admin);
        $this->actingAs($this->admin)->post(route('supplier-payments.approve', $own->id))->assertSessionHasErrors('payment');
        $this->post(route('supplier-payments.cancel', $own->id), ['reason' => 'Raised by mistake'])->assertSessionHasNoErrors();

        $this->approveAndComplete($first, 'UTR0001');
        $bill->refresh();
        $this->assertSame('partially_paid', $bill->status);
        $this->assertSame('480.00', $bill->outstanding_amount);

        // Duplicate UTR on another payment is refused.
        $second = $this->requestPayment($bill, '480.00');
        $this->actingAs($this->owner)->post(route('supplier-payments.approve', $second->id));
        $this->actingAs($this->officer)->post(route('supplier-payments.complete', $second->id), [
            'payment_date' => now()->toDateString(),
            'reference_number' => 'utr0001',
        ])->assertSessionHasErrors('reference_number');
        $this->post(route('supplier-payments.complete', $second->id), [
            'payment_date' => now()->toDateString(),
            'reference_number' => 'UTR0002',
        ])->assertSessionHasNoErrors();

        $bill->refresh();
        $this->assertSame('paid', $bill->status);
        $this->assertSame('0.00', $bill->outstanding_amount);
        $this->actingAs($this->officer)->post(route('supplier-payments.store'), $this->paymentPayload($bill, '1.00'))->assertSessionHasErrors('allocations');

        // Reversal restores the balance without deleting the record.
        $this->actingAs($this->owner)->post(route('supplier-payments.reverse', $second->id), ['reason' => 'Bounced'])->assertSessionHasNoErrors();
        $bill->refresh();
        $this->assertSame('partially_paid', $bill->status);
        $this->assertSame('480.00', $bill->outstanding_amount);
        $this->assertSame('reversed', $second->fresh()->status);
    }

    public function test_failed_payment_does_not_reduce_outstanding_and_submission_is_idempotent()
    {
        $bill = $this->approvedBill();
        $payload = $this->paymentPayload($bill, '980.00');
        $this->actingAs($this->officer)->post(route('supplier-payments.store'), $payload)->assertRedirect();
        $this->post(route('supplier-payments.store'), $payload)->assertRedirect();
        $this->assertSame(1, SupplierPayment::where('submission_token', $payload['submission_token'])->count());

        $payment = SupplierPayment::where('submission_token', $payload['submission_token'])->firstOrFail();
        $this->actingAs($this->owner)->post(route('supplier-payments.approve', $payment->id));
        $this->actingAs($this->officer)->post(route('supplier-payments.fail', $payment->id), ['reason' => 'Bank rejected'])->assertSessionHasNoErrors();

        $bill->refresh();
        $this->assertSame('failed', $payment->fresh()->status);
        $this->assertSame('partially_paid', $bill->status);
        $this->assertSame('980.00', $bill->outstanding_amount);
        // The failed request no longer reserves the balance.
        $this->requestPayment($bill, '980.00');
    }

    public function test_later_purchase_payment_is_applied_to_an_approved_supplier_bill()
    {
        $bill = $this->approvedBill();
        $this->assertSame('980.00', $bill->outstanding_amount);
        $this->get(route('supplier-bills.show', $bill->id))->assertSee('PO Advance #');

        $order = PurchaseOrder::with('quotation')->findOrFail($bill->purchase_order_id);
        $this->actingAs($this->owner)->post(
            route('quotations.purchase-payments.store', $order->quotation_id),
            [
                'payment_date' => now()->toDateString(),
                'amount' => '100.00',
                'payment_method' => 'UPI',
                'submission_token' => (string) Str::uuid(),
            ]
        )->assertRedirect(route('quotations.purchase-details', $order->quotation_id));

        $bill->refresh();
        $this->assertSame('300.00', $bill->paid_amount);
        $this->assertSame('880.00', $bill->outstanding_amount);
        $this->actingAs($this->officer)
            ->post(route('supplier-payments.store'), $this->paymentPayload($bill, '880.01'))
            ->assertSessionHasErrors('allocations');
        $this->actingAs($this->owner)->get(route('purchase-orders.show', $order->id))
            ->assertOk()
            ->assertSee('₹300.00');
    }

    public function test_quantity_mismatch_puts_bill_on_hold_and_requires_reasoned_exception_approval()
    {
        $order = $this->purchaseOrder();
        $this->receive($order, 5)->assertRedirect();

        $this->actingAs($this->officer)->post(route('supplier-bills.store', $order->id), $this->billPayload($order, 10, '1180.00'))->assertRedirect();
        $bill = SupplierBill::where('purchase_order_id', $order->id)->firstOrFail();
        $this->assertSame('on_hold', $bill->status);
        $this->assertSame('mismatch', $bill->match_status);
        $this->assertSame(['quantity'], collect($bill->match_result['issues'])->pluck('type')->all());

        $this->actingAs($this->owner)->post(route('supplier-bills.approve', $bill->id))->assertSessionHasErrors('reason');
        $this->assertSame('on_hold', $bill->fresh()->status);
        // Approved bills only: payment against an on-hold bill is refused.
        $this->actingAs($this->officer)->post(route('supplier-payments.store'), $this->paymentPayload($bill, '10.00'))->assertSessionHasErrors('allocations');

        $this->actingAs($this->owner)->post(route('supplier-bills.approve', $bill->id), ['reason' => 'Balance arriving tomorrow, agreed by owner'])->assertSessionHasNoErrors();
        $bill->refresh();
        $this->assertSame('partially_paid', $bill->status);
        $this->assertSame('200.00', $bill->paid_amount);
        $this->assertTrue($bill->exception_approved);
        $this->assertTrue(ProcurementAuditLog::forEntity($bill)->where('event', 'bill_exception_approved')->exists());
    }

    public function test_price_tax_and_total_differences_are_flagged_and_correction_rematches()
    {
        $order = $this->purchaseOrder();
        $this->receive($order, 10)->assertRedirect();
        $payload = $this->billPayload($order, 10, '1300.00');
        $itemId = $order->items->first()->id;
        $payload['items'][$itemId]['rate'] = '105';
        $payload['items'][$itemId]['tax_percent'] = '12';

        $this->actingAs($this->officer)->post(route('supplier-bills.store', $order->id), $payload)->assertRedirect();
        $bill = SupplierBill::where('purchase_order_id', $order->id)->firstOrFail();
        $this->assertSame('on_hold', $bill->status);
        $this->assertEqualsCanonicalizing(['price', 'tax', 'total'], collect($bill->match_result['issues'])->pluck('type')->all());

        $this->get(route('supplier-bills.show', $bill->id))->assertOk()->assertSee('differs from PO rate');
        $this->get(route('supplier-bills.edit', $bill->id))->assertOk()->assertSee('Reason for correction');
        $this->get(route('supplier-bills.create', $order->id))->assertOk()->assertSee('Submit Bill for Verification');

        $corrected = $this->billPayload($order, 10, '1180.00');
        unset($corrected['submission_token']);
        $corrected['correction_reason'] = 'Supplier issued corrected invoice';
        $this->put(route('supplier-bills.update', $bill->id), $corrected)->assertSessionHasNoErrors();
        $bill->refresh();
        $this->assertSame('under_verification', $bill->status);
        $this->assertSame('matched', $bill->match_status);
        $this->assertTrue(ProcurementAuditLog::forEntity($bill)->where('event', 'bill_corrected')->exists());
    }

    public function test_duplicate_supplier_invoice_is_refused_until_the_original_is_rejected()
    {
        $order = $this->purchaseOrder();
        $this->receive($order, 10)->assertRedirect();
        $this->actingAs($this->officer)->post(route('supplier-bills.store', $order->id), $this->billPayload($order, 5, '590.00', 'INV-001'))->assertRedirect();
        $bill = SupplierBill::where('purchase_order_id', $order->id)->firstOrFail();

        // Same invoice number written differently is still a duplicate.
        $this->post(route('supplier-bills.store', $order->id), $this->billPayload($order, 5, '590.00', 'inv 001'))->assertSessionHasErrors('invoice_number');
        $this->assertSame(1, SupplierBill::where('purchase_order_id', $order->id)->count());

        // Idempotent replay of the same submission does not create a second bill.
        $this->actingAs($this->officer);
        $replay = $this->billPayload($order, 5, '590.00', 'INV-002');
        $this->post(route('supplier-bills.store', $order->id), $replay)->assertRedirect();
        $this->post(route('supplier-bills.store', $order->id), $replay)->assertRedirect();
        $this->assertSame(2, SupplierBill::where('purchase_order_id', $order->id)->count());

        $this->actingAs($this->owner)->post(route('supplier-bills.reject', $bill->id), ['reason' => 'Wrong GSTIN'])->assertSessionHasNoErrors();
        $this->assertSame('rejected', $bill->fresh()->status);
        $this->actingAs($this->officer)->post(route('supplier-bills.store', $order->id), $this->billPayload($order, 5, '590.00', 'INV-001'))->assertRedirect();
        $this->assertSame(3, SupplierBill::where('purchase_order_id', $order->id)->count());
    }

    public function test_bill_creator_cannot_approve_their_own_bill_even_as_admin()
    {
        $order = $this->purchaseOrder();
        $this->receive($order, 10)->assertRedirect();
        $this->actingAs($this->admin)->post(route('supplier-bills.store', $order->id), $this->billPayload($order, 10, '1180.00'))->assertRedirect();
        $bill = SupplierBill::where('purchase_order_id', $order->id)->firstOrFail();

        $this->post(route('supplier-bills.approve', $bill->id))->assertSessionHasErrors('bill');
        $this->assertSame('under_verification', $bill->fresh()->status);
    }

    public function test_payment_history_aggregates_transactions_and_filters_bill_statuses()
    {
        $bill = $this->approvedBill();
        $bill->update([
            'paid_amount' => 200,
            'status' => SupplierBill::STATUS_PARTIALLY_PAID,
            'due_date' => now()->subDay()->toDateString(),
        ]);
        $unpaidBill = SupplierBill::create([
            'bill_number' => 'BILL-HISTORY-' . strtoupper(Str::random(8)),
            'submission_token' => (string) Str::uuid(),
            'supplier_id' => $bill->supplier_id,
            'purchase_order_id' => $bill->purchase_order_id,
            'invoice_number' => 'HISTORY-' . strtoupper(Str::random(8)),
            'duplicate_key' => SupplierBill::duplicateKey($bill->supplier_id, 'HISTORY-' . Str::random(8)),
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->subDay()->toDateString(),
            'subtotal' => 500,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 500,
            'invoice_total' => 500,
            'paid_amount' => 0,
            'status' => SupplierBill::STATUS_APPROVED,
            'created_by' => $this->officer->id,
        ]);

        $pending = $this->requestPayment($bill, '100.00');
        $this->actingAs($this->owner)
            ->get(route('supplier-bills.index', ['filter' => 'pending_approval']))
            ->assertOk()
            ->assertSee($pending->payment_number)
            ->assertSee('Pending Approval');
        $this->actingAs($this->owner)
            ->get(route('supplier-bills.index', ['filter' => 'pending_approval', 'search' => 'no-matching-payment']))
            ->assertOk()
            ->assertSee('No payment history found');

        $this->actingAs($this->owner)
            ->get(route('supplier-bills.index', ['filter' => 'unpaid']))
            ->assertOk()
            ->assertSee($unpaidBill->bill_number)
            ->assertDontSee($pending->payment_number);

        $this->actingAs($this->owner)
            ->get(route('supplier-bills.index', ['filter' => 'overdue']))
            ->assertOk()
            ->assertSee($bill->bill_number)
            ->assertSee($unpaidBill->bill_number);

        $this->approveAndComplete($pending, 'HISTORY-REF-1');
        $this->actingAs($this->owner)
            ->get(route('supplier-bills.index', [
                'filter' => 'partially_paid',
                'search' => $bill->supplier->name,
                'supplier_id' => $bill->supplier_id,
                'from_date' => now()->toDateString(),
                'to_date' => now()->toDateString(),
            ]))
            ->assertOk()
            ->assertSee($pending->payment_number)
            ->assertSee('₹1,180.00')
            ->assertSee('25.42%');
        $confirmedPaid = \App\Support\Money::fromMinor(
            \App\Support\Money::toMinor(DB::table('purchase_payments')->where('status', 'paid')->sum('amount'))
            + \App\Support\Money::toMinor(DB::table('supplier_payments')->where('status', SupplierPayment::STATUS_COMPLETED)->sum('amount'))
        );
        $billOutstandingMinor = SupplierBill::whereIn('status', SupplierBill::PAYABLE_STATUSES)->get()
            ->sum(function ($payableBill) {
                return max(0, $payableBill->outstandingMinor());
            });
        $paymentAllocations = app(\App\Services\PurchasePaymentAllocationService::class);
        $unbilledOutstandingMinor = PurchaseOrder::where('status', '<>', 'cancelled')
            ->whereDoesntHave('supplierBills')
            ->get()
            ->sum(function ($unbilledOrder) use ($paymentAllocations) {
                return max(0, \App\Support\Money::toMinor($unbilledOrder->grand_total)
                    - $paymentAllocations->paidMinorForOrder($unbilledOrder->id));
            });
        $dashboard = $this->actingAs($this->owner)->get(route('supplier-bills.index'))->assertOk();
        $dashboard->assertSee('₹' . number_format((float) $confirmedPaid, 2))
            ->assertSee('₹' . number_format((float) \App\Support\Money::fromMinor($billOutstandingMinor + $unbilledOutstandingMinor), 2));
        $purchasePayment = PurchasePayment::where('purchase_order_id', $bill->purchase_order_id)->firstOrFail();
        $this->assertSame(
            1,
            substr_count($dashboard->getContent(), 'PP-' . str_pad($purchasePayment->id, 8, '0', STR_PAD_LEFT))
        );
        $this->get(route('payment-history.purchase-payments.show', $purchasePayment->id))
            ->assertOk()
            ->assertSee('Purchase Payment Details')
            ->assertSee('Related Purchase Payment History');

        $nextPayment = $this->requestPayment($bill, '880.00');
        $this->approveAndComplete($nextPayment, 'HISTORY-REF-2');
        $this->actingAs($this->owner)
            ->get(route('supplier-bills.index', ['filter' => 'paid']))
            ->assertOk()
            ->assertSee($nextPayment->payment_number)
            ->assertSee('₹1,180.00');

        $export = $this->actingAs($this->owner)
            ->get(route('supplier-bills.index', ['filter' => 'all', 'export' => 'csv']))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->assertHeader('content-disposition');
        ob_start();
        $export->baseResponse->sendContent();
        $csv = ob_get_clean();
        $this->assertStringContainsString('Payment Voucher No.', $csv);
        $this->assertStringContainsString($pending->payment_number, $csv);
        $this->assertSame(1, substr_count($csv, 'PP-' . str_pad($purchasePayment->id, 8, '0', STR_PAD_LEFT)));
    }

    public function test_role_permissions_are_enforced_on_new_endpoints()
    {
        $bill = $this->approvedBill();
        $payment = $this->requestPayment($bill, '100.00');

        $this->actingAs($this->owner);
        $this->get(route('supplier-bills.index'))->assertOk()->assertSee($bill->bill_number);
        $this->get(route('supplier-bills.show', $bill->id))->assertOk();
        $this->get(route('procurement.approvals'))->assertOk()->assertSee($payment->payment_number);
        $this->get(route('suppliers.purchase-history', $bill->supplier_id))->assertOk()->assertSee($bill->bill_number);
        $this->get(route('supplier-bills.create', $bill->purchase_order_id))->assertForbidden();
        $this->post(route('supplier-payments.store'), $this->paymentPayload($bill, '1.00'))->assertForbidden();
        $this->post(route('supplier-payments.complete', $payment->id), [])->assertForbidden();

        $this->actingAs($this->officer);
        $this->get(route('supplier-payments.index'))->assertOk()->assertSee($payment->payment_number);
        $this->get(route('supplier-payments.show', $payment->id))->assertOk();
        $this->get(route('supplier-payments.create', ['supplier_id' => $bill->supplier_id]))->assertOk()->assertSee($bill->bill_number);
        $this->get(route('procurement.approvals'))->assertForbidden();
        $this->post(route('supplier-payments.approve', $payment->id))->assertForbidden();

        $unprivileged = User::create([
            'name' => 'Payment History No Permission',
            'email' => Str::uuid() . '@example.test',
            'password' => Hash::make('test-password'),
            'role' => 'UNPRIVILEGED',
        ]);
        $purchasePayment = PurchasePayment::where('purchase_order_id', $bill->purchase_order_id)->firstOrFail();
        $this->actingAs($unprivileged)->get(route('supplier-bills.index'))->assertForbidden();
        $this->get(route('supplier-bills.show', $bill->id))->assertForbidden();
        $this->get(route('supplier-payments.show', $payment->id))->assertForbidden();
        $this->get(route('payment-history.purchase-payments.show', $purchasePayment->id))->assertForbidden();
        $this->post(route('supplier-payments.reverse', $payment->id), ['reason' => 'x'])->assertForbidden();
        $this->post(route('supplier-bills.reject', $bill->id), ['reason' => 'x'])->assertForbidden();
        $this->post(route('supplier-bills.cancel', $bill->id), ['reason' => 'x'])->assertForbidden();
        $this->post(route('purchase-orders.cancel', $bill->purchase_order_id), ['reason' => 'x'])->assertForbidden();

        auth()->logout();
        $this->get(route('supplier-bills.index'))->assertRedirect(route('login'));
    }

    public function test_purchase_order_cancel_and_close_rules()
    {
        $fresh = $this->purchaseOrder();
        $this->actingAs($this->owner)->post(route('purchase-orders.cancel', $fresh->id), ['reason' => 'Supplier cannot deliver'])->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $fresh->fresh()->status);
        $this->receive($fresh, 1)->assertSessionHasErrors('items');

        $received = $this->purchaseOrder();
        $this->receive($received, 4)->assertRedirect();
        $this->actingAs($this->owner)->post(route('purchase-orders.cancel', $received->id), ['reason' => 'Too late'])->assertSessionHasErrors('purchase_order');
        $this->post(route('purchase-orders.close', $received->id), ['reason' => 'Short-closed'])->assertSessionHasNoErrors();
        $this->assertSame('closed', $received->fresh()->status);
        $this->receive($received, 1)->assertSessionHasErrors('items');

        $this->actingAs($this->owner)->get(route('purchase-orders.show', $received->id))
            ->assertOk()
            ->assertDontSee('<h2>Goods Received</h2>', false)
            ->assertDontSee('<h2>Supplier Bills &amp; Payments</h2>', false)
            ->assertSee('Closed');
    }

    public function test_cannot_cancel_a_bill_with_pending_payment_and_audit_rows_are_immutable()
    {
        $bill = $this->approvedBill();
        $this->requestPayment($bill, '100.00');
        $this->actingAs($this->owner)->post(route('supplier-bills.cancel', $bill->id), ['reason' => 'Duplicate'])->assertSessionHasErrors('bill');
        $this->assertSame('partially_paid', $bill->fresh()->status);

        $entry = ProcurementAuditLog::forEntity($bill)->firstOrFail();
        try {
            $entry->update(['reason' => 'tampered']);
            $this->fail('Audit log update should be blocked.');
        } catch (\LogicException $exception) {
            $this->assertStringContainsString('cannot be modified', $exception->getMessage());
        }
        $this->expectException(\LogicException::class);
        $entry->delete();
    }

    private function approvedQuotation($billCopy = null, $approve = true, $discountPercent = 0, $supplierInvoiceNumber = null)
    {
        $this->actingAs($this->officer)->post(route('quotations.store'), [
            'request_date' => now()->toDateString(),
            'store_id' => $this->store,
            'supplier_ids' => $this->suppliers->pluck('id')->all(),
            'items' => [['category_id' => $this->product->category_id, 'product_id' => $this->product->id, 'quantity' => 10]],
        ])->assertRedirect();
        $request = QuotationRequest::orderByDesc('id')->firstOrFail();
        $items = [];
        $supplierInvoiceNumbers = [];
        foreach ($request->quotations as $quotation) {
            if ($supplierInvoiceNumber !== null) {
                $supplierInvoiceNumbers[$quotation->id] = $supplierInvoiceNumber;
            }
            foreach ($quotation->items as $item) {
                $items[$quotation->id][$item->id] = [
                    'supplier_rate' => 100,
                    'discount_percent' => $discountPercent,
                    'gst_rate' => 18,
                ];
            }
        }
        $payload = ['items' => $items];
        if ($supplierInvoiceNumbers) {
            $payload['supplier_invoice_numbers'] = $supplierInvoiceNumbers;
        }
        if ($billCopy) {
            $firstDraftQuotationId = $request->quotations()->orderBy('id')->value('id');
            $payload['bill_copies'] = [$firstDraftQuotationId => $billCopy];
        }
        $this->post(route('quotations.request.submit', $request->id), $payload)->assertRedirect();
        list($approved, $other) = $request->quotations()->orderBy('id')->get()->all();
        if ($approve) {
            $this->actingAs($this->owner)->post(route('quotations.approve', $approved->id))->assertSessionHasNoErrors();
        }

        return [Quotation::find($approved->id), Quotation::find($other->id)];
    }

    private function purchaseOrder()
    {
        list($approved) = $this->approvedQuotation();
        $lines = [];
        foreach ($approved->items as $item) {
            $lines[$item->id] = ['purchase_rate' => $item->supplier_rate, 'discount_percent' => 0];
        }
        $this->actingAs($this->owner)->post(route('quotations.purchase-payments.store', $approved->id), [
            'payment_date' => now()->toDateString(),
            'amount' => 200,
            'payment_method' => 'Bank Transfer',
            'submission_token' => (string) Str::uuid(),
            'items' => $lines,
        ])->assertRedirect();
        $order = PurchaseOrder::with('items')->where('quotation_id', $approved->id)->firstOrFail();
        $this->post(route('purchase-orders.release', $order->id))->assertRedirect();

        return $order->fresh('items');
    }

    private function receive(PurchaseOrder $order, $received, $rejected = 0, $damaged = 0)
    {
        return $this->actingAs($this->officer)->post(route('goods-receipts.store', $order->id), [
            'store_id' => $this->store,
            'received_date' => now()->toDateString(),
            'submission_token' => (string) Str::uuid(),
            'items' => [[
                'purchase_order_item_id' => $order->items->first()->id,
                'received_quantity' => $received,
                'rejected_quantity' => $rejected,
                'damaged_quantity' => $damaged,
            ]],
        ]);
    }

    private function billPayload(PurchaseOrder $order, $quantity, $invoiceTotal, $invoiceNumber = null)
    {
        $item = $order->items->first();

        return [
            'submission_token' => (string) Str::uuid(),
            'invoice_number' => $invoiceNumber ?: 'INV-' . strtoupper(Str::random(8)),
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'invoice_total' => $invoiceTotal,
            'items' => [$item->id => [
                'quantity' => $quantity,
                'rate' => (string) $item->purchase_rate,
                'discount_percent' => '0',
                'tax_percent' => (string) $item->tax_percent,
            ]],
        ];
    }

    private function approvedBill()
    {
        $order = $this->purchaseOrder();
        $this->receive($order, 10)->assertRedirect();
        $this->actingAs($this->officer)->post(route('supplier-bills.store', $order->id), $this->billPayload($order, 10, '1180.00'))->assertRedirect();
        $bill = SupplierBill::where('purchase_order_id', $order->id)->firstOrFail();
        $this->actingAs($this->owner)->post(route('supplier-bills.approve', $bill->id))->assertSessionHasNoErrors();

        return $bill->fresh();
    }

    private function paymentPayload(SupplierBill $bill, $amount)
    {
        return [
            'submission_token' => (string) Str::uuid(),
            'supplier_id' => $bill->supplier_id,
            'payment_method' => 'neft',
            'allocations' => [$bill->id => $amount],
        ];
    }

    private function requestPayment(SupplierBill $bill, $amount, User $as = null)
    {
        $payload = $this->paymentPayload($bill, $amount);
        $this->actingAs($as ?: $this->officer)->post(route('supplier-payments.store'), $payload)->assertSessionHasNoErrors();

        return SupplierPayment::where('submission_token', $payload['submission_token'])->firstOrFail();
    }

    private function approveAndComplete(SupplierPayment $payment, $reference)
    {
        $this->actingAs($this->owner)->post(route('supplier-payments.approve', $payment->id))->assertSessionHasNoErrors();
        $this->actingAs($this->officer)->post(route('supplier-payments.complete', $payment->id), [
            'payment_date' => now()->toDateString(),
            'reference_number' => $reference,
        ])->assertSessionHasNoErrors();
        $this->assertSame('completed', $payment->fresh()->status);
    }

    public function test_supplier_quotation_discount_is_applied_before_gst()
    {
        list($quotation) = $this->approvedQuotation(null, false, 10);
        $quotation->refresh();
        $item = $quotation->items->first();

        $this->assertSame(10.0, (float) $item->discount_percent);
        $this->assertSame(100.0, (float) $quotation->discount_total);
        $this->assertSame(162.0, (float) $quotation->tax_total);
        $this->assertSame(1062.0, (float) $quotation->grand_total);

        $this->get(route('quotations.show', $quotation->quotation_request_id))
            ->assertOk()
            ->assertSee('DISCOUNT %')
            ->assertSee('Discount')
            ->assertSee('₹100.00');

        $this->actingAs($this->owner)
            ->post(route('quotations.approve', $quotation->id))
            ->assertSessionHasNoErrors();
        $this->get(route('quotations.purchase-details', $quotation->id))
            ->assertOk()
            ->assertSee('name="items[' . $item->id . '][discount_percent]" value="10.00"', false);

        $purchaseDetails = $this->get(route('quotations.purchase-details', $quotation->id))
            ->assertOk();
        $this->assertSame(
            1,
            substr_count($purchaseDetails->getContent(), 'name="items[' . $item->id . '][discount_percent]"')
        );

        $this->actingAs($this->owner)
            ->post(route('quotations.purchase-orders.generate', $quotation->id), [
                'items' => [
                    $item->id => [
                        'purchase_rate' => '100.00',
                        'discount_percent' => '5.00',
                    ],
                ],
            ])
            ->assertRedirect(route('quotations.purchase-details', $quotation->id));

        $purchaseItem = PurchaseOrder::where('quotation_id', $quotation->id)
            ->with('items')
            ->firstOrFail()
            ->items
            ->first();
        $this->assertSame(5.0, (float) $purchaseItem->discount_percent);
        $this->assertSame(1121.0, (float) $purchaseItem->total_amount);
        $this->get(route('quotations.purchase-details', $quotation->id))
            ->assertOk()
            ->assertSee('name="items[' . $purchaseItem->id . '][discount_percent]" value="5.00"', false)
            ->assertSee('₹1,121.00');
    }

    public function test_supplier_invoice_number_entered_with_quotation_prefills_goods_receipt()
    {
        $invoiceNumber = 'SUP-INV-' . strtoupper(Str::random(8));
        list($quotation) = $this->approvedQuotation(null, true, 0, $invoiceNumber);

        $this->assertSame($invoiceNumber, $quotation->fresh()->supplier_invoice_number);

        $items = [];
        foreach ($quotation->items as $item) {
            $items[$item->id] = [
                'purchase_rate' => number_format((float) $item->supplier_rate, 2, '.', ''),
                'discount_percent' => number_format((float) $item->discount_percent, 2, '.', ''),
            ];
        }

        $this->actingAs($this->owner)
            ->post(route('quotations.purchase-orders.generate', $quotation->id), ['items' => $items])
            ->assertRedirect(route('quotations.purchase-details', $quotation->id));

        $purchaseOrder = PurchaseOrder::where('quotation_id', $quotation->id)->firstOrFail();
        $this->actingAs($this->owner)
            ->post(route('purchase-orders.release', $purchaseOrder->id))
            ->assertRedirect(route('purchase-orders.show', $purchaseOrder->id));
        $this->get(route('goods-receipts.create', $purchaseOrder->id))
            ->assertOk()
            ->assertSee('id="invoice_number"', false)
            ->assertSee('value="' . $invoiceNumber . '"', false);
    }

    private function makeUser($roleName)
    {
        $user = User::create([
            'name' => 'Workflow ' . $roleName,
            'email' => Str::uuid() . '@example.test',
            'password' => Hash::make('test-password'),
            'role' => $roleName,
        ]);
        $user->roles()->sync([Role::where('name', $roleName)->firstOrFail()->id]);

        return $user;
    }
}
