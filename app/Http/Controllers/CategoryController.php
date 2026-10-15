<?php

namespace App\Http\Controllers;

use App\Category;
use App\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        try {
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
        } catch (\Throwable $e) {
            Log::error('Failed to load categories index.', [
                'exception' => $e,
            ]);

            return back()->with(
                'error',
                'Unable to load categories. Please try again.'
            );
        }
    }

    public function create()
    {
        try {
            return view('categories.create');
        } catch (\Throwable $e) {
            Log::error('Failed to load category creation page.', [
                'exception' => $e,
            ]);

            return back()->with(
                'error',
                'Unable to load the page. Please try again.'
            );
        }
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                function ($attribute, $value, $fail) {
                    if (Category::whereRaw('LOWER(TRIM(name)) = ?', [Str::lower(trim($value))])->exists()) {
                        $fail('Category is already Present');
                    }
                },
            ],
            'description' => 'nullable|string',
        ]);

        try {
            // Transaction purpose: Ensure the category creation is committed only if the database write succeeds.
            DB::beginTransaction();

            Category::create([
                'name' => trim($request->name),
                'description' => $request->description,
                'is_active' => 1,
            ]);

            DB::commit();

            return redirect()
                ->route('categories.index')
                ->with('success', 'Category created successfully.');
        } catch (\Throwable $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            Log::error('Failed to create category.', [
                'exception' => $e,
            ]);

            return back()
                ->with('error', 'Unable to create category. Please try again.')
                ->withInput();
        }
    }

    public function show($id)
    {
        try {
            $category = Category::withCount('products')->findOrFail($id);

            return view('categories.show', compact('category'));
        } catch (\Throwable $e) {
            Log::error('Failed to load category details.', [
                'category_id' => $id,
                'exception' => $e,
            ]);

            return back()->with(
                'error',
                'Unable to load category details. Please try again.'
            );
        }
    }

    public function edit($id)
    {
        try {
            $category = Category::findOrFail($id);

            return view('categories.edit', compact('category'));
        } catch (\Throwable $e) {
            Log::error('Failed to load category edit page.', [
                'category_id' => $id,
                'exception' => $e,
            ]);

            return back()->with(
                'error',
                'Unable to load category details. Please try again.'
            );
        }
    }

    public function update(Request $request, $id)
    {
        $category = Category::findOrFail($id);

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                function ($attribute, $value, $fail) use ($category) {
                    if (Str::lower(trim($value)) !== Str::lower(trim($category->name))
                        && Category::whereRaw('LOWER(TRIM(name)) = ?', [Str::lower(trim($value))])
                        ->where('id', '<>', $category->id)
                        ->exists()) {
                        $fail('Category is already Present');
                    }
                },
            ],
            'description' => 'nullable|string',
            'is_active' => 'sometimes|in:0,1',
        ]);

        $updates = [
            'name' => trim($validated['name']),
            'description' => $validated['description'] ?? null,
        ];

        if (array_key_exists('is_active', $validated)) {
            $updates['is_active'] = (int) $validated['is_active'];
        }

        try {
            // Transaction purpose: Keep the category's field updates together so a failed database write can be rolled back.
            DB::beginTransaction();

            $category->update($updates);

            DB::commit();

            return redirect()
                ->route('categories.index')
                ->with(
                    'success',
                    array_key_exists('is_active', $validated)
                        ? 'Category status updated successfully.'
                        : 'Category updated successfully.'
                );
        } catch (\Throwable $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            Log::error('Failed to update category.', [
                'category_id' => $id,
                'exception' => $e,
            ]);

            return back()
                ->with('error', 'Unable to update category. Please try again.')
                ->withInput();
        }
    }

    public function status(Request $request, $id)
    {
        $validated = $request->validate([
            'is_active' => 'required|boolean',
            'listing_status' => 'required|in:active,inactive',
        ]);

        try {
            $category = Category::findOrFail($id);

            // Transaction purpose: Ensure the category status change is committed only when the database save succeeds.
            DB::beginTransaction();

            $category->is_active = $validated['is_active'];
            $category->save();

            DB::commit();

            return redirect()
                ->route('categories.index', ['status' => $validated['listing_status']])
                ->with('success', $category->is_active
                    ? 'Category activated successfully.'
                    : 'Category deactivated successfully. The record is retained.');
        } catch (\Throwable $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            Log::error('Failed to change category status.', [
                'category_id' => $id,
                'exception' => $e,
            ]);

            return back()->with(
                'error',
                'Unable to update category status. Please try again.'
            );
        }
    }

    public function destroy($id)
    {
        try {
            $category = Category::findOrFail($id);

            // Transaction purpose: Ensure category deactivation is committed only if the database save succeeds; the record is retained.
            DB::beginTransaction();

            $category->is_active = false;
            $category->save();

            DB::commit();

            return redirect()
                ->route('categories.index')
                ->with('success', 'Category deactivated successfully. The record is retained.');
        } catch (\Throwable $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            Log::error('Failed to deactivate category.', [
                'category_id' => $id,
                'exception' => $e,
            ]);

            return back()->with(
                'error',
                'Unable to deactivate category. Please try again.'
            );
        }
    }
}