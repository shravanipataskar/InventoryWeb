<?php

namespace App\Http\Controllers;

use App\Category;
use App\ProcurementAuditLog;
use App\Product;
use App\PurchaseOrder;
use App\Supplier;
use App\SupplierBill;
use App\Support\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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

    public function create(Request $request)
    {
        $suppliers = Supplier::where('is_active', true)->orderBy('name')->get();
        $categories = Category::orderBy('name')->get();
        $products = Product::where('is_active', true)
            ->with('unit')
            ->orderBy('name')
            ->get();
        $stores = DB::table('stores')->where('is_active', true)->orderBy('name')->get();
        $prefillProduct = $products->firstWhere('id', (int) $request->query('product_id'));
        $requestedQuantity = filter_var($request->query('ordered_quantity'), FILTER_VALIDATE_FLOAT);
        $prefillItems = $prefillProduct ? [[
            'product_id' => $prefillProduct->id,
            'category_id' => $prefillProduct->category_id,
            'ordered_quantity' => $requestedQuantity !== false && $requestedQuantity > 0
                ? $requestedQuantity
                : ($prefillProduct->reorder_quantity > 0 ? $prefillProduct->reorder_quantity : 1),
            'purchase_rate' => $prefillProduct->purchase_price,
        ]] : null;
        $prefillStore = $stores->firstWhere('id', (int) $request->query('store_id'));
        $prefillStoreId = $prefillStore ? $prefillStore->id : null;

        return view('purchase_orders.create', compact(
            'suppliers',
            'categories',
            'products',
            'stores',
            'prefillItems',
            'prefillStoreId'
        ));
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
            ProcurementAuditLog::recordIfAvailable($order, 'po_created', null, $order->status);

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
            'goodsReceipts.items.product.unit',
        ])->findOrFail($id);
        $purchaseOrder->load(['quotation.request', 'creator']);
        $billingEnabled = Schema::hasTable('supplier_bills');
        if ($billingEnabled) {
            $purchaseOrder->load('supplierBills.allocations.payment');
        }
        $history = $billingEnabled ? ProcurementAuditLog::forEntity($purchaseOrder)->get() : collect();

        return view('purchase_orders.show', compact('purchaseOrder', 'history', 'billingEnabled'));
    }

    public function cancel(Request $request, $id)
    {
        $validated = $request->validate(['reason' => 'required|string|max:2000']);

        $order = DB::transaction(function () use ($id, $validated) {
            $order = PurchaseOrder::lockForUpdate()->with('items')->findOrFail($id);
            if (!in_array($order->status, ['ordered', 'pending', 'approved'], true)
                || $order->goodsReceipts()->exists()
                || $order->items->contains(function ($item) {
                    return (float) $item->received_quantity > 0;
                })) {
                throw ValidationException::withMessages(['purchase_order' => 'Only a purchase order with no goods received can be cancelled. Close it instead.']);
            }
            if ($order->supplierBills()->whereIn('status', SupplierBill::ACTIVE_STATUSES)->exists()) {
                throw ValidationException::withMessages(['purchase_order' => 'Cancel the supplier bills linked to this purchase order first.']);
            }

            return $this->changeStatus($order, 'cancelled', $validated['reason'], 'po_cancelled', ['cancelled_at' => now()]);
        });

        ActivityLogger::log('Purchase Order Cancelled', 'Purchase', 'Purchase order ' . $order->po_number . ' was cancelled.');

        return back()->with('success', 'Purchase order cancelled.');
    }

    public function close(Request $request, $id)
    {
        $validated = $request->validate(['reason' => 'required|string|max:2000']);

        $order = DB::transaction(function () use ($id, $validated) {
            $order = PurchaseOrder::lockForUpdate()->findOrFail($id);
            if (!in_array($order->status, ['partially_received', 'received'], true)) {
                throw ValidationException::withMessages(['purchase_order' => 'Only a received or partially received purchase order can be closed.']);
            }

            return $this->changeStatus($order, 'closed', $validated['reason'], 'po_closed', ['closed_at' => now()]);
        });

        ActivityLogger::log('Purchase Order Closed', 'Purchase', 'Purchase order ' . $order->po_number . ' was closed.');

        return back()->with('success', 'Purchase order closed. No further goods can be received against it.');
    }

    private function changeStatus(PurchaseOrder $order, $status, $reason, $event, array $extra)
    {
        $previous = $order->status;
        $order->fill($extra + ['status' => $status, 'status_reason' => $reason]);
        $order->save();
        ProcurementAuditLog::record($order, $event, $previous, $status, $reason);

        return $order;
    }
}
