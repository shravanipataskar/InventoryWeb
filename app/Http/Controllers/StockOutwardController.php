<?php

namespace App\Http\Controllers;

use App\StockOutward;
use App\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockOutwardController extends Controller
{
    public function index(Request $request)
    {
        $listingStatus = $request->query('status') === 'inactive' ? 'inactive' : 'active';
        $stockOutwards = StockOutward::with('product')
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
        $products = Product::where('is_active', 1)
            ->where('current_stock', '>', 0)
            ->orderBy('name')
            ->get();

        return view(
            'stock_outwards.create',
            compact('products')
        );
    }

    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'reference_number' => 'nullable|string|max:100',
            'outward_date' => 'required|date',
            'quantity' => 'required|numeric|min:0.01',
            'selling_price' => 'required|numeric|min:0',
            'issued_to' => 'nullable|string|max:255',
            'remarks' => 'nullable|string',
        ]);

        DB::transaction(function () use ($request) {

            $product = Product::lockForUpdate()
                ->findOrFail($request->product_id);

            if ($request->quantity > $product->current_stock) {
                abort(
                    422,
                    'Insufficient stock. Available stock: '
                    . $product->current_stock
                );
            }

            $totalAmount =
                $request->quantity * $request->selling_price;

            StockOutward::create([
                'product_id' => $request->product_id,
                'reference_number' => $request->reference_number,
                'outward_date' => $request->outward_date,
                'quantity' => $request->quantity,
                'selling_price' => $request->selling_price,
                'total_amount' => $totalAmount,
                'issued_to' => $request->issued_to,
                'remarks' => $request->remarks,
            ]);

            $product->decrement(
                'current_stock',
                $request->quantity
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