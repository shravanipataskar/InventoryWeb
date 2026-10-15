<?php

namespace Tests\Feature;

use App\Category;
use App\Hall;
use App\Product;
use App\StockInward;
use App\StockOutward;
use App\Supplier;
use App\Unit;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Http\Middleware\VerifyCsrfToken;
use Tests\TestCase;

class ProductImageTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(VerifyCsrfToken::class);
    }

    public function test_product_create_hides_generated_identifiers_and_uses_location_navigation_label()
    {
        $this->actingAs($this->makeUser());

        $this->get(route('products.create'))
            ->assertOk()
            ->assertSee('Location')
            ->assertDontSee('Product code / SKU')
            ->assertDontSee('Generated automatically')
            ->assertDontSee('Reorder quantity')
            ->assertDontSee('Reorder level')
            ->assertDontSee('<span>Hall</span>', false);
    }

    public function test_image_is_optional_and_uploaded_product_images_are_stored_and_displayed()
    {
        Storage::fake('public');
        $this->actingAs($this->makeUser());
        list($hall, $category, $unit) = $this->makeProductReferences();

        $withoutImage = $this->productPayload($hall, $category, $unit);
        unset($withoutImage['reorder_quantity']);
        unset($withoutImage['reorder_level']);
        $this->post(route('products.store'), $withoutImage)
            ->assertRedirect(route('products.index'));
        $productWithoutImage = Product::where('name', $withoutImage['name'])
            ->whereNull('image')
            ->latest('id')
            ->firstOrFail();
        $this->assertNull($productWithoutImage->image);
        $this->assertSame(0.0, (float) $productWithoutImage->reorder_quantity);
        $this->assertSame((float) $productWithoutImage->minimum_stock, (float) $productWithoutImage->reorder_level);

        $withImage = $this->productPayload($hall, $category, $unit);
        $withImage['name'] .= ' With Image';
        $this->post(route('products.store'), $withImage + [
            'image' => UploadedFile::fake()->image('product.jpg', 120, 90),
        ])->assertRedirect(route('products.index'));

        $product = Product::where('name', $withImage['name'])
            ->whereNotNull('image')
            ->latest('id')
            ->firstOrFail();
        $this->assertStringStartsWith('products/', $product->image);
        Storage::disk('public')->assertExists($product->image);

        $this->get(route('products.index'))
            ->assertOk()
            ->assertSee('product-thumbnail')
            ->assertSee('src="' . asset('storage/' . $product->image) . '"', false)
            ->assertSee('product-thumbnail-placeholder')
            ->assertSee(route('products.show', $product->id), false);
        $this->get(route('products.show', $product->id))
            ->assertOk()
            ->assertSee($product->name)
            ->assertSee($product->product_code)
            ->assertSee('Current Stock')
            ->assertSee('Back to Products')
            ->assertSee('src="' . asset('storage/' . $product->image) . '"', false);
        $this->get(route('products.show', $productWithoutImage->id))
            ->assertOk()
            ->assertSee('product-details-image-placeholder');
    }

    public function test_edit_preserves_image_without_upload_and_replaces_it_after_successful_upload()
    {
        Storage::fake('public');
        $this->actingAs($this->makeUser());
        list($hall, $category, $unit) = $this->makeProductReferences();
        $payload = $this->productPayload($hall, $category, $unit);
        $this->post(route('products.store'), $payload + [
            'image' => UploadedFile::fake()->image('first.png', 100, 100),
        ])->assertRedirect(route('products.index'));

        $product = Product::where('name', $payload['name'])
            ->whereNotNull('image')
            ->latest('id')
            ->firstOrFail();
        $oldImage = $product->image;
        Storage::disk('public')->assertExists($oldImage);

        $this->get(route('products.edit', $product->id))
            ->assertOk()
            ->assertSee('data-current-product-image', false)
            ->assertDontSee('Reorder level')
            ->assertSee('/storage/' . $oldImage, false);

        unset($payload['reorder_level']);
        $this->put(route('products.update', $product->id), $payload)
            ->assertRedirect(route('products.index'));
        $this->assertSame($oldImage, $product->fresh()->image);
        Storage::disk('public')->assertExists($oldImage);

        $this->put(route('products.update', $product->id), $payload + [
            'image' => UploadedFile::fake()->image('replacement.png', 100, 100),
        ])->assertRedirect(route('products.index'));

        $newImage = $product->fresh()->image;
        $this->assertNotSame($oldImage, $newImage);
        Storage::disk('public')->assertMissing($oldImage);
        Storage::disk('public')->assertExists($newImage);
    }

    public function test_duplicate_product_names_are_rejected()
    {
        $this->actingAs($this->makeUser());
        list($hall, $category, $unit) = $this->makeProductReferences();
        $payload = $this->productPayload($hall, $category, $unit);

        $this->post(route('products.store'), $payload)
            ->assertRedirect(route('products.index'));

        $duplicate = $this->productPayload($hall, $category, $unit);
        $duplicate['name'] = $payload['name'];
        $this->from(route('products.create'))
            ->post(route('products.store'), $duplicate)
            ->assertSessionHasErrors('name');
    }

    public function test_webp_product_image_is_accepted()
    {
        Storage::fake('public');
        $this->actingAs($this->makeUser());
        list($hall, $category, $unit) = $this->makeProductReferences();

        $temporaryPath = tempnam(sys_get_temp_dir(), 'product-webp-');
        $image = imagecreatetruecolor(80, 60);
        $background = imagecolorallocate($image, 25, 120, 110);
        imagefill($image, 0, 0, $background);
        imagewebp($image, $temporaryPath);
        imagedestroy($image);

        $upload = new UploadedFile(
            $temporaryPath,
            'product.webp',
            'image/webp',
            UPLOAD_ERR_OK,
            true
        );
        $payload = $this->productPayload($hall, $category, $unit);

        try {
            $this->post(route('products.store'), $payload + ['image' => $upload])
                ->assertRedirect(route('products.index'));
        } finally {
            unlink($temporaryPath);
        }

        $product = Product::where('name', $payload['name'])
            ->whereNotNull('image')
            ->latest('id')
            ->firstOrFail();
        $this->assertStringStartsWith('products/', $product->image);
        Storage::disk('public')->assertExists($product->image);
    }

    public function test_invalid_and_oversized_product_images_are_rejected()
    {
        Storage::fake('public');
        $this->actingAs($this->makeUser());
        list($hall, $category, $unit) = $this->makeProductReferences();

        $invalid = $this->productPayload($hall, $category, $unit);
        $this->from(route('products.create'))->post(route('products.store'), $invalid + [
            'image' => UploadedFile::fake()->create('payload.txt', 10, 'text/plain'),
        ])->assertSessionHasErrors('image');

        $largeImage = UploadedFile::fake()->image('large.jpg', 100, 100);
        $largeImagePath = tempnam(sys_get_temp_dir(), 'product-image-');
        file_put_contents(
            $largeImagePath,
            file_get_contents($largeImage->getPathname()) . str_repeat("\0", 5200 * 1024)
        );
        $oversized = $this->productPayload($hall, $category, $unit);
        $largeImageUpload = new UploadedFile(
            $largeImagePath,
            'large.jpg',
            'image/jpeg',
            UPLOAD_ERR_OK,
            true
        );
        $this->assertGreaterThan(5120, $largeImageUpload->getSize() / 1024);
        try {
            $this->from(route('products.create'))->post(route('products.store'), $oversized + [
                'image' => $largeImageUpload,
            ])->assertSessionHasErrors('image');
        } finally {
            unlink($largeImagePath);
        }
    }

    public function test_product_list_shows_latest_active_inward_and_outward_prices()
    {
        $this->actingAs($this->makeUser());
        list($hall, $category, $unit) = $this->makeProductReferences();
        $product = Product::create([
            'product_code' => 'PRICE-' . strtoupper(Str::random(8)),
            'name' => 'Movement Price Product',
            'hall_id' => $hall->id,
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'purchase_price' => 10,
            'selling_price' => 15,
            'minimum_stock' => 1,
            'is_active' => true,
        ]);
        $supplier = Supplier::create([
            'supplier_code' => 'PRICE-' . strtoupper(Str::random(8)),
            'name' => 'Movement Price Supplier',
            'is_active' => true,
        ]);

        foreach ([
            ['date' => '2026-10-01', 'price' => 123456.78, 'active' => true],
            ['date' => '2026-10-03', 'price' => 234567.89, 'active' => true],
            ['date' => '2026-10-04', 'price' => 987654.32, 'active' => false],
        ] as $inward) {
            StockInward::create([
                'product_id' => $product->id,
                'supplier_id' => $supplier->id,
                'inward_date' => $inward['date'],
                'quantity' => 1,
                'purchase_price' => $inward['price'],
                'total_amount' => $inward['price'],
                'is_active' => $inward['active'],
            ]);
        }

        foreach ([
            ['date' => '2026-10-01', 'price' => 345678.91, 'active' => true],
            ['date' => '2026-10-03', 'price' => 456789.12, 'active' => true],
            ['date' => '2026-10-04', 'price' => 876543.21, 'active' => false],
        ] as $outward) {
            StockOutward::create([
                'product_id' => $product->id,
                'outward_date' => $outward['date'],
                'quantity' => 1,
                'selling_price' => $outward['price'],
                'total_amount' => $outward['price'],
                'is_active' => $outward['active'],
            ]);
        }

        $this->get(route('products.index'))
            ->assertOk()
            ->assertSee('₹234,567.89')
            ->assertSee('₹456,789.12')
            ->assertDontSee('₹987,654.32')
            ->assertDontSee('₹876,543.21');
    }

    public function test_product_list_falls_back_to_saved_prices_without_active_movements()
    {
        $this->actingAs($this->makeUser());
        list($hall, $category, $unit) = $this->makeProductReferences();
        $product = Product::create([
            'product_code' => 'FALLBACK-' . strtoupper(Str::random(8)),
            'name' => 'Saved Price Product',
            'hall_id' => $hall->id,
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'purchase_price' => 123.45,
            'selling_price' => 234.56,
            'minimum_stock' => 1,
            'is_active' => true,
        ]);

        $this->get(route('products.index'))
            ->assertOk()
            ->assertSee($product->name)
            ->assertSee('₹123.45')
            ->assertSee('₹234.56');
    }

    private function makeUser()
    {
        return User::create([
            'name' => 'Image Test User',
            'email' => Str::uuid() . '@example.test',
            'password' => Hash::make('test-password'),
            'role' => 'ADMIN',
        ]);
    }

    private function makeProductReferences()
    {
        $suffix = strtoupper(Str::random(8));
        $hall = Hall::create([
            'name' => 'IMG' . $suffix,
            'is_active' => true,
        ]);
        $category = Category::create([
            'name' => 'Image Category ' . $suffix,
            'is_active' => true,
        ]);
        $unit = Unit::create([
            'name' => 'Image Unit ' . $suffix,
            'short_name' => 'I' . substr($suffix, 0, 4),
            'is_active' => true,
        ]);

        return [$hall, $category, $unit];
    }

    private function productPayload(Hall $hall, Category $category, Unit $unit)
    {
        return [
            'product_code' => 'IMG-' . strtoupper(Str::random(10)),
            'name' => 'Image Test Product',
            'hall_id' => $hall->id,
            'rack_id' => '',
            'shelf_id' => '',
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'purchase_price' => 10,
            'selling_price' => 15,
            'minimum_stock' => 1,
            'reorder_level' => 1,
            'reorder_quantity' => 1,
        ];
    }
}
