<?php

namespace App\Http\Controllers;

use App\Category;
use App\Product;
use App\PurchaseOrder;
use App\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PurchaseOrderController extends Controller
{
    public function index()
    {
        $purchaseOrders = PurchaseOrder::with('supplier')
            ->withCount('items')
            ->orderByDesc('id')
            ->paginate(20);

        return view('purchase_orders.index', compact('purchaseOrders'));
    }

    public function create()
    {
        $suppliers = Supplier::where('is_active', true)->orderBy('name')->get();
        $categories = Category::orderBy('name')->get();
        $products = Product::where('is_active', true)
            ->with('unit')
            ->orderBy('name')
            ->get();
        $stores = DB::table('stores')->where('is_active', true)->orderBy('name')->get();

        return view('purchase_orders.create', compact('suppliers', 'categories', 'products', 'stores'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'supplier_id' => 'required|integer|exists:suppliers,id,is_active,1',
            'store_id' => 'required|integer|exists:stores,id,is_active,1',
            'order_date' => 'required|date',
            'expected_date' => 'nullable|date|after_or_equal:order_date',
            'items' => 'required|array|min:1',
            'items.*.product_id' => [
                'required',
                'distinct',
                Rule::exists('products', 'id')->where('is_active', true),
            ],
            'items.*.ordered_quantity' => 'required|numeric|min:0.01',
            'items.*.purchase_rate' => 'required|numeric|min:0.01',
        ]);

        $purchaseOrder = DB::transaction(function () use ($validated) {
            $subtotal = collect($validated['items'])->sum(function ($item) {
                return (float) $item['ordered_quantity'] * (float) $item['purchase_rate'];
            });
            $order = PurchaseOrder::create([
                'po_number' => 'TEMP-' . \Illuminate\Support\Str::uuid(),
                'supplier_id' => $validated['supplier_id'],
                'store_id' => $validated['store_id'],
                'po_date' => $validated['order_date'],
                'expected_date' => $validated['expected_date'] ?? null,
                'status' => 'approved',
                'subtotal' => round($subtotal, 2),
                'discount_amount' => 0,
                'tax_amount' => 0,
                'grand_total' => round($subtotal, 2),
                'created_by' => Auth::id(),
            ]);
            $order->po_number = 'PO-' . date('Ymd', strtotime($validated['order_date']))
                . '-' . str_pad($order->id, 3, '0', STR_PAD_LEFT);
            $order->save();

            foreach ($validated['items'] as $item) {
                $order->items()->create([
                    'product_id' => $item['product_id'],
                    'unit_id' => Product::findOrFail($item['product_id'])->unit_id,
                    'quantity' => round((float) $item['ordered_quantity'], 2),
                    'received_quantity' => 0,
                    'purchase_rate' => round((float) $item['purchase_rate'], 2),
                    'discount_percent' => 0,
                    'tax_percent' => 0,
                    'total_amount' => round((float) $item['ordered_quantity'] * (float) $item['purchase_rate'], 2),
                ]);
            }

            return $order;
        });

        return redirect()
            ->route('purchase-orders.show', $purchaseOrder->id)
            ->with('success', 'Purchase order created.');
    }

    public function show($id)
    {
        $purchaseOrder = PurchaseOrder::with([
            'supplier',
            'store',
            'items.product.category',
            'items.product.unit',
            'goodsReceipts.store',
            'goodsReceipts.items',
        ])->findOrFail($id);

        return view('purchase_orders.show', compact('purchaseOrder'));
    }
}
