<?php

namespace App\Http\Controllers;

use App\Category;
use App\Product;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
<<<<<<< Updated upstream
        $listingStatus = $request->query('status') === 'inactive' ? 'inactive' : 'active';
        $categories = Category::where('is_active', $listingStatus === 'active')
            ->orderBy('id', 'desc')
            ->get();

        return view('categories.index', compact('categories', 'listingStatus'));
=======
        $totalCategories = Category::count();
        $activeCategories = Category::where('is_active', 1)->count();
        $inactiveCategories = Category::where('is_active', 0)->count();
        $totalProductsInCategories = Product::whereHas('category')->count();

        $query = Category::withCount('products');

        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where(function ($categoryQuery) use ($search) {
                $categoryQuery->where('name', 'like', '%' . $search . '%')
                    ->orWhere('description', 'like', '%' . $search . '%');
            });
        }

        if ($request->input('status') === 'active') {
            $query->where('is_active', 1);
        } elseif ($request->input('status') === 'inactive') {
            $query->where('is_active', 0);
        }

        $sortOptions = [
            'newest' => ['created_at', 'desc'],
            'oldest' => ['created_at', 'asc'],
            'name_asc' => ['name', 'asc'],
            'name_desc' => ['name', 'desc'],
        ];
        $sort = $request->input('sort', 'newest');
        if (!array_key_exists($sort, $sortOptions)) {
            $sort = 'newest';
        }

        $query->orderBy($sortOptions[$sort][0], $sortOptions[$sort][1]);
        $categories = $query->paginate(5)->appends($request->query());

        return view('categories.index', compact(
            'categories',
            'totalCategories',
            'activeCategories',
            'inactiveCategories',
            'totalProductsInCategories',
            'sort'
        ));
>>>>>>> Stashed changes
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

    public function show($id)
    {
        $category = Category::withCount('products')->findOrFail($id);

        return view('categories.show', compact('category'));
    }

    public function edit($id)
    {
        $category = Category::findOrFail($id);

        return view('categories.edit', compact('category'));
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'sometimes|in:0,1',
        ]);

        $category = Category::findOrFail($id);

        $category->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
        ]);

        if (array_key_exists('is_active', $validated)) {
            $category->update(['is_active' => (int) $validated['is_active']]);
        }

        return redirect()
            ->route('categories.index')
            ->with(
                'success',
                array_key_exists('is_active', $validated)
                    ? 'Category status updated successfully.'
                    : 'Category updated successfully.'
            );
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