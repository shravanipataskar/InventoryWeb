<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyCsrfToken;
use App\Supplier;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class QuotationSupplierPickerTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(VerifyCsrfToken::class);
        $this->actingAs(User::create([
            'name' => 'Quotation Supplier Picker Test',
            'email' => Str::uuid() . '@example.test',
            'password' => Hash::make('test-password'),
            'role' => 'ADMIN',
        ]));
    }

    public function test_quotation_form_lists_only_active_suppliers_in_a_multi_select()
    {
        $active = Supplier::create([
            'supplier_code' => 'ACTIVE-' . strtoupper(Str::random(6)),
            'name' => 'Active Supplier ' . strtoupper(Str::random(6)),
            'company_name' => 'Active Company ' . strtoupper(Str::random(6)),
            'phone' => '9876543210',
            'is_active' => true,
        ]);
        $inactive = Supplier::create([
            'supplier_code' => 'INACTIVE-' . strtoupper(Str::random(6)),
            'name' => 'Inactive Supplier ' . strtoupper(Str::random(6)),
            'phone' => '9123456780',
            'is_active' => false,
        ]);

        $this->get(route('quotations.create'))
            ->assertOk()
            ->assertSee('Request Information')
            ->assertSee('Required Products')
            ->assertSee('Select suppliers...', false)
            ->assertSee('Search supplier...', false)
            ->assertSee('Select all suppliers who should submit a quotation.')
            ->assertSee('Receive Into Store / Warehouse')
            ->assertSee('Add Product')
            ->assertSee('Continue to Supplier Quotations')
            ->assertSee($active->company_name)
            ->assertSee($active->supplier_code)
            ->assertSee($active->phone)
            ->assertDontSee($inactive->name)
            ->assertDontSee($inactive->supplier_code);
    }

    public function test_quotation_request_requires_at_least_one_supplier_with_a_clear_message()
    {
        $this->post(route('quotations.store'), [])
            ->assertSessionHasErrors(['supplier_ids']);

        $this->assertSame(
            'Please select at least one supplier.',
            session('errors')->first('supplier_ids')
        );
    }

    public function test_supplier_selection_is_retained_after_other_fields_fail_validation()
    {
        $supplier = Supplier::create([
            'supplier_code' => 'SELECTED-' . strtoupper(Str::random(6)),
            'name' => 'Selected Supplier ' . strtoupper(Str::random(6)),
            'phone' => '9876543210',
            'is_active' => true,
        ]);

        $this->post(route('quotations.store'), [
            'supplier_ids' => [$supplier->id],
        ])->assertSessionHasErrors(['request_date', 'store_id', 'items']);

        $this->get(route('quotations.create'))
            ->assertOk()
            ->assertSee('name="supplier_ids[]" value="' . $supplier->id . '" data-supplier-id="' . $supplier->id . '"', false)
            ->assertSee('checked', false)
            ->assertSee($supplier->name);
    }
}
