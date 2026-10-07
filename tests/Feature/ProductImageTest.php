<?php

namespace Tests\Feature;

use App\Category;
use App\Hall;
use App\Product;
use App\Unit;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductImageTest extends TestCase
{
    use DatabaseTransactions;

    public function test_image_is_optional_and_uploaded_product_images_are_stored_and_displayed()
    {
        Storage::fake('public');
        $this->actingAs($this->makeUser());
        list($hall, $category, $unit) = $this->makeProductReferences();

        $withoutImage = $this->productPayload($hall, $category, $unit);
        $this->post(route('products.store'), $withoutImage)
            ->assertRedirect(route('products.index'));
        $productWithoutImage = Product::where('product_code', $withoutImage['product_code'])->firstOrFail();
        $this->assertNull($productWithoutImage->image);

        $withImage = $this->productPayload($hall, $category, $unit);
        $this->post(route('products.store'), $withImage + [
            'image' => UploadedFile::fake()->image('product.jpg', 120, 90),
        ])->assertRedirect(route('products.index'));

        $product = Product::where('product_code', $withImage['product_code'])->firstOrFail();
        $this->assertStringStartsWith('products/', $product->image);
        Storage::disk('public')->assertExists($product->image);

        $this->get(route('products.index'))
            ->assertOk()
            ->assertSee('product-thumbnail')
            ->assertSee(asset('storage/' . $product->image), false)
            ->assertSee('product-thumbnail-placeholder');
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

        $product = Product::where('product_code', $payload['product_code'])->firstOrFail();
        $oldImage = $product->image;
        Storage::disk('public')->assertExists($oldImage);

        $this->get(route('products.edit', $product->id))
            ->assertOk()
            ->assertSee('Current product image')
            ->assertSee(asset('storage/' . $oldImage), false);

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

        $product = Product::where('product_code', $payload['product_code'])->firstOrFail();
        $this->assertStringStartsWith('products/', $product->image);
        Storage::disk('public')->assertExists($product->image);
    }

    public function test_invalid_and_oversized_product_images_are_rejected()
    {
        Storage::fake('public');
        $this->actingAs($this->makeUser());
        list($hall, $category, $unit) = $this->makeProductReferences();

        $invalid = $this->productPayload($hall, $category, $unit);
        $this->post(route('products.store'), $invalid + [
            'image' => UploadedFile::fake()->create('payload.txt', 10, 'text/plain'),
        ])->assertSessionHasErrors('image');

        $largeImage = UploadedFile::fake()->image('large.jpg', 100, 100);
        $largeImagePath = tempnam(sys_get_temp_dir(), 'product-image-');
        file_put_contents(
            $largeImagePath,
            file_get_contents($largeImage->getPathname()) . str_repeat("\0", 2100 * 1024)
        );
        $oversized = $this->productPayload($hall, $category, $unit);
        $largeImageUpload = new UploadedFile(
            $largeImagePath,
            'large.jpg',
            'image/jpeg',
            UPLOAD_ERR_OK,
            true
        );
        $this->assertGreaterThan(2048, $largeImageUpload->getSize() / 1024);
        try {
            $this->post(route('products.store'), $oversized + [
                'image' => $largeImageUpload,
            ])->assertSessionHasErrors('image');
        } finally {
            unlink($largeImagePath);
        }
    }

    private function makeUser()
    {
        return User::create([
            'name' => 'Image Test User',
            'email' => Str::uuid() . '@example.test',
            'password' => Hash::make('test-password'),
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
        ];
    }
}
