<?php

namespace App\Http\Controllers;

use App\GoodsReceipt;
use App\Product;
use App\PurchaseOrder;
use App\StockInward;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

class GoodsReceiptController extends Controller
{
    public function index()
    {
        $goodsReceipts = GoodsReceipt::with(['purchaseOrder', 'supplier', 'store'])
            ->withCount('items')
            ->orderByDesc('id')
            ->paginate(20);

        return view('goods_receipts.index', compact('goodsReceipts'));
    }

    public function create($purchaseOrderId)
    {
        $purchaseOrder = PurchaseOrder::with(['supplier', 'items.product.unit'])
            ->findOrFail($purchaseOrderId);

        if (!in_array($purchaseOrder->status, ['ordered', 'pending', 'approved', 'partially_received'], true)) {
            return redirect()->route('purchase-orders.show', $purchaseOrder->id)
                ->with('error', 'This purchase order has no remaining quantities to receive.');
        }

        $purchaseOrder->items = $purchaseOrder->items->filter(function ($item) {
            return (float) $item->received_quantity < (float) $item->ordered_quantity;
        })->values();
        $stores = DB::table('stores')->where('is_active', true)->orderBy('name')->get();

        return view('goods_receipts.create', compact('purchaseOrder', 'stores'));
    }

    public function store(Request $request, $purchaseOrderId)
    {
        $purchaseOrder = PurchaseOrder::findOrFail($purchaseOrderId);

        $validated = $request->validate([
            'store_id' => 'required|integer|exists:stores,id,is_active,1',
            'received_date' => 'required|date',
            'submission_token' => 'required|uuid',
            'invoice_number' => 'nullable|string|max:100',
            'remarks' => 'nullable|string|max:4000',
            'items' => 'required|array|min:1',
            'items.*.purchase_order_item_id' => 'required|integer|distinct|exists:purchase_order_items,id',
            'items.*.received_quantity' => 'required|numeric|min:0',
            'items.*.rejected_quantity' => 'required|numeric|min:0',
        ]);

        $existingReceipt = GoodsReceipt::where('submission_token', $validated['submission_token'])->first();
        if ($existingReceipt) {
            return redirect()->route('goods-receipts.show', $existingReceipt->id)
                ->with('success', 'This Goods Received transaction was already posted.');
        }

        try {
            $receipt = DB::transaction(function () use ($validated, $purchaseOrderId) {
            $order = PurchaseOrder::lockForUpdate()->with('items')->findOrFail($purchaseOrderId);
            if (!in_array($order->status, ['ordered', 'pending', 'approved', 'partially_received'], true)) {
                throw ValidationException::withMessages([
                    'items' => 'This purchase order is already fully received or cancelled.',
                ]);
            }

            $lines = [];
            $hasAcceptedUnits = false;
            foreach ($validated['items'] as $line) {
                $orderItem = $order->items->firstWhere('id', (int) $line['purchase_order_item_id']);
                if (!$orderItem) {
                    throw ValidationException::withMessages([
                        'items' => 'A received item does not belong to this purchase order.',
                    ]);
                }

                $received = round((float) $line['received_quantity'], 2);
                $rejected = round((float) $line['rejected_quantity'], 2);
                $remaining = round((float) $orderItem->ordered_quantity - (float) $orderItem->received_quantity, 2);
                if ($rejected > $received) {
                    throw ValidationException::withMessages([
                        'items' => 'Rejected quantity cannot exceed received quantity.',
                    ]);
                }
                if ($received > $remaining) {
                    throw ValidationException::withMessages([
                        'items' => 'Received quantity cannot exceed the remaining ordered quantity.',
                    ]);
                }
                if ($received > 0 && $received <= $rejected) {
                    throw ValidationException::withMessages([
                        'items' => 'Each received item must have a positive accepted quantity.',
                    ]);
                }
                if ($received > 0) {
                    $hasAcceptedUnits = true;
                    $lines[] = [$orderItem, $received, $rejected];
                }
            }

            if (!$hasAcceptedUnits) {
                throw ValidationException::withMessages([
                    'items' => 'Enter a received quantity with at least one accepted unit.',
                ]);
            }

            $receipt = GoodsReceipt::create([
                'purchase_order_id' => $order->id,
                'submission_token' => $validated['submission_token'],
                'supplier_id' => $order->supplier_id,
                'store_id' => $validated['store_id'],
                'received_date' => $validated['received_date'],
                'invoice_number' => $validated['invoice_number'] ?? null,
                'remarks' => $validated['remarks'] ?? null,
                'status' => 'posted',
                'received_by' => Auth::id(),
                'created_by' => Auth::id(),
            ]);
            $receipt->grn_number = 'GRN-' . date('Ymd', strtotime($validated['received_date']))
                . '-' . str_pad($receipt->id, 3, '0', STR_PAD_LEFT);
            $receipt->save();

            foreach ($lines as list($orderItem, $received, $rejected)) {
                $accepted = round($received - $rejected, 2);
                $receiptItem = $receipt->items()->create([
                    'purchase_order_item_id' => $orderItem->id,
                    'product_id' => $orderItem->product_id,
                    'ordered_quantity' => $orderItem->ordered_quantity,
                    'received_quantity' => $received,
                    'rejected_quantity' => $rejected,
                    'accepted_quantity' => $accepted,
                    'purchase_rate' => $orderItem->purchase_rate,
                ]);
                $orderItem->received_quantity = round((float) $orderItem->received_quantity + $received, 2);
                $orderItem->save();

                $product = Product::lockForUpdate()->findOrFail($orderItem->product_id);
                $product->current_stock = round((float) $product->current_stock + $accepted, 2);
                $product->save();

                $subtotal = round($accepted * (float) $orderItem->purchase_rate, 2);
                $inward = StockInward::create([
                    'product_id' => $product->id,
                    'supplier_id' => $order->supplier_id,
                    'invoice_number' => $receipt->invoice_number,
                    'inward_date' => $receipt->received_date,
                    'quantity' => $accepted,
                    'received_quantity' => $received,
                    'rejected_quantity' => $rejected,
                    'purchase_price' => $orderItem->purchase_rate,
                    'total_amount' => $subtotal,
                    'subtotal' => $subtotal,
                    'grand_total' => $subtotal,
                    'remarks' => $receipt->remarks,
                    'goods_receipt_item_id' => $receiptItem->id,
                    'store_id' => $receipt->store_id,
                    'status' => 'posted',
                    'created_by' => Auth::id(),
                    'is_active' => true,
                ]);
                $inward->inward_number = 'INW-' . date('Ymd', strtotime($receipt->received_date))
                    . '-' . str_pad($inward->id, 4, '0', STR_PAD_LEFT);
                $inward->save();

                $storeBalance = (float) DB::table('stock_transactions')
                    ->where('store_id', $receipt->store_id)
                    ->where('product_id', $product->id)
                    ->sum(DB::raw('quantity_in - quantity_out'));
                DB::table('stock_transactions')->insert([
                    'store_id' => $receipt->store_id,
                    'product_id' => $product->id,
                    'transaction_type' => 'purchase',
                    'reference_type' => 'stock_inward',
                    'reference_id' => $inward->id,
                    'quantity_in' => $accepted,
                    'quantity_out' => 0,
                    'balance_quantity' => round($storeBalance + $accepted, 2),
                    'unit_price' => $orderItem->purchase_rate,
                    'transaction_date' => $receipt->received_date,
                    'remarks' => $receipt->grn_number,
                    'created_by' => Auth::id(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $allReceived = $order->items->every(function ($item) {
                return (float) $item->received_quantity >= (float) $item->ordered_quantity;
            });
            $order->status = $allReceived ? 'received' : 'partially_received';
            $order->save();
                return $receipt;
            });
        } catch (QueryException $exception) {
            $existingReceipt = GoodsReceipt::where('submission_token', $validated['submission_token'])->first();
            if (!$existingReceipt) {
                throw $exception;
            }

            return redirect()->route('goods-receipts.show', $existingReceipt->id)
                ->with('success', 'This Goods Received transaction was already posted.');
        }

        return redirect()
            ->route('goods-receipts.show', $receipt->id)
            ->with('success', 'Goods received and stock inward posted successfully.');
    }

    public function show($id)
    {
        $goodsReceipt = GoodsReceipt::with([
            'purchaseOrder',
            'supplier',
            'store',
            'receiver',
            'items.product.category',
            'items.product.unit',
            'items.purchaseOrderItem',
            'items.stockInward',
        ])->findOrFail($id);

        return view('goods_receipts.show', compact('goodsReceipt'));
    }
}
