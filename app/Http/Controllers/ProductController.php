<?php

namespace App\Http\Controllers;

use App\Product;
use App\Category;
use App\Company;
use App\Hall;
use App\Unit;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $listingStatus = $request->query('status') === 'inactive' ? 'inactive' : 'active';
        $query = Product::with(['category', 'unit', 'hall', 'rack', 'shelf'])
            ->where('is_active', $listingStatus === 'active');

        if ($request->filled('category_id')) {
            $request->validate([
                'category_id' => 'integer|exists:categories,id',
            ]);
            $query->where('category_id', $request->input('category_id'));
        }

        if ($request->filled('company_id')) {
            $request->validate([
                'company_id' => 'integer|exists:companies,id',
            ]);
            $query->where('company_id', $request->input('company_id'));
        }

        $products = $query->with('company')->orderBy('id', 'desc')->get();
        $units = Unit::where('is_active', 1)
            ->orderBy('name')
            ->get();

        return view('products.index', compact('products', 'units', 'listingStatus'));
    }

    public function create()
    {
        $categories = Category::where('is_active', 1)
            ->orderBy('name')
            ->get();

        $halls = Hall::where('is_active', 1)
            ->orderBy('name')
            ->get();

        $units = Unit::where('is_active', 1)
            ->orderBy('name')
            ->get();
        $companies = Company::where('is_active', 1)->orderBy('name')->get();
        return view('products.create', compact('categories', 'halls', 'units', 'companies'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'hall_id' => 'required|integer|exists:halls,id',
            'rack_id' => ['nullable', 'integer', Rule::exists('racks', 'id')->where('hall_id', $request->input('hall_id'))],
            'shelf_id' => ['nullable', 'integer', Rule::exists('shelves', 'id')->where('rack_id', $request->input('rack_id'))],
            'category_id' => 'required|exists:categories,id',
            'company_id' => 'nullable|exists:companies,id',
            'unit_id' => 'required|exists:units,id',
            'minimum_stock' => 'required|numeric|min:0',
            'reorder_level' => 'required|numeric|min:0',
            'reorder_quantity' => 'required|numeric|min:0',
            'track_batch' => 'nullable|boolean',
            'track_serial' => 'nullable|boolean',
            'description' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        $productCode = $this->generateProductCode();
        $barcode = $this->generateBarcode();
        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('products', 'public');
            if (!$imagePath) {
                throw new \RuntimeException('The product image could not be saved.');
            }
        }

        Product::create([
            'product_code' => $productCode,
            'name' => $request->name,
            'hall_id' => $request->hall_id,
            'rack_id' => $request->input('rack_id') ?: null,
            'shelf_id' => $request->input('shelf_id') ?: null,
            'category_id' => $request->category_id,
            'company_id' => $request->input('company_id') ?: null,
            'unit_id' => $request->unit_id,
            'barcode' => $barcode,
            'image' => $imagePath,
            'minimum_stock' => $request->minimum_stock,
            'reorder_level' => $request->reorder_level,
            'reorder_quantity' => $request->reorder_quantity,
            'track_batch' => $request->boolean('track_batch'),
            'track_serial' => $request->boolean('track_serial'),
            'description' => $request->description,
            'is_active' => 1,
        ]);

        return redirect()
            ->route('products.index')
            ->with('success', 'Product created successfully.');
    }

    public function edit($id)
    {
        $product = Product::with(['hall', 'rack', 'shelf'])->findOrFail($id);

        $categories = Category::where('is_active', 1)
            ->orderBy('name')
            ->get();

        $halls = Hall::where('is_active', 1)
            ->orWhere('id', optional($product->hall)->id)
            ->orderBy('name')
            ->get();

        $units = Unit::where('is_active', 1)
            ->orderBy('name')
            ->get();
        $companies = Company::where('is_active', 1)
            ->orWhere('id', $product->company_id)
            ->orderBy('name')
            ->get();

        return view('products.edit', compact(
            'product',
            'categories',
            'halls',
            'units',
            'companies'
        ));
    }

    public function update(Request $request, $id)
    {
        $product = Product::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'hall_id' => 'required|integer|exists:halls,id',
            'rack_id' => ['nullable', 'integer', Rule::exists('racks', 'id')->where('hall_id', $request->input('hall_id'))],
            'shelf_id' => ['nullable', 'integer', Rule::exists('shelves', 'id')->where('rack_id', $request->input('rack_id'))],
            'category_id' => 'required|exists:categories,id',
            'company_id' => 'nullable|exists:companies,id',
            'unit_id' => 'required|exists:units,id',
            'minimum_stock' => 'required|numeric|min:0',
            'reorder_level' => 'required|numeric|min:0',
            'reorder_quantity' => 'required|numeric|min:0',
            'track_batch' => 'nullable|boolean',
            'track_serial' => 'nullable|boolean',
            'description' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'remove_image' => 'nullable|boolean',
        ]);

        $oldImage = $product->image;
        $newImage = $request->hasFile('image')
            ? $request->file('image')->store('products', 'public')
            : null;
        if ($request->hasFile('image') && !$newImage) {
            throw new \RuntimeException('The product image could not be saved.');
        }

        $product->update([
            'name' => $request->name,
            'hall_id' => $request->hall_id,
            'rack_id' => $request->input('rack_id') ?: null,
            'shelf_id' => $request->input('shelf_id') ?: null,
            'category_id' => $request->category_id,
            'company_id' => $request->input('company_id') ?: null,
            'unit_id' => $request->unit_id,
            'image' => $newImage ?: ($request->boolean('remove_image') ? null : $oldImage),
            'minimum_stock' => $request->minimum_stock,
            'reorder_level' => $request->reorder_level,
            'reorder_quantity' => $request->reorder_quantity,
            'track_batch' => $request->boolean('track_batch'),
            'track_serial' => $request->boolean('track_serial'),
            'description' => $request->description,
        ]);

        if ($oldImage && ($newImage || $request->boolean('remove_image'))) {
            if (!Storage::disk('public')->delete($oldImage)) {
                \Illuminate\Support\Facades\Log::warning('Unable to remove replaced product image.', [
                    'product_id' => $product->id,
                    'image_path' => $oldImage,
                ]);
            }
        }

        return redirect()
            ->route('products.index')
            ->with('success', 'Product updated successfully.');
    }

    public function status(Request $request, $id)
    {
        $validated = $request->validate([
            'is_active' => 'required|boolean',
            'listing_status' => 'required|in:active,inactive',
        ]);

        $product = Product::findOrFail($id);
        $product->is_active = $validated['is_active'];
        $product->save();

        return redirect()
            ->route('products.index', ['status' => $validated['listing_status']])
            ->with('success', $product->is_active
                ? 'Product activated successfully.'
                : 'Product deactivated successfully. The record is retained.');
    }

    public function destroy($id)
    {
        $product = Product::findOrFail($id);
        $product->is_active = false;
        $product->save();

        return redirect()
            ->route('products.index')
            ->with('success', 'Product deactivated successfully. The record is retained.');
    }

    private function generateProductCode()
    {
        do {
            $code = 'PRD-' . now()->format('ymd') . '-' . Str::upper(Str::random(6));
        } while (Product::where('product_code', $code)->exists());

        return $code;
    }

    private function generateBarcode()
    {
        do {
            $digits = '';
            for ($index = 0; $index < 12; $index++) {
                $digits .= (string) random_int(0, 9);
            }

            $sum = 0;
            for ($index = 0; $index < 12; $index++) {
                $sum += ((int) $digits[$index]) * ($index % 2 === 0 ? 1 : 3);
            }
            $barcode = $digits . ((10 - ($sum % 10)) % 10);
        } while (Product::where('barcode', $barcode)->exists());

        return $barcode;
    }
}