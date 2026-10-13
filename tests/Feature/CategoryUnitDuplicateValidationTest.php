<?php

namespace Tests\Feature;

use App\Category;
use App\Unit;
use App\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class CategoryUnitDuplicateValidationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $user = User::create([
            'name' => 'Master Data Test Admin',
            'email' => Str::uuid() . '@example.test',
            'password' => Hash::make('test-password'),
            'role' => 'ADMIN',
        ]);
        $this->actingAs($user);
    }

    public function test_category_duplicate_is_case_insensitive_and_new_category_is_saved()
    {
        $existingName = 'Electronics ' . Str::random(8);
        $existing = Category::create(['name' => $existingName, 'is_active' => true]);

        $this->from(route('categories.create'))->post(route('categories.store'), [
            'name' => Str::lower($existingName),
        ])->assertRedirect(route('categories.create'))
            ->assertSessionHasErrors(['name' => 'Category is already Present']);

        $this->assertSame(1, Category::whereRaw('LOWER(TRIM(name)) = ?', [Str::lower($existingName)])->count());
        $this->put(route('categories.update', $existing->id), [
            'name' => $existingName,
            'description' => 'Updated description',
        ])->assertSessionHasNoErrors();

        $newName = 'Home Appliances ' . Str::random(8);
        $this->post(route('categories.store'), [
            'name' => $newName,
            'description' => 'Household equipment',
        ])->assertRedirect(route('categories.index'));

        $this->assertDatabaseHas('categories', ['name' => $newName]);
    }

    public function test_unit_duplicate_is_case_insensitive_and_new_unit_is_saved()
    {
        $existingName = 'Kilogram ' . Str::random(8);
        $existing = Unit::create(['name' => $existingName, 'short_name' => 'KG', 'is_active' => true]);

        $this->from(route('units.create'))->post(route('units.store'), [
            'name' => Str::upper($existingName),
            'short_name' => 'KGM',
        ])->assertRedirect(route('units.create'))
            ->assertSessionHasErrors(['name' => 'Unit is already Present']);

        $this->assertSame(1, Unit::whereRaw('LOWER(TRIM(name)) = ?', [Str::lower($existingName)])->count());
        $this->put(route('units.update', $existing->id), [
            'name' => $existingName,
            'short_name' => 'KGM',
        ])->assertSessionHasNoErrors();

        $newName = 'Litre ' . Str::random(8);
        $this->post(route('units.store'), [
            'name' => $newName,
            'short_name' => 'L',
        ])->assertRedirect(route('units.index'));

        $this->assertDatabaseHas('units', ['name' => $newName, 'short_name' => 'L']);
    }

    public function test_database_protections_reject_case_insensitive_category_and_unit_duplicates()
    {
        $categoryName = 'Database Category ' . Str::random(8);
        $unitName = 'Database Unit ' . Str::random(8);
        Category::create(['name' => $categoryName, 'is_active' => true]);
        Unit::create(['name' => $unitName, 'short_name' => 'DU', 'is_active' => true]);

        try {
            DB::table('categories')->insert([
                'name' => Str::lower($categoryName),
                'description' => null,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->fail('The category unique index should reject a case-insensitive duplicate.');
        } catch (QueryException $exception) {
            $this->assertNotSame('', $exception->getMessage());
        }

        try {
            DB::table('units')->insert([
                'name' => Str::lower($unitName),
                'short_name' => 'DU2',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->fail('The unit unique index should reject a case-insensitive duplicate.');
        } catch (QueryException $exception) {
            $this->assertNotSame('', $exception->getMessage());
        }
    }
}
