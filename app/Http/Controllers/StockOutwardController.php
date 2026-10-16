<?php

namespace App\Http\Controllers;

use App\Category;
use App\Customer;
use App\StockOutward;
use App\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Throwable;

class StockOutwardController extends Controller
{
    public function index(Request $request)
    {
        $listingStatus = $request->query('status') === 'inactive' ? 'inactive' : 'active';
        $filters = $request->validate([
            'search' => 'nullable|string|max:255',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'product_id' => 'nullable|integer|exists:products,id',
            'customer_id' => 'nullable|integer|exists:customers,id',
        ]);

        $query = StockOutward::query()
            ->with(['product', 'customer'])
            ->where('is_active', $listingStatus === 'active')
            ->when(!empty($filters['search']), function ($query) use ($filters) {
                $search = trim($filters['search']);
                $query->where(function ($query) use ($search) {
                    $query->where('reference_number', 'like', '%' . $search . '%')
                        ->orWhere('issued_to', 'like', '%' . $search . '%')
                        ->orWhereHas('product', function ($query) use ($search) {
                            $query->where('name', 'like', '%' . $search . '%')
                                ->orWhere('product_code', 'like', '%' . $search . '%');
                        })
                        ->orWhereHas('customer', function ($query) use ($search) {
                            $query->where('name', 'like', '%' . $search . '%');
                        });
                });
            })
            ->when(!empty($filters['date_from']), function ($query) use ($filters) {
                $query->whereDate('outward_date', '>=', $filters['date_from']);
            })
            ->when(!empty($filters['date_to']), function ($query) use ($filters) {
                $query->whereDate('outward_date', '<=', $filters['date_to']);
            })
            ->when(!empty($filters['product_id']), function ($query) use ($filters) {
                $query->where('product_id', $filters['product_id']);
            })
            ->when(!empty($filters['customer_id']), function ($query) use ($filters) {
                $query->where('customer_id', $filters['customer_id']);
            });

        $totals = (clone $query)
            ->selectRaw('COUNT(*) as total_records, COALESCE(SUM(quantity), 0) as total_quantity, COALESCE(SUM(total_amount), 0) as total_amount')
            ->first();

        $activeCount = StockOutward::where('is_active', true)->count();
        $inactiveCount = StockOutward::where('is_active', false)->count();

        $stockOutwards = $query
            ->orderBy('id', 'desc')
            ->paginate(10)
            ->appends($request->query());

        $products = Product::orderBy('name')->get(['id', 'name']);
        $customers = Customer::orderBy('name')->get(['id', 'name']);

        return view(
            'stock_outwards.index',
            compact('stockOutwards', 'listingStatus', 'filters', 'totals', 'products', 'customers', 'activeCount', 'inactiveCount')
        );
    }

    public function show($id)
    {
        try {
            $stock = StockOutward::with(['product', 'customer'])
                ->findOrFail($id);

            return view('stock_outwards.show', compact('stock'));

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $exception) {
            throw $exception;

        } catch (\Throwable $exception) {
            \Illuminate\Support\Facades\Log::error(
                'Error displaying stock outward details.',
                [
                    'stock_outward_id' => $id,
                    'error' => $exception->getMessage(),
                    'file' => $exception->getFile(),
                    'line' => $exception->getLine(),
                ]
            );

            return redirect()
                ->route('stock-outwards.index')
                ->with('error', 'Unable to load stock outward details. Please try again.');
        }
    }


    public function create()
    {
        $selectedCustomerId = Customer::where('is_active', true)
            ->whereKey(request()->query('customer_id'))
            ->value('id');
        $products = Product::with(['category', 'hall', 'rack', 'shelf'])
            ->where('is_active', 1)
            ->where('current_stock', '>', 0)
            ->orderBy('name')
            ->get();
        $customers = Customer::where('is_active', true)
            ->orderBy('name')
            ->get();
        $categories = Category::where('is_active', true)
            ->orderBy('name')
            ->get();

        return view(
            'stock_outwards.create',
            compact('products', 'customers', 'categories', 'selectedCustomerId')
        );
    }

    public function store(Request $request)
    {
        $productIds = $request->input('product_id');
        if (is_array($productIds)) {
            $categories = $request->input('category_id', []);
            $quantities = $request->input('quantity', []);
            $rates = $request->input('rate', []);
            $discounts = $request->input('discount', []);
            $gstRates = $request->input('gst', []);
            $items = [];

            foreach ($productIds as $index => $productId) {
                $items[] = [
                    'category_id' => $categories[$index] ?? null,
                    'product_id' => $productId,
                    'quantity' => $quantities[$index] ?? null,
                    'rate' => $rates[$index] ?? null,
                    'discount' => $discounts[$index] ?? 0,
                    'gst' => $gstRates[$index] ?? 0,
                ];
            }
        } else {
            $product = $productIds ? Product::find($productIds) : null;
            $items = [[
                'category_id' => $product ? $product->category_id : null,
                'product_id' => $productIds,
                'quantity' => $request->input('quantity'),
                'rate' => $request->input('selling_price'),
                'discount' => 0,
                'gst' => 0,
            ]];
        }

        $request->merge(['items' => $items]);
        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.category_id' => 'required|integer|exists:categories,id',
            'items.*.product_id' => [
                'required',
                'integer',
                Rule::exists('products', 'id')->where('is_active', true),
            ],
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.rate' => 'required|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|between:0,100',
            'items.*.gst' => 'nullable|numeric|between:0,100',
            'reference_number' => 'nullable|string|max:100',
            'outward_date' => 'required|date',
            'customer_id' => [
                'nullable',
                'required_without:issued_to',
                'integer',
                Rule::exists('customers', 'id')->where('is_active', true),
            ],
            'issued_to' => 'nullable|required_without:customer_id|string|max:255',
            'remarks' => 'nullable|string',
        ]);

        // Start transaction for creating stock outward entries and updating inventory
        try {
            DB::beginTransaction();

            $quantitiesByProduct = collect($validated['items'])
                ->groupBy('product_id')
                ->map(function ($items) {
                    return (float) $items->sum('quantity');
                });
            $products = Product::whereIn('id', $quantitiesByProduct->keys())
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($validated['items'] as $index => $item) {
                $product = $products->get($item['product_id']);
                if (!$product || (int) $product->category_id !== (int) $item['category_id']) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'items.' . $index . '.product_id' => 'Selected product does not belong to the selected category.',
                    ]);
                }
            }

            foreach ($quantitiesByProduct as $productId => $quantity) {
                $product = $products->get($productId);
                if ($quantity > (float) $product->current_stock) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'items' => 'Insufficient stock for ' . $product->name . '. Available stock: ' . $product->current_stock,
                    ]);
                }
            }

            $customer = null;
            if (!empty($validated['customer_id'])) {
                $customer = Customer::where('is_active', true)
                    ->lockForUpdate()
                    ->findOrFail($validated['customer_id']);
            }

            foreach ($validated['items'] as $item) {
                $subtotal = (float) $item['quantity'] * (float) $item['rate'];
                $discount = $subtotal * ((float) ($item['discount'] ?? 0) / 100);
                $taxable = $subtotal - $discount;
                $totalAmount = $taxable * (1 + ((float) ($item['gst'] ?? 0) / 100));

                StockOutward::create([
                    'product_id' => $item['product_id'],
                    'customer_id' => $customer ? $customer->id : null,
                    'reference_number' => $validated['reference_number'] ?? null,
                    'outward_date' => $validated['outward_date'],
                    'quantity' => $item['quantity'],
                    'selling_price' => $item['rate'],
                    'total_amount' => round($totalAmount, 2),
                    'issued_to' => $customer ? $customer->name : ($validated['issued_to'] ?? null),
                    'remarks' => $validated['remarks'] ?? null,
                ]);
            }

            foreach ($quantitiesByProduct as $productId => $quantity) {
                $products->get($productId)->decrement('current_stock', $quantity);
            }

            DB::commit();
        } catch (Throwable $exception) {
            DB::rollBack();
            throw $exception;
        }

        return redirect()
            ->route('stock-outwards.index')
            ->with(
                'success',
                'Stock outward recorded successfully.'
            );
    }

    public function status(Request $request, $id)
    {
        $validated = $request->validate([
            'is_active' => 'required|boolean',
            'listing_status' => 'required|in:active,inactive',
        ]);

        // Start transaction for updating stock outward status and inventory
        try {
            DB::beginTransaction();

            $stockOutward = StockOutward::lockForUpdate()->findOrFail($id);

            if ((bool) $stockOutward->is_active === (bool) $validated['is_active']) {
                DB::commit();

                return redirect()
                    ->route('stock-outwards.index', ['status' => $validated['listing_status']])
                    ->with('success', $validated['is_active']
                        ? 'Stock outward activated and inventory updated.'
                        : 'Stock outward deactivated and inventory updated.');
            }

            $product = Product::lockForUpdate()->findOrFail($stockOutward->product_id);

            if ($validated['is_active']) {
                if ($product->current_stock < $stockOutward->quantity) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'status' => 'This outward cannot be activated because there is not enough stock available.',
                    ]);
                }

                $product->decrement('current_stock', $stockOutward->quantity);
                $stockOutward->is_active = true;
            } else {
                $product->increment('current_stock', $stockOutward->quantity);
                $stockOutward->is_active = false;
            }

            $stockOutward->save();

            DB::commit();
        } catch (Throwable $exception) {
            DB::rollBack();
            throw $exception;
        }

        return redirect()
            ->route('stock-outwards.index', ['status' => $validated['listing_status']])
            ->with('success', $validated['is_active']
                ? 'Stock outward activated and inventory updated.'
                : 'Stock outward deactivated and inventory updated.');
    }
}