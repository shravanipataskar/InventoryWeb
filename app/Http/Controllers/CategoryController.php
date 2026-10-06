<?php

namespace App\Http\Controllers;

use App\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        $listingStatus = $request->query('status') === 'inactive' ? 'inactive' : 'active';
        $categories = Category::where('is_active', $listingStatus === 'active')
            ->orderBy('id', 'desc')
            ->get();

        return view('categories.index', compact('categories', 'listingStatus'));
    }

    public function create()
    {
        return view('categories.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        Category::create([
            'name' => $request->name,
            'description' => $request->description,
            'is_active' => 1,
        ]);

        return redirect()
            ->route('categories.index')
            ->with('success', 'Category created successfully.');
    }

    public function edit($id)
    {
        $category = Category::findOrFail($id);

        return view('categories.edit', compact('category'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $category = Category::findOrFail($id);

        $category->update([
            'name' => $request->name,
            'description' => $request->description,
        ]);

        return redirect()
            ->route('categories.index')
            ->with('success', 'Category updated successfully.');
    }

    public function status(Request $request, $id)
    {
        $validated = $request->validate([
            'is_active' => 'required|boolean',
            'listing_status' => 'required|in:active,inactive',
        ]);

        $category = Category::findOrFail($id);
        $category->is_active = $validated['is_active'];
        $category->save();

        return redirect()
            ->route('categories.index', ['status' => $validated['listing_status']])
            ->with('success', $category->is_active
                ? 'Category activated successfully.'
                : 'Category deactivated successfully. The record is retained.');
    }

    public function destroy($id)
    {
        $category = Category::findOrFail($id);
        $category->is_active = false;
        $category->save();

        return redirect()
            ->route('categories.index')
            ->with('success', 'Category deactivated successfully. The record is retained.');
    }
}