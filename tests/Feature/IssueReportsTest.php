<?php

namespace Tests\Feature;

use App\Category;
use App\Customer;
use App\Hall;
use App\Product;
use App\Rack;
use App\Shelf;
use App\StockOutward;
use App\Unit;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

class IssueReportsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_issue_report_aggregates_active_lines_and_values_them_at_purchase_cost()
    {
        list($customer, $category, $hall, $firstProduct, $secondProduct, $first, $second) = $this->createIssueFixture();
        $this->actingAs($this->makeUser());

        $this->get(route('issue-reports.index', [
            'date_from' => '2026-10-01',
            'date_to' => '2026-10-31',
            'recipient' => $customer->name,
        ]))
            ->assertOk()
            ->assertSee('Issue Reports')
            ->assertSee('OUT-REPORT-001')
            ->assertSee($customer->name)
            ->assertSee($firstProduct->product_code)
            ->assertSee($secondProduct->product_code)
            ->assertSee($hall->name)
            ->assertSee('1</strong>', false)
            ->assertSee('₹110.00')
            ->assertSee('8.00');

        $this->assertDatabaseHas('stock_outwards', ['id' => $first->id, 'is_active' => true]);
        $this->assertDatabaseHas('stock_outwards', ['id' => $second->id, 'is_active' => true]);
        $this->assertSame(13.0, (float) $firstProduct->fresh()->current_stock);
        $this->assertSame(7.0, (float) $secondProduct->fresh()->current_stock);
    }

    public function test_issue_report_filters_and_detail_view_support_multi_product_references()
    {
        list($customer, $category, $hall, $firstProduct, $secondProduct, $first) = $this->createIssueFixture();
        $this->actingAs($this->makeUser());

        $this->get(route('issue-reports.index', [
            'date_from' => '2026-10-01',
            'date_to' => '2026-10-31',
            'recipient' => $customer->name,
            'product_id' => $firstProduct->id,
            'category_id' => $category->id,
            'location_id' => $hall->id,
            'search' => $firstProduct->product_code,
        ]))
            ->assertOk()
            ->assertSee($firstProduct->name)
            ->assertDontSee('<td><strong>' . e($secondProduct->name) . '</strong><small', false)
            ->assertSee('₹50.00');

        $this->get(route('issue-reports.show', $first->id))
            ->assertOk()
            ->assertSee('OUT-REPORT-001')
            ->assertSee($firstProduct->name)
            ->assertSee($secondProduct->name)
            ->assertSee('₹110.00')
            ->assertSee('Not recorded');

        $this->get(route('issue-reports.index', [
            'date_from' => '2020-01-01',
            'date_to' => '2020-01-31',
        ]))
            ->assertOk()
            ->assertSee('No issue reports found.')
            ->assertSee('Reset Filters')
            ->assertSee('₹0.00');
    }

    public function test_filtered_issue_exports_and_print_view_are_read_only()
    {
        list($customer, $category, $hall, $firstProduct, $secondProduct, $first) = $this->createIssueFixture();
        $this->actingAs($this->makeUser());
        $filters = [
            'date_from' => '2026-10-01',
            'date_to' => '2026-10-31',
            'recipient' => $customer->name,
        ];
        $firstStock = (float) $firstProduct->fresh()->current_stock;
        $issueCount = StockOutward::count();

        $detailsCsv = $this->get(route('issue-reports.export', array_merge(['format' => 'csv', 'report_type' => 'details'], $filters)));
        $detailsCsv->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString($first->reference_number, $detailsCsv->streamedContent());
        $this->assertStringContainsString($firstProduct->product_code, $detailsCsv->streamedContent());
        $this->assertStringNotContainsString('OUT-REPORT-INACTIVE', $detailsCsv->streamedContent());

        $recipientExcel = $this->get(route('issue-reports.export', array_merge(['format' => 'excel', 'report_type' => 'recipients'], $filters)));
        $recipientExcel->assertOk()->assertHeader('content-type', 'application/vnd.ms-excel; charset=UTF-8');
        $this->assertStringContainsString($customer->name, $recipientExcel->streamedContent());

        $productCsv = $this->get(route('issue-reports.export', array_merge(['format' => 'csv', 'report_type' => 'products'], $filters)));
        $productCsv->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString($firstProduct->product_code, $productCsv->streamedContent());
        $this->get(route('issue-reports.export', array_merge(['format' => 'print'], $filters)))
            ->assertOk()
            ->assertSee($first->reference_number)
            ->assertSee($firstProduct->name);

        $this->assertSame($firstStock, (float) $firstProduct->fresh()->current_stock);
        $this->assertSame($issueCount, StockOutward::count());
    }

    private function createIssueFixture()
    {
        $suffix = strtoupper(Str::random(7));
        $customer = Customer::create([
            'customer_code' => 'IR-' . $suffix,
            'name' => 'Issue Recipient ' . $suffix,
            'customer_type' => 'Department',
            'is_active' => true,
        ]);
        $category = Category::create(['name' => 'Issue Category ' . $suffix, 'is_active' => true]);
        $unit = Unit::create([
            'name' => 'Issue Unit ' . $suffix,
            'short_name' => 'IU',
            'is_active' => true,
        ]);
        $hall = Hall::create(['name' => 'Issue Hall ' . $suffix, 'is_active' => true]);
        $rack = $hall->racks()->create(['name' => 'Issue Rack ' . $suffix, 'is_active' => true]);
        $shelf = $rack->shelves()->create(['name' => 'Issue Shelf ' . $suffix, 'is_active' => true]);

        $firstProduct = Product::create([
            'product_code' => 'IR-A-' . $suffix,
            'name' => 'Issue Product A ' . $suffix,
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'hall_id' => $hall->id,
            'rack_id' => $rack->id,
            'shelf_id' => $shelf->id,
            'purchase_price' => 10,
            'selling_price' => 1000,
            'minimum_stock' => 0,
            'is_active' => true,
        ]);
        $firstProduct->current_stock = 13;
        $firstProduct->save();
        $secondProduct = Product::create([
            'product_code' => 'IR-B-' . $suffix,
            'name' => 'Issue Product B ' . $suffix,
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'hall_id' => $hall->id,
            'rack_id' => $rack->id,
            'shelf_id' => $shelf->id,
            'purchase_price' => 20,
            'selling_price' => 2000,
            'minimum_stock' => 0,
            'is_active' => true,
        ]);
        $secondProduct->current_stock = 7;
        $secondProduct->save();
        $first = StockOutward::create([
            'product_id' => $firstProduct->id,
            'customer_id' => $customer->id,
            'reference_number' => 'OUT-REPORT-001',
            'outward_date' => '2026-10-08',
            'quantity' => 5,
            'selling_price' => 1000,
            'total_amount' => 5000,
            'issued_to' => $customer->name,
            'is_active' => true,
        ]);
        $second = StockOutward::create([
            'product_id' => $secondProduct->id,
            'customer_id' => $customer->id,
            'reference_number' => 'OUT-REPORT-001',
            'outward_date' => '2026-10-08',
            'quantity' => 3,
            'selling_price' => 2000,
            'total_amount' => 6000,
            'issued_to' => $customer->name,
            'is_active' => true,
        ]);
        StockOutward::create([
            'product_id' => $firstProduct->id,
            'customer_id' => $customer->id,
            'reference_number' => 'OUT-REPORT-INACTIVE',
            'outward_date' => '2026-10-08',
            'quantity' => 99,
            'selling_price' => 1000,
            'total_amount' => 99000,
            'issued_to' => $customer->name,
            'is_active' => false,
        ]);

        return [$customer, $category, $hall, $firstProduct, $secondProduct, $first, $second];
    }

    private function makeUser()
    {
        return \App\User::create([
            'name' => 'Issue Report User',
            'email' => Str::uuid() . '@example.test',
            'password' => bcrypt('test-password'),
        ]);
    }
}
