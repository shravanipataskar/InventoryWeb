<?php

namespace Tests\Feature;

use App\Category;
use App\Customer;
use App\Hall;
use App\Product;
use App\Rack;
use App\Shelf;
use App\Unit;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerMasterTest extends TestCase
{
    use DatabaseTransactions;

    public function test_customer_master_can_be_created_and_used_in_stock_outward_history()
    {
        $this->actingAs($this->makeUser());
        $suffix = strtoupper(Str::random(8));

        $this->get(route('customers.index'))
            ->assertOk()
            ->assertSee('Manage customers and recipients who receive inventory.')
            ->assertSee('Add Customer');

        $this->get(route('customers.create'))
            ->assertOk()
            ->assertSee('Customer Type')
            ->assertDontSee('Add Stock Outward');

        $this->post(route('customers.store'), [
            'name' => 'Aaryan School ' . $suffix,
            'customer_type' => 'School',
            'contact_person' => 'Rahul Sharma',
            'phone' => '9988776655',
            'email' => 'rahul@aaryanschool.com',
            'city' => 'Pune',
            'state' => 'Maharashtra',
            'status' => 'active',
        ])->assertRedirect(route('customers.show', Customer::where('name', 'Aaryan School ' . $suffix)->value('id')));

        $customer = Customer::where('name', 'Aaryan School ' . $suffix)->firstOrFail();
        $this->post(route('customers.store'), [
            'name' => $customer->name,
            'customer_type' => 'School',
            'status' => 'active',
        ])->assertSessionHasErrors('name');

        $this->get(route('customers.index'))
            ->assertOk()
            ->assertSee($customer->name)
            ->assertSee('Rahul Sharma');

        $hall = Hall::create(['name' => 'MAIN' . $suffix, 'is_active' => true]);
        $rack = $hall->racks()->create(['name' => 'RACK' . $suffix, 'is_active' => true]);
        $shelf = $rack->shelves()->create(['name' => 'SHELF' . $suffix, 'is_active' => true]);
        $category = Category::create(['name' => 'Category ' . $suffix, 'is_active' => true]);
        $unit = Unit::create([
            'name' => 'Pieces ' . $suffix,
            'short_name' => 'PCS',
            'is_active' => true,
        ]);
        $product = Product::create([
            'product_code' => 'CUS-' . $suffix,
            'name' => 'Dell Latitude 5450',
            'hall_id' => $hall->id,
            'rack_id' => $rack->id,
            'shelf_id' => $shelf->id,
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'purchase_price' => 50000,
            'selling_price' => 70000,
            'current_stock' => 30,
            'minimum_stock' => 1,
            'reorder_level' => 1,
            'reorder_quantity' => 5,
            'is_active' => true,
        ]);
        $product->current_stock = 30;
        $product->save();
        $this->assertEquals(30, $product->fresh()->current_stock);

        $this->get(route('stock-outwards.create'))
            ->assertOk()
            ->assertSee($customer->name)
            ->assertSee('Customer / Recipient');

        $this->post(route('stock-outwards.store'), [
            'customer_id' => $customer->id,
            'product_id' => $product->id,
            'reference_number' => 'OUT-' . $suffix,
            'outward_date' => '2026-10-07',
            'quantity' => 5,
            'selling_price' => 70000,
            'remarks' => 'Computer Lab Requirement',
        ])->assertRedirect(route('stock-outwards.index'));

        $this->assertSame('25.00', $product->fresh()->current_stock);
        $this->assertDatabaseHas('stock_outwards', [
            'customer_id' => $customer->id,
            'issued_to' => $customer->name,
            'product_id' => $product->id,
            'quantity' => 5,
        ]);

        $this->get(route('customers.show', $customer->id))
            ->assertOk()
            ->assertSee('Dell Latitude 5450')
            ->assertSee('Computer Lab Requirement')
            ->assertSee('OUT-' . $suffix)
            ->assertSee($hall->name)
            ->assertSee($rack->name)
            ->assertSee($shelf->name);

        $this->post(route('stock-outwards.store'), [
            'issued_to' => 'Legacy Recipient ' . $suffix,
            'product_id' => $product->id,
            'outward_date' => '2026-10-07',
            'quantity' => 1,
            'selling_price' => 70000,
        ])->assertRedirect(route('stock-outwards.index'));
        $this->assertDatabaseHas('stock_outwards', [
            'customer_id' => null,
            'issued_to' => 'Legacy Recipient ' . $suffix,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);
        $this->assertSame('24.00', $product->fresh()->current_stock);
        $this->assertDatabaseMissing('customers', ['name' => 'Legacy Recipient ' . $suffix]);
    }

    public function test_inactive_customers_are_not_available_for_new_stock_outwards()
    {
        $this->actingAs($this->makeUser());
        $customer = Customer::create([
            'customer_code' => 'CUS-' . strtoupper(Str::random(10)),
            'name' => 'Inactive customer ' . Str::random(8),
            'customer_type' => 'Other',
            'is_active' => false,
        ]);

        $this->get(route('stock-outwards.create'))
            ->assertOk()
            ->assertDontSee($customer->name);
    }

    private function makeUser()
    {
        return User::create([
            'name' => 'Customer Test User',
            'email' => Str::uuid() . '@example.test',
            'password' => Hash::make('test-password'),
        ]);
    }
}
