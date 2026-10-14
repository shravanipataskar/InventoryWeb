<?php

namespace App\Http\Controllers;

use App\Category;
use App\ProcurementAuditLog;
use App\Product;
use App\PurchaseOrder;
use App\Services\PurchasePaymentAllocationService;
use App\Supplier;
use App\SupplierBill;
use App\Support\ActivityLogger;
use App\Support\Money;
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
        $editing = false;
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
            'prefillStoreId',
            'editing'
        ));
    }

    public function edit($id)
    {
        $purchaseOrder = PurchaseOrder::with('items.product')->findOrFail($id);
        abort_unless(
            $purchaseOrder->status === 'draft'
                && $purchaseOrder->goodsReceipts()->doesntExist()
                && $purchaseOrder->payments()->doesntExist()
                && (!Schema::hasTable('supplier_bills') || $purchaseOrder->supplierBills()->doesntExist()),
            404
        );

        $suppliers = Supplier::where('is_active', true)->orWhere('id', $purchaseOrder->supplier_id)->orderBy('name')->get();
        $categories = Category::orderBy('name')->get();
        $productIds = $purchaseOrder->items->pluck('product_id');
        $products = Product::with('unit')
            ->where(function ($query) use ($productIds) {
                $query->where('is_active', true)->orWhereIn('id', $productIds);
            })
            ->orderBy('name')
            ->get();
        $stores = DB::table('stores')->where('is_active', true)->orWhere('id', $purchaseOrder->store_id)->orderBy('name')->get();
        $prefillItems = $purchaseOrder->items->map(function ($item) {
            return [
                'product_id' => $item->product_id,
                'category_id' => optional($item->product)->category_id,
                'ordered_quantity' => $item->ordered_quantity,
                'purchase_rate' => $item->purchase_rate,
                'tax_percent' => $item->tax_percent,
            ];
        })->values()->all();
        $prefillStoreId = $purchaseOrder->store_id;
        $editing = true;

        return view('purchase_orders.create', compact(
            'purchaseOrder',
            'suppliers',
            'categories',
            'products',
            'stores',
            'prefillItems',
            'prefillStoreId',
            'editing'
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
            'items.*.tax_percent' => 'nullable|numeric|min:0|max:100',
        ]);

        $purchaseOrder = DB::transaction(function () use ($validated) {
            $totals = $this->calculateTotals($validated['items']);
            $order = PurchaseOrder::create([
                'po_number' => 'TEMP-' . \Illuminate\Support\Str::uuid(),
                'supplier_id' => $validated['supplier_id'],
                'store_id' => $validated['store_id'],
                'po_date' => $validated['order_date'],
                'expected_date' => $validated['expected_date'] ?? null,
                'status' => 'draft',
                'subtotal' => $totals['subtotal'],
                'discount_amount' => 0,
                'tax_amount' => $totals['tax'],
                'cgst_total' => $totals['cgst'],
                'sgst_total' => $totals['sgst'],
                'grand_total' => $totals['total'],
                'created_by' => Auth::id(),
            ]);
            $order->po_number = 'PO-' . date('Ymd', strtotime($validated['order_date']))
                . '-' . str_pad($order->id, 3, '0', STR_PAD_LEFT);
            $order->save();

            $this->writeItems($order, $validated['items']);
            ProcurementAuditLog::recordIfAvailable($order, 'po_created', null, $order->status);

            return $order;
        });

        return redirect()
            ->route('purchase-orders.show', $purchaseOrder->id)
            ->with('success', 'Purchase order created.');
    }

    public function update(Request $request, $id)
    {
        $existingOrder = PurchaseOrder::with('items')->findOrFail($id);
        $existingProductIds = $existingOrder->items->pluck('product_id');
        $validated = $request->validate([
            'supplier_id' => [
                'required',
                'integer',
                Rule::exists('suppliers', 'id')->where(function ($query) use ($existingOrder) {
                    $query->where('is_active', true)->orWhere('id', $existingOrder->supplier_id);
                }),
            ],
            'store_id' => [
                'required',
                'integer',
                Rule::exists('stores', 'id')->where(function ($query) use ($existingOrder) {
                    $query->where('is_active', true)->orWhere('id', $existingOrder->store_id);
                }),
            ],
            'order_date' => 'required|date',
            'expected_date' => 'nullable|date|after_or_equal:order_date',
            'items' => 'required|array|min:1',
            'items.*.product_id' => [
                'required',
                'distinct',
                Rule::exists('products', 'id')->where(function ($query) use ($existingProductIds) {
                    $query->where('is_active', true)->orWhereIn('id', $existingProductIds);
                }),
            ],
            'items.*.ordered_quantity' => 'required|numeric|min:0.01',
            'items.*.purchase_rate' => 'required|numeric|min:0.01',
            'items.*.tax_percent' => 'nullable|numeric|min:0|max:100',
        ]);

        $order = DB::transaction(function () use ($id, $validated) {
            $order = PurchaseOrder::lockForUpdate()->with('quotation')->findOrFail($id);
            $hasSupplierBills = Schema::hasTable('supplier_bills') && $order->supplierBills()->exists();
            if ($order->status !== 'draft' || $order->goodsReceipts()->exists() || $order->payments()->exists() || $hasSupplierBills) {
                throw ValidationException::withMessages(['purchase_order' => 'A draft purchase order can only be edited before payments, goods receipts, or supplier bills are linked.']);
            }

            if ($order->quotation_id && (int) $order->supplier_id !== (int) $validated['supplier_id']) {
                throw ValidationException::withMessages(['supplier_id' => 'The supplier on a quotation-linked purchase order cannot be changed.']);
            }

            $totals = $this->calculateTotals($validated['items']);
            $previousStatus = $order->status;
            $order->fill([
                'supplier_id' => $validated['supplier_id'],
                'store_id' => $validated['store_id'],
                'po_date' => $validated['order_date'],
                'expected_date' => $validated['expected_date'] ?? null,
                'subtotal' => $totals['subtotal'],
                'tax_amount' => $totals['tax'],
                'cgst_total' => $totals['cgst'],
                'sgst_total' => $totals['sgst'],
                'grand_total' => $totals['total'],
            ])->save();
            $order->items()->delete();
            $this->writeItems($order, $validated['items']);
            ProcurementAuditLog::recordIfAvailable($order, 'po_updated', $previousStatus, $order->status);

            return $order;
        });

        ActivityLogger::log('Purchase Order Updated', 'Purchase', 'Draft purchase order ' . $order->po_number . ' was updated.');

        return redirect()->route('purchase-orders.show', $order->id)->with('success', 'Draft purchase order updated.');
    }

    public function show(PurchasePaymentAllocationService $paymentAllocations, $id)
    {
        $purchaseOrder = PurchaseOrder::with([
            'supplier',
            'store',
            'items.product.category',
            'items.product.unit',
            'goodsReceipts.store',
            'goodsReceipts.items.product.unit',
        ])->findOrFail($id);
        $purchaseOrder->load(['quotation.request', 'creator', 'releaser', 'payments.enteredBy']);
        $billingEnabled = Schema::hasTable('supplier_bills');
        if ($billingEnabled) {
            $purchaseOrder->load([
                'supplierBills.allocations.payment',
                'supplierBills.purchasePaymentAllocations.purchasePayment',
            ]);
        }
        $history = $billingEnabled ? ProcurementAuditLog::forEntity($purchaseOrder)->get() : collect();
        if ($billingEnabled && $purchaseOrder->quotation) {
            $history = $history->merge(ProcurementAuditLog::forEntity($purchaseOrder->quotation)->get())
                ->sortBy('created_at')
                ->values();
        }
        $purchasePaid = Money::fromMinor($paymentAllocations->paidMinorForOrder($purchaseOrder->id));

        return view('purchase_orders.show', compact('purchaseOrder', 'history', 'billingEnabled', 'purchasePaid'));
    }

    public function release($id)
    {
        $order = DB::transaction(function () use ($id) {
            $order = PurchaseOrder::lockForUpdate()->findOrFail($id);
            if ($order->status !== 'draft') {
                throw ValidationException::withMessages(['purchase_order' => 'Only a draft purchase order can be released.']);
            }

            $previousStatus = $order->status;
            $order->update([
                'status' => 'released',
                'released_by' => Auth::id(),
                'released_at' => now(),
            ]);
            ProcurementAuditLog::record($order, 'po_released', $previousStatus, 'released');

            return $order;
        });

        ActivityLogger::log('Purchase Order Released', 'Purchase', 'Purchase order ' . $order->po_number . ' was released.');

        return redirect()->route('purchase-orders.show', $order->id)->with('success', 'Purchase order released and ready for goods receipt.');
    }

    public function cancel(Request $request, $id)
    {
        $validated = $request->validate(['reason' => 'required|string|max:2000']);

        $order = DB::transaction(function () use ($id, $validated) {
            $order = PurchaseOrder::lockForUpdate()->with('items')->findOrFail($id);
            if (!in_array($order->status, ['draft', 'released', 'ordered', 'pending', 'approved'], true)
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

    private function calculateTotals(array $items)
    {
        $subtotal = $cgst = $sgst = 0;
        foreach ($items as $item) {
            $basic = round((float) $item['ordered_quantity'] * (float) $item['purchase_rate'], 2);
            $gstRate = round((float) ($item['tax_percent'] ?? 0), 2);
            $cgstRate = round($gstRate / 2, 2);
            $sgstRate = round($gstRate - $cgstRate, 2);
            $subtotal += $basic;
            $cgst += round($basic * $cgstRate / 100, 2);
            $sgst += round($basic * $sgstRate / 100, 2);
        }

        return [
            'subtotal' => round($subtotal, 2),
            'cgst' => round($cgst, 2),
            'sgst' => round($sgst, 2),
            'tax' => round($cgst + $sgst, 2),
            'total' => round($subtotal + $cgst + $sgst, 2),
        ];
    }

    private function writeItems(PurchaseOrder $order, array $items)
    {
        foreach ($items as $line) {
            $quantity = round((float) $line['ordered_quantity'], 2);
            $rate = round((float) $line['purchase_rate'], 2);
            $gstRate = round((float) ($line['tax_percent'] ?? 0), 2);
            $cgstRate = round($gstRate / 2, 2);
            $sgstRate = round($gstRate - $cgstRate, 2);
            $basic = round($quantity * $rate, 2);
            $cgst = round($basic * $cgstRate / 100, 2);
            $sgst = round($basic * $sgstRate / 100, 2);
            $order->items()->create([
                'product_id' => $line['product_id'],
                'unit_id' => Product::findOrFail($line['product_id'])->unit_id,
                'quantity' => $quantity,
                'received_quantity' => 0,
                'purchase_rate' => $rate,
                'discount_percent' => 0,
                'tax_percent' => $gstRate,
                'total_amount' => round($basic + $cgst + $sgst, 2),
                'cgst_rate' => $cgstRate,
                'cgst_amount' => $cgst,
                'sgst_rate' => $sgstRate,
                'sgst_amount' => $sgst,
            ]);
        }
    }
}
