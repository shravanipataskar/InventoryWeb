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
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'reference_number' => 'nullable|string|max:100',
            'outward_date' => 'required|date',
            'quantity' => 'required|numeric|min:0.01',
            'selling_price' => 'required|numeric|min:0',
            'customer_id' => [
                'nullable',
                'required_without:issued_to',
                'integer',
                Rule::exists('customers', 'id')->where('is_active', true),
            ],
            'issued_to' => 'nullable|required_without:customer_id|string|max:255',
            'remarks' => 'nullable|string',
        ]);

        DB::transaction(function () use ($request, $validated) {

            $product = Product::lockForUpdate()->findOrFail($validated['product_id']);

            if ($validated['quantity'] > $product->current_stock) {
                abort(
                    422,
                    'Insufficient stock. Available stock: '
                    . $product->current_stock
                );
            }

            $customer = null;
            if (!empty($validated['customer_id'])) {
                $customer = Customer::where('is_active', true)
                    ->lockForUpdate()
                    ->findOrFail($validated['customer_id']);
            }

            $totalAmount = $validated['quantity'] * $validated['selling_price'];

            StockOutward::create([
                'product_id' => $validated['product_id'],
                'customer_id' => $customer ? $customer->id : null,
                'reference_number' => $validated['reference_number'] ?? null,
                'outward_date' => $validated['outward_date'],
                'quantity' => $validated['quantity'],
                'selling_price' => $validated['selling_price'],
                'total_amount' => $totalAmount,
                'issued_to' => $customer ? $customer->name : ($validated['issued_to'] ?? null),
                'remarks' => $validated['remarks'] ?? null,
            ]);

            $product->decrement(
                'current_stock',
                $validated['quantity']
            );
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