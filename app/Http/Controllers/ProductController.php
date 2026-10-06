<?php

namespace App\Http\Controllers;

use App\Product;
use App\Category;
use App\Unit;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $listingStatus = $request->query('status') === 'inactive' ? 'inactive' : 'active';
        $query = Product::with(['category', 'unit'])
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

        return view('products.create', compact('categories', 'units'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'product_code' => 'required|string|max:100|unique:products,product_code',
            'name' => 'required|string|max:255',
            'hall' => 'required|alpha_num|max:50',
            'rack' => 'required|alpha_num|max:50',
            'shell' => 'required|alpha_num|max:50',
            'category_id' => 'required|exists:categories,id',
            'unit_id' => 'required|exists:units,id',
            'barcode' => 'nullable|string|max:100|unique:products,barcode',
            'purchase_price' => 'required|numeric|min:0',
            'selling_price' => 'required|numeric|min:0',
            'minimum_stock' => 'required|numeric|min:0',
            'description' => 'nullable|string',
        ]);

        Product::create([
            'product_code' => $request->product_code,
            'name' => $request->name,
            'hall' => $request->hall,
            'rack' => $request->rack,
            'shell' => $request->shell,
            'category_id' => $request->category_id,
            'unit_id' => $request->unit_id,
            'barcode' => $request->barcode,
            'purchase_price' => $request->purchase_price,
            'selling_price' => $request->selling_price,
            'minimum_stock' => $request->minimum_stock,
            'description' => $request->description,
            'is_active' => 1,
        ]);

        return redirect()
            ->route('products.index')
            ->with('success', 'Product created successfully.');
    }

    public function edit($id)
    {
        $product = Product::findOrFail($id);

        $categories = Category::where('is_active', 1)
            ->orderBy('name')
            ->get();

        $units = Unit::where('is_active', 1)
            ->orderBy('name')
            ->get();

        return view('products.edit', compact(
            'product',
            'categories',
            'units'
        ));
    }

    public function update(Request $request, $id)
    {
        $product = Product::findOrFail($id);

        $request->validate([
            'product_code' => 'required|string|max:100|unique:products,product_code,' . $id,
            'name' => 'required|string|max:255',
            'hall' => 'required|alpha_num|max:50',
            'rack' => 'required|alpha_num|max:50',
            'shell' => 'required|alpha_num|max:50',
            'category_id' => 'required|exists:categories,id',
            'unit_id' => 'required|exists:units,id',
            'barcode' => 'nullable|string|max:100|unique:products,barcode,' . $id,
            'purchase_price' => 'required|numeric|min:0',
            'selling_price' => 'required|numeric|min:0',
            'minimum_stock' => 'required|numeric|min:0',
            'description' => 'nullable|string',
        ]);

        $product->update([
            'product_code' => $request->product_code,
            'name' => $request->name,
            'hall' => $request->hall,
            'rack' => $request->rack,
            'shell' => $request->shell,
            'category_id' => $request->category_id,
            'unit_id' => $request->unit_id,
            'barcode' => $request->barcode,
            'purchase_price' => $request->purchase_price,
            'selling_price' => $request->selling_price,
            'minimum_stock' => $request->minimum_stock,
            'description' => $request->description,
        ]);

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
}