<?php

namespace Tests\Feature;

use App\Supplier;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class SupplierDetailsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_supplier_business_details_are_saved_and_contact_information_section_is_not_rendered()
    {
        $this->actingAs(User::create([
            'name' => 'Supplier Test User',
            'email' => Str::uuid() . '@example.test',
            'password' => Hash::make('test-password'),
        ]));

        $payload = $this->supplierPayload();
        $this->get(route('suppliers.create'))
            ->assertOk()
            ->assertSee('Tax Information')
            ->assertSee('Payment Information')
            ->assertSee('Bank Name')
            ->assertDontSee('Contact Information')
            ->assertDontSee('Alternate Phone')
            ->assertDontSee('name="email"', false);

        $this->post(route('suppliers.store'), $payload)
            ->assertRedirect(route('suppliers.index'));

        $supplier = Supplier::where('supplier_code', $payload['supplier_code'])->firstOrFail();
        $this->assertDatabaseHas('suppliers', [
            'id' => $supplier->id,
            'supplier_code' => $payload['supplier_code'],
            'name' => $payload['name'],
            'company_name' => $payload['company_name'],
            'phone' => $payload['phone'],
            'gst_number' => $payload['gst_number'],
            'pan_number' => $payload['pan_number'],
            'payment_terms' => $payload['payment_terms'],
            'bank_name' => $payload['bank_name'],
            'account_holder_name' => $payload['account_holder_name'],
            'account_number' => $payload['account_number'],
            'ifsc_code' => $payload['ifsc_code'],
            'branch_name' => $payload['branch_name'],
            'address' => $payload['address'],
            'city' => $payload['city'],
            'state' => $payload['state'],
            'pincode' => $payload['pincode'],
            'notes' => $payload['notes'],
        ]);

        $this->get(route('suppliers.edit', $supplier->id))
            ->assertOk()
            ->assertSee('value="' . $payload['bank_name'] . '"', false)
            ->assertSee('value="' . $payload['account_number'] . '"', false)
            ->assertDontSee('Contact Information')
            ->assertDontSee('Alternate Phone')
            ->assertDontSee('name="email"', false);

        $payload['name'] = 'Updated Supplier Contact';
        $payload['payment_terms'] = 'net_60';
        $this->put(route('suppliers.update', $supplier->id), $payload)
            ->assertRedirect(route('suppliers.index'));

        $this->assertDatabaseHas('suppliers', [
            'id' => $supplier->id,
            'name' => $payload['name'],
            'payment_terms' => 'net_60',
        ]);
    }

    public function test_supplier_requires_code_company_contact_and_phone_and_valid_payment_terms()
    {
        $this->actingAs(User::create([
            'name' => 'Supplier Validation User',
            'email' => Str::uuid() . '@example.test',
            'password' => Hash::make('test-password'),
        ]));

        $this->post(route('suppliers.store'), [
            'supplier_code' => '',
            'company_name' => '',
            'name' => '',
            'phone' => '',
            'payment_terms' => 'net_90',
        ])->assertSessionHasErrors([
            'supplier_code',
            'company_name',
            'name',
            'phone',
            'payment_terms',
        ]);
    }

    private function supplierPayload()
    {
        $suffix = strtoupper(Str::random(8));

        return [
            'supplier_code' => 'SUP-' . $suffix,
            'company_name' => 'Supplier Company ' . $suffix,
            'name' => 'Supplier Contact ' . $suffix,
            'phone' => '9876543210',
            'gst_number' => '27ABCDE1234F1Z5',
            'pan_number' => 'ABCDE1234F',
            'address' => '123 MG Road',
            'city' => 'Pune',
            'state' => 'Maharashtra',
            'pincode' => '411001',
            'payment_terms' => 'net_30',
            'bank_name' => 'HDFC Bank',
            'account_holder_name' => 'Supplier Company ' . $suffix,
            'account_number' => '123456789012',
            'ifsc_code' => 'HDFC0001234',
            'branch_name' => 'Pune',
            'notes' => 'Billing note ' . $suffix,
        ];
    }
}
