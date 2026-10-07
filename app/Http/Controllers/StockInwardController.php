<?php

namespace App\Http\Controllers;

use App\StockInward;
use App\Product;
use App\Supplier;
use App\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

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
        $categories = Category::orderBy('name')
            ->get();

        $products = Product::where('is_active', 1)
            ->orderBy('name')
            ->get();
        $productOptions = $products->map(function ($product) {
            return [
                'id' => (string) $product->id,
                'category_id' => (string) $product->category_id,
                'label' => $product->name . ' (' . $product->product_code . ')',
            ];
        })->values();

        $suppliers = Supplier::where('is_active', 1)
            ->orderBy('name')
            ->get();

        return view('stock_inwards.create', compact(
            'categories',
            'products',
            'productOptions',
            'suppliers'
        ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'product_id' => [
                'required',
                Rule::exists('products', 'id')->where('category_id', $request->input('category_id')),
            ],
            'supplier_id' => 'required|exists:suppliers,id',
            'invoice_number' => 'nullable|string|max:100',
            'inward_date' => 'required|date',
            'quantity' => 'required|numeric|min:0.01',
            'purchase_price' => 'required|numeric|min:0',
            'sgst_rate' => 'required|numeric|min:0|max:100',
            'cgst_rate' => 'required|numeric|min:0|max:100',
            'remarks' => 'nullable|string',
        ]);

        $quantity = round((float) $validated['quantity'], 2);
        $purchasePrice = round((float) $validated['purchase_price'], 2);
        $sgstRate = round((float) $validated['sgst_rate'], 2);
        $cgstRate = round((float) $validated['cgst_rate'], 2);
        $subtotal = round($quantity * $purchasePrice, 2);
        $sgstAmount = round($subtotal * $sgstRate / 100, 2);
        $cgstAmount = round($subtotal * $cgstRate / 100, 2);
        $taxTotal = round($sgstAmount + $cgstAmount, 2);
        $grandTotal = round($subtotal + $taxTotal, 2);

        DB::transaction(function () use (
            $validated,
            $quantity,
            $purchasePrice,
            $sgstRate,
            $cgstRate,
            $subtotal,
            $sgstAmount,
            $cgstAmount,
            $taxTotal,
            $grandTotal
        ) {

            StockInward::create([
                'product_id' => $validated['product_id'],
                'supplier_id' => $validated['supplier_id'],
                'invoice_number' => $validated['invoice_number'] ?? null,
                'inward_date' => $validated['inward_date'],
                'quantity' => $quantity,
                'purchase_price' => $purchasePrice,
                'total_amount' => $subtotal,
                'sgst_rate' => $sgstRate,
                'cgst_rate' => $cgstRate,
                'sgst_amount' => $sgstAmount,
                'cgst_amount' => $cgstAmount,
                'tax_total' => $taxTotal,
                'subtotal' => $subtotal,
                'grand_total' => $grandTotal,
                'remarks' => $validated['remarks'] ?? null,
            ]);

            $product = Product::findOrFail($validated['product_id']);

            $product->increment(
                'current_stock',
                $quantity
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