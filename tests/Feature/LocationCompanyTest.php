<?php

namespace Tests\Feature;

use App\Company;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class LocationCompanyTest extends TestCase
{
    use DatabaseTransactions;

    public function test_location_requires_a_company_and_lists_the_selected_company()
    {
        $this->actingAs(User::create([
            'name' => 'Location Test User',
            'email' => Str::uuid() . '@example.test',
            'password' => Hash::make('test-password'),
        ]));

        $company = Company::create([
            'name' => 'Location Company ' . Str::random(8),
            'code' => 'LOC-' . strtoupper(Str::random(8)),
            'is_active' => true,
        ]);

        $this->get(route('locations.create'))
            ->assertOk()
            ->assertSee('Company / Brand')
            ->assertSee($company->name);

        $this->post(route('locations.store'), [
            'name' => 'Main Store',
            'code' => 'MAIN-' . strtoupper(Str::random(8)),
            'location' => 'Main shop',
        ])->assertSessionHasErrors('company_id');

        $code = 'MAIN-' . strtoupper(Str::random(8));
        $this->post(route('locations.store'), [
            'name' => 'Main Store',
            'code' => $code,
            'company_id' => $company->id,
            'location' => 'Main shop',
        ])->assertRedirect(route('locations.index'));

        $this->assertDatabaseHas('stores', [
            'code' => $code,
            'company_id' => $company->id,
        ]);

        $this->get(route('locations.index'))
            ->assertOk()
            ->assertSee($company->name);
    }
}
