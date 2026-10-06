<?php

namespace App\Http\Controllers;

use App\StockInward;
use App\Product;
use App\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockInwardController extends Controller
{
    public function index(Request $request)
    {
        $listingStatus = $request->query('status') === 'inactive' ? 'inactive' : 'active';
        $stockInwards = StockInward::with(['product', 'supplier'])
            ->where('is_active', $listingStatus === 'active')
            ->orderBy('id', 'desc')
            ->get();

        return view('stock_inwards.index', compact('stockInwards', 'listingStatus'));
    }

    public function create()
    {
        $products = Product::where('is_active', 1)
            ->orderBy('name')
            ->get();

        $suppliers = Supplier::where('is_active', 1)
            ->orderBy('name')
            ->get();

        return view('stock_inwards.create', compact(
            'products',
            'suppliers'
        ));
    }

    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'supplier_id' => 'required|exists:suppliers,id',
            'invoice_number' => 'nullable|string|max:100',
            'inward_date' => 'required|date',
            'quantity' => 'required|numeric|min:0.01',
            'purchase_price' => 'required|numeric|min:0',
            'remarks' => 'nullable|string',
        ]);

        DB::transaction(function () use ($request) {

            $totalAmount =
                $request->quantity * $request->purchase_price;

            StockInward::create([
                'product_id' => $request->product_id,
                'supplier_id' => $request->supplier_id,
                'invoice_number' => $request->invoice_number,
                'inward_date' => $request->inward_date,
                'quantity' => $request->quantity,
                'purchase_price' => $request->purchase_price,
                'total_amount' => $totalAmount,
                'remarks' => $request->remarks,
            ]);

            $product = Product::findOrFail($request->product_id);

            $product->increment(
                'current_stock',
                $request->quantity
            );
        });

        return redirect()
            ->route('stock-inwards.index')
            ->with('success', 'Stock inward recorded successfully.');
    }

    public function status(Request $request, $id)
    {
        $validated = $request->validate([
            'is_active' => 'required|boolean',
            'listing_status' => 'required|in:active,inactive',
        ]);

        DB::transaction(function () use ($id, $validated) {
            $stockInward = StockInward::lockForUpdate()->findOrFail($id);

            if ((bool) $stockInward->is_active === (bool) $validated['is_active']) {
                return;
            }

            $product = Product::lockForUpdate()->findOrFail($stockInward->product_id);

            if ($validated['is_active']) {
                $product->increment('current_stock', $stockInward->quantity);
                $stockInward->is_active = true;
            } else {
                if ($product->current_stock < $stockInward->quantity) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'status' => 'This inward cannot be deactivated because the product no longer has enough stock to reverse this receipt.',
                    ]);
                }

                $product->decrement('current_stock', $stockInward->quantity);
                $stockInward->is_active = false;
            }

            $stockInward->save();
        });

        return redirect()
            ->route('stock-inwards.index', ['status' => $validated['listing_status']])
            ->with('success', $validated['is_active']
                ? 'Stock inward activated and inventory updated.'
                : 'Stock inward deactivated and inventory updated.');
    }
}