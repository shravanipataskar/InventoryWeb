<?php

namespace Tests\Feature;

use App\Category;
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

class HallLocationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_hall_rack_and_shell_management_preserves_parent_relationships()
    {
        $this->actingAs($this->makeUser());
        $suffix = strtoupper(Str::random(6));
        $hallA1Name = 'A1' . $suffix;
        $hallA2Name = 'A2' . $suffix;
        $rackNames = ['AR1' . $suffix, 'AR2' . $suffix, 'AR3' . $suffix];
        $otherHallRackName = 'AR4' . $suffix;

        $this->post(route('halls.store'), ['name' => $hallA1Name])->assertRedirect(route('halls.index'));
        $hallA1 = Hall::where('name', $hallA1Name)->firstOrFail();
        $this->post(route('halls.store'), ['name' => $hallA1Name])->assertSessionHasErrors('name');
        $this->post(route('halls.store'), ['name' => 'A-1' . $suffix])->assertSessionHasErrors('name');

        $this->post(route('halls.store'), ['name' => $hallA2Name])->assertRedirect(route('halls.index'));
        $hallA2 = Hall::where('name', $hallA2Name)->firstOrFail();

        $rackIds = [];
        foreach ($rackNames as $rackName) {
            $this->post(route('racks.store', $hallA1->id), ['name' => $rackName])
                ->assertRedirect(route('halls.show', $hallA1->id));
            $rackIds[$rackName] = Rack::where('hall_id', $hallA1->id)
                ->where('name', $rackName)
                ->value('id');
        }

        $this->post(route('racks.store', $hallA2->id), ['name' => $otherHallRackName])
            ->assertRedirect(route('halls.show', $hallA2->id));
        $this->post(route('racks.store', $hallA1->id), ['name' => $rackNames[0]])
            ->assertSessionHasErrors('name');
        $this->post(route('racks.store', $hallA2->id), ['name' => $rackNames[0]])
            ->assertRedirect(route('halls.show', $hallA2->id));

        foreach ([
            $rackNames[0] => ['S1', 'S2', 'S3'],
            $rackNames[1] => ['S1', 'S2', 'S3', 'S4'],
            $rackNames[2] => ['S1', 'S2'],
        ] as $rackName => $shellNames) {
            foreach ($shellNames as $shellName) {
                $this->post(route('shelves.store', $rackIds[$rackName]), ['name' => $shellName])
                    ->assertRedirect(route('racks.show', $rackIds[$rackName]));
            }
        }

        $this->get(route('halls.index'))->assertOk()->assertSee('Hall Management');
        $this->get(route('halls.show', $hallA1->id))->assertOk()->assertSee($rackNames[0]);
        $this->get(route('halls.edit', $hallA1->id))->assertOk();
        $this->get(route('racks.create', $hallA1->id))->assertOk();
        $this->get(route('racks.edit', $rackIds[$rackNames[0]]))->assertOk();
        $this->get(route('racks.show', $rackIds[$rackNames[0]]))->assertOk()->assertSee('S1');
        $this->get(route('shelves.create', $rackIds[$rackNames[0]]))->assertOk();
        $shelfToEdit = Shelf::where('rack_id', $rackIds[$rackNames[0]])->where('name', 'S1')->firstOrFail();
        $this->get(route('shelves.edit', $shelfToEdit->id))->assertOk();

        $rackOne = Rack::findOrFail($rackIds[$rackNames[0]]);
        $rackTwo = Rack::findOrFail($rackIds[$rackNames[1]]);
        $this->assertTrue($rackOne->shelves()->where('name', 'S1')->exists());
        $this->assertTrue($rackTwo->shelves()->where('name', 'S1')->exists());
        $this->post(route('shelves.store', $rackOne->id), ['name' => 'S1'])
            ->assertSessionHasErrors('name');

        $this->getJson(route('locations.halls.racks', $hallA1->id))
            ->assertOk()
            ->assertJsonCount(3)
            ->assertJsonMissing(['name' => $otherHallRackName]);
        $this->getJson(route('locations.racks.shelves', $rackOne->id))
            ->assertOk()
            ->assertJsonCount(3);

        $this->patch(route('racks.status', $rackIds[$rackNames[2]]), [
            'is_active' => 0,
            'listing_status' => 'active',
        ])->assertRedirect(route('halls.show', [
            'hall' => $hallA1->id,
            'status' => 'active',
        ]));
        $this->getJson(route('locations.halls.racks', $hallA1->id))
            ->assertJsonCount(2);
        $this->patch(route('racks.status', $rackIds[$rackNames[2]]), [
            'is_active' => 1,
            'listing_status' => 'inactive',
        ])->assertRedirect(route('halls.show', [
            'hall' => $hallA1->id,
            'status' => 'inactive',
        ]));

        $shelf = $rackOne->shelves()->where('name', 'S3')->firstOrFail();
        $this->patch(route('shelves.status', $shelf->id), [
            'is_active' => 0,
            'listing_status' => 'active',
        ])->assertRedirect(route('racks.show', [
            'rack' => $rackOne->id,
            'status' => 'active',
        ]));
        $this->getJson(route('locations.racks.shelves', $rackOne->id))
            ->assertJsonCount(2);
    }

    public function test_products_allow_optional_rack_and_shell_and_reject_invalid_hierarchies()
    {
        $this->actingAs($this->makeUser());
        list($category, $unit) = $this->makeProductReferences();
        $suffix = strtoupper(Str::random(6));
        $hallA1 = Hall::create(['name' => 'A1' . $suffix, 'is_active' => true]);
        $hallA2 = Hall::create(['name' => 'A2' . $suffix, 'is_active' => true]);
        $rackA1 = $hallA1->racks()->create(['name' => 'AR1' . $suffix, 'is_active' => true]);
        $rackA2 = $hallA1->racks()->create(['name' => 'AR2' . $suffix, 'is_active' => true]);
        $rackA3 = $hallA2->racks()->create(['name' => 'AR4' . $suffix, 'is_active' => true]);
        $shelfA1 = $rackA1->shelves()->create(['name' => 'S1', 'is_active' => true]);
        $shelfA2 = $rackA2->shelves()->create(['name' => 'S1', 'is_active' => true]);

        $this->createProduct($hallA1, $category, $unit, null, null);
        $this->createProduct($hallA1, $category, $unit, $rackA1, null);
        $this->createProduct($hallA1, $category, $unit, $rackA1, $shelfA1);

        $wrongRack = $this->productPayload($hallA1, $category, $unit, $rackA3, null);
        $this->post(route('products.store'), $wrongRack)->assertSessionHasErrors('rack_id');

        $wrongShell = $this->productPayload($hallA1, $category, $unit, $rackA1, $shelfA2);
        $this->post(route('products.store'), $wrongShell)->assertSessionHasErrors('shelf_id');
        $shellWithoutRack = $this->productPayload($hallA1, $category, $unit, null, $shelfA1);
        $this->post(route('products.store'), $shellWithoutRack)->assertSessionHasErrors('shelf_id');
        $missingHall = $this->productPayload($hallA1, $category, $unit);
        $missingHall['hall_id'] = '';
        $this->post(route('products.store'), $missingHall)->assertSessionHasErrors('hall_id');

        $this->assertDatabaseHas('products', [
            'hall_id' => $hallA1->id,
            'rack_id' => null,
            'shelf_id' => null,
        ]);
        $this->assertDatabaseHas('products', [
            'hall_id' => $hallA1->id,
            'rack_id' => $rackA1->id,
            'shelf_id' => null,
        ]);
        $this->assertDatabaseHas('products', [
            'hall_id' => $hallA1->id,
            'rack_id' => $rackA1->id,
            'shelf_id' => $shelfA1->id,
        ]);
    }

    public function test_inactive_existing_product_locations_remain_editable_without_becoming_new_choices()
    {
        $this->actingAs($this->makeUser());
        list($category, $unit) = $this->makeProductReferences();
        $suffix = strtoupper(Str::random(6));
        $hall = Hall::create(['name' => 'B1' . $suffix, 'is_active' => true]);
        $rack = $hall->racks()->create(['name' => 'BR1' . $suffix, 'is_active' => true]);
        $shelf = $rack->shelves()->create(['name' => 'S1', 'is_active' => true]);
        $payload = $this->productPayload($hall, $category, $unit, $rack, $shelf);
        $this->post(route('products.store'), $payload)->assertRedirect(route('products.index'));
        $product = Product::where('product_code', $payload['product_code'])->firstOrFail();

        $hall->update(['is_active' => false]);
        $rack->update(['is_active' => false]);
        $shelf->update(['is_active' => false]);

        $this->get(route('products.edit', $product->id))
            ->assertOk()
            ->assertSee($hall->name . ' (Inactive — existing location)')
            ->assertSee('data-preserve-rack-id="' . $rack->id . '"', false)
            ->assertSee('data-preserve-shelf-id="' . $shelf->id . '"', false);
        $this->get(route('products.create'))
            ->assertOk()
            ->assertDontSee($hall->name);
        $this->getJson(route('locations.halls.racks', $hall->id))
            ->assertOk()
            ->assertJsonCount(0);
        $newLocationPayload = $this->productPayload($hall, $category, $unit);
        $this->post(route('products.store'), $newLocationPayload)
            ->assertSessionHasErrors('hall_id');

        $payload['name'] = 'Updated historical location product';
        $this->put(route('products.update', $product->id), $payload)
            ->assertRedirect(route('products.index'));
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'hall_id' => $hall->id,
            'rack_id' => $rack->id,
            'shelf_id' => $shelf->id,
            'name' => $payload['name'],
        ]);
    }

    public function test_current_stock_displays_related_hall_rack_and_shelf_names()
    {
        $this->actingAs($this->makeUser());
        list($category, $unit) = $this->makeProductReferences();
        $suffix = strtoupper(Str::random(6));
        $hall = Hall::create(['name' => 'H' . $suffix, 'is_active' => true]);
        $rack = $hall->racks()->create(['name' => 'R' . $suffix, 'is_active' => true]);
        $shelf = $rack->shelves()->create(['name' => 'S' . $suffix, 'is_active' => true]);
        Product::create([
            'product_code' => 'LOC-' . $suffix,
            'name' => 'Current stock location test product ' . $suffix,
            'hall_id' => $hall->id,
            'rack_id' => $rack->id,
            'shelf_id' => $shelf->id,
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'purchase_price' => 10,
            'selling_price' => 15,
            'minimum_stock' => 1,
            'is_active' => true,
        ]);

        $this->get(route('current-stock.index'))
            ->assertOk()
            ->assertSee($hall->name . ' / ' . $rack->name . ' / ' . $shelf->name);
    }

    private function makeUser()
    {
        return User::create([
            'name' => 'Hall Test User',
            'email' => Str::uuid() . '@example.test',
            'password' => Hash::make('test-password'),
        ]);
    }

    private function makeProductReferences()
    {
        $suffix = Str::random(10);
        $category = Category::create([
            'name' => 'Test Category ' . $suffix,
            'is_active' => true,
        ]);
        $unit = Unit::create([
            'name' => 'Test Unit ' . $suffix,
            'short_name' => 'U' . strtoupper(substr($suffix, 0, 4)),
            'is_active' => true,
        ]);

        return [$category, $unit];
    }

    private function createProduct(Hall $hall, Category $category, Unit $unit, Rack $rack = null, Shelf $shelf = null)
    {
        $payload = $this->productPayload($hall, $category, $unit, $rack, $shelf);
        $this->post(route('products.store'), $payload)->assertRedirect(route('products.index'));

        return Product::where('product_code', $payload['product_code'])->firstOrFail();
    }

    private function productPayload(Hall $hall, Category $category, Unit $unit, Rack $rack = null, Shelf $shelf = null)
    {
        return [
            'product_code' => 'LOC-' . strtoupper(Str::random(10)),
            'name' => 'Location test product',
            'hall_id' => $hall->id,
            'rack_id' => $rack ? $rack->id : '',
            'shelf_id' => $shelf ? $shelf->id : '',
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'purchase_price' => 10,
            'selling_price' => 15,
            'minimum_stock' => 1,
        ];
    }
}
