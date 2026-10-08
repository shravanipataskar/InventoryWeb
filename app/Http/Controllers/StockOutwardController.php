<?php

namespace App\Http\Controllers;

use App\Category;
use App\Customer;
use App\StockOutward;
use App\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StockOutwardController extends Controller
{
    public function index(Request $request)
    {
        $listingStatus = $request->query('status') === 'inactive' ? 'inactive' : 'active';
        $stockOutwards = StockOutward::with(['product', 'customer'])
            ->where('is_active', $listingStatus === 'active')
            ->orderBy('id', 'desc')
            ->get();

        return view(
            'stock_outwards.index',
            compact('stockOutwards', 'listingStatus')
        );
    }

    public function create()
    {
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
            compact('products', 'customers', 'categories')
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

        DB::transaction(function () use ($validated) {
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
        });

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

        DB::transaction(function () use ($id, $validated) {
            $stockOutward = StockOutward::lockForUpdate()->findOrFail($id);

            if ((bool) $stockOutward->is_active === (bool) $validated['is_active']) {
                return;
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
        });

        return redirect()
            ->route('stock-outwards.index', ['status' => $validated['listing_status']])
            ->with('success', $validated['is_active']
                ? 'Stock outward activated and inventory updated.'
                : 'Stock outward deactivated and inventory updated.');
    }
}