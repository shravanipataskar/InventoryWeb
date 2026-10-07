<?php

namespace App\Http\Controllers;

use App\Product;
use App\Category;
use App\Hall;
use App\Rack;
use App\Shelf;
use App\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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

        $products = $query->orderBy('id', 'desc')->get();
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

        $units = Unit::where('is_active', 1)
            ->orderBy('name')
            ->get();

        $halls = Hall::where('is_active', 1)
            ->orderBy('name')
            ->get();

        return view('products.create', compact('categories', 'units', 'halls'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'product_code' => 'required|string|max:100|unique:products,product_code',
            'name' => 'required|string|max:255',
            'hall_id' => 'required|integer|exists:halls,id',
            'rack_id' => 'nullable|integer|exists:racks,id',
            'shelf_id' => 'nullable|integer|exists:shelves,id',
            'category_id' => 'required|exists:categories,id',
            'unit_id' => 'required|exists:units,id',
            'barcode' => 'nullable|string|max:100|unique:products,barcode',
            'purchase_price' => 'required|numeric|min:0',
            'selling_price' => 'required|numeric|min:0',
            'minimum_stock' => 'required|numeric|min:0',
            'description' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        $this->validateLocation($validated);

        $imagePath = $request->hasFile('image')
            ? $request->file('image')->store('products', 'public')
            : null;
        if ($request->hasFile('image') && !$imagePath) {
            throw new \RuntimeException('The product image could not be stored.');
        }

        try {
            Product::create([
                'product_code' => $request->product_code,
                'name' => $request->name,
                'hall_id' => $validated['hall_id'],
                'rack_id' => !empty($validated['rack_id']) ? $validated['rack_id'] : null,
                'shelf_id' => !empty($validated['shelf_id']) ? $validated['shelf_id'] : null,
                'category_id' => $request->category_id,
                'unit_id' => $request->unit_id,
                'barcode' => $request->barcode,
                'purchase_price' => $request->purchase_price,
                'selling_price' => $request->selling_price,
                'minimum_stock' => $request->minimum_stock,
                'description' => $request->description,
                'image' => $imagePath,
                'is_active' => 1,
            ]);
        } catch (\Throwable $exception) {
            if ($this->isProductImagePath($imagePath)) {
                Storage::disk('public')->delete($imagePath);
            }

            throw $exception;
        }

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

        $units = Unit::where('is_active', 1)
            ->orderBy('name')
            ->get();

        $halls = Hall::where('is_active', 1)
            ->orderBy('name')
            ->get();
        if ($product->hall && !$halls->contains('id', $product->hall->id)) {
            $halls->push($product->hall);
        }

        return view('products.edit', compact(
            'product',
            'categories',
            'units',
            'halls'
        ));
    }

    public function update(Request $request, $id)
    {
        $product = Product::findOrFail($id);

        $validated = $request->validate([
            'product_code' => 'required|string|max:100|unique:products,product_code,' . $id,
            'name' => 'required|string|max:255',
            'hall_id' => 'required|integer|exists:halls,id',
            'rack_id' => 'nullable|integer|exists:racks,id',
            'shelf_id' => 'nullable|integer|exists:shelves,id',
            'category_id' => 'required|exists:categories,id',
            'unit_id' => 'required|exists:units,id',
            'barcode' => 'nullable|string|max:100|unique:products,barcode,' . $id,
            'purchase_price' => 'required|numeric|min:0',
            'selling_price' => 'required|numeric|min:0',
            'minimum_stock' => 'required|numeric|min:0',
            'description' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        $this->validateLocation($validated, $product);

        $oldImagePath = $product->image;
        $newImagePath = $request->hasFile('image')
            ? $request->file('image')->store('products', 'public')
            : null;
        if ($request->hasFile('image') && !$newImagePath) {
            throw new \RuntimeException('The product image could not be stored.');
        }

        try {
            $product->update([
                'product_code' => $request->product_code,
                'name' => $request->name,
                'hall_id' => $validated['hall_id'],
                'rack_id' => !empty($validated['rack_id']) ? $validated['rack_id'] : null,
                'shelf_id' => !empty($validated['shelf_id']) ? $validated['shelf_id'] : null,
                'category_id' => $request->category_id,
                'unit_id' => $request->unit_id,
                'barcode' => $request->barcode,
                'purchase_price' => $request->purchase_price,
                'selling_price' => $request->selling_price,
                'minimum_stock' => $request->minimum_stock,
                'description' => $request->description,
                'image' => $newImagePath ?: $oldImagePath,
            ]);
        } catch (\Throwable $exception) {
            if ($this->isProductImagePath($newImagePath)) {
                Storage::disk('public')->delete($newImagePath);
            }

            throw $exception;
        }

        if ($newImagePath && $oldImagePath !== $newImagePath && $this->isProductImagePath($oldImagePath)) {
            Storage::disk('public')->delete($oldImagePath);
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

    private function validateLocation(array $location, Product $product = null)
    {
        $hall = Hall::findOrFail($location['hall_id']);
        $currentHallId = $product ? $product->hall_id : null;
        $currentRackId = $product ? $product->rack_id : null;
        $currentShelfId = $product ? $product->shelf_id : null;
        $rackId = empty($location['rack_id']) ? null : $location['rack_id'];
        $shelfId = empty($location['shelf_id']) ? null : $location['shelf_id'];

        if (!$hall->is_active && (string) $currentHallId !== (string) $hall->id) {
            throw ValidationException::withMessages([
                'hall_id' => 'Only active Halls can be selected for a new product location.',
            ]);
        }

        if ($rackId === null && $shelfId !== null) {
            throw ValidationException::withMessages([
                'shelf_id' => 'Select a Rack before selecting a Shell.',
            ]);
        }

        $rack = null;
        if ($rackId !== null) {
            $rack = Rack::findOrFail($rackId);

            if ((string) $rack->hall_id !== (string) $hall->id) {
                throw ValidationException::withMessages([
                    'rack_id' => 'The selected Rack does not belong to the selected Hall.',
                ]);
            }

            $preservingInactiveRack = (string) $currentRackId === (string) $rack->id;
            if ((!$rack->is_active || !$hall->is_active) && !$preservingInactiveRack) {
                throw ValidationException::withMessages([
                    'rack_id' => 'Only active Racks under an active Hall can be selected.',
                ]);
            }
        }

        if ($shelfId !== null) {
            $shelf = Shelf::findOrFail($shelfId);

            if ((string) $shelf->rack_id !== (string) $rack->id) {
                throw ValidationException::withMessages([
                    'shelf_id' => 'The selected Shell does not belong to the selected Rack.',
                ]);
            }

            $preservingInactiveShelf = (string) $currentShelfId === (string) $shelf->id;
            if ((!$shelf->is_active || !$rack->is_active || !$hall->is_active)
                && !$preservingInactiveShelf) {
                throw ValidationException::withMessages([
                    'shelf_id' => 'Only active Shells under an active Rack and Hall can be selected.',
                ]);
            }
        }
    }

    private function isProductImagePath($path)
    {
        return is_string($path)
            && strpos($path, 'products/') === 0
            && strpos($path, '..') === false
            && strpos($path, '\\') === false;
    }
}