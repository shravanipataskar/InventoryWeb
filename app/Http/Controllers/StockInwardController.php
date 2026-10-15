<?php

namespace App\Http\Controllers;

use App\StockInward;
use App\Product;
use App\Supplier;
use App\Category;
use App\PurchaseOrder;
use App\GoodsReceipt;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StockInwardController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'status' => 'nullable|in:active,inactive',
            'search' => 'nullable|string|max:255',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'supplier_id' => 'nullable|integer|exists:suppliers,id',
            'product_id' => 'nullable|integer|exists:products,id',
            'store_id' => 'nullable|integer|exists:stores,id',
            'record_status' => 'nullable|in:all,posted,active,inactive',
        ]);
        $listingStatus = $filters['status'] ?? 'active';

        $query = StockInward::query()
            ->where('is_active', $listingStatus === 'active')
            ->when(!empty($filters['search']), function ($query) use ($filters) {
                $search = trim($filters['search']);
                $query->where(function ($query) use ($search) {
                    $query->where('inward_number', 'like', '%' . $search . '%')
                        ->orWhere('invoice_number', 'like', '%' . $search . '%')
                        ->orWhereHas('product', function ($query) use ($search) {
                            $query->where('name', 'like', '%' . $search . '%')
                                ->orWhere('product_code', 'like', '%' . $search . '%');
                        })
                        ->orWhereHas('supplier', function ($query) use ($search) {
                            $query->where('name', 'like', '%' . $search . '%');
                        })
                        ->orWhereHas('store', function ($query) use ($search) {
                            $query->where('name', 'like', '%' . $search . '%');
                        })
                        ->orWhereHas('goodsReceiptItem.goodsReceipt', function ($query) use ($search) {
                            $query->where('grn_number', 'like', '%' . $search . '%')
                                ->orWhereHas('purchaseOrder', function ($query) use ($search) {
                                    $query->where('po_number', 'like', '%' . $search . '%');
                                });
                        });
                });
            })
            ->when(!empty($filters['date_from']), function ($query) use ($filters) {
                $query->whereDate('inward_date', '>=', $filters['date_from']);
            })
            ->when(!empty($filters['date_to']), function ($query) use ($filters) {
                $query->whereDate('inward_date', '<=', $filters['date_to']);
            })
            ->when(!empty($filters['supplier_id']), function ($query) use ($filters) {
                $query->where('supplier_id', $filters['supplier_id']);
            })
            ->when(!empty($filters['product_id']), function ($query) use ($filters) {
                $query->where('product_id', $filters['product_id']);
            })
            ->when(!empty($filters['store_id']), function ($query) use ($filters) {
                $query->where('store_id', $filters['store_id']);
            })
            ->when(isset($filters['record_status']) && $filters['record_status'] !== 'all', function ($query) use ($filters) {
                if ($filters['record_status'] === 'posted') {
                    $query->whereNotNull('goods_receipt_item_id');
                } else {
                    $query->whereNull('goods_receipt_item_id')
                        ->where('is_active', $filters['record_status'] === 'active');
                }
            });

        $totals = (clone $query)->selectRaw(
            'COUNT(*) as inward_count, COALESCE(SUM(CASE WHEN goods_receipt_item_id IS NOT NULL AND received_quantity > 0 THEN received_quantity ELSE quantity END), 0) as total_quantity, COALESCE(SUM(subtotal), 0) as purchase_value, COALESCE(SUM(tax_total), 0) as tax_amount'
        )->first();

        $stockInwards = $query->with([
                'product.category',
                'product.unit',
                'supplier',
                'store',
                'goodsReceiptItem.goodsReceipt.purchaseOrder',
            ])
            ->orderBy('id', 'desc')
            ->paginate(20)
            ->appends($request->query());

        $suppliers = Supplier::orderBy('name')->get(['id', 'name']);
        $products = Product::orderBy('name')->get(['id', 'name']);
        $stores = \App\Store::orderBy('name')->get(['id', 'name']);

        return view('stock_inwards.index', compact(
            'stockInwards',
            'listingStatus',
            'filters',
            'totals',
            'suppliers',
            'products',
            'stores'
        ));
    }

    public function show(Request $request, $id)
    {
        $stockInward = StockInward::with([
            'product.category',
            'product.unit',
            'supplier',
            'store',
            'creator',
            'goodsReceiptItem.goodsReceipt.items.product.unit',
            'goodsReceiptItem.goodsReceipt.items.purchaseOrderItem',
            'goodsReceiptItem.goodsReceipt.items.stockInward',
            'goodsReceiptItem.goodsReceipt.purchaseOrder.items.product.unit',
        ])->findOrFail($id);

        $receiptItem = $stockInward->goodsReceiptItem;
        $goodsReceipt = $receiptItem ? $receiptItem->goodsReceipt : null;
        $purchaseOrder = $goodsReceipt ? $goodsReceipt->purchaseOrder : null;
        $receiptItems = $goodsReceipt ? $goodsReceipt->items : collect();
        $purchaseOrderItemIds = $receiptItems->pluck('purchase_order_item_id')->filter()->unique()->values();
        $previouslyReceived = collect();

        if ($goodsReceipt && $purchaseOrderItemIds->isNotEmpty()) {
            $previouslyReceived = DB::table('goods_receipt_items as gri')
                ->join('goods_receipts as gr', 'gr.id', '=', 'gri.goods_receipt_id')
                ->whereIn('gri.purchase_order_item_id', $purchaseOrderItemIds)
                ->where('gr.status', 'posted')
                ->where(function ($query) use ($goodsReceipt) {
                    $query->where('gr.received_date', '<', $goodsReceipt->received_date)
                        ->orWhere(function ($sameDate) use ($goodsReceipt) {
                            $sameDate->where('gr.received_date', $goodsReceipt->received_date)
                                ->where('gr.id', '<', $goodsReceipt->id);
                        });
                })
                ->groupBy('gri.purchase_order_item_id')
                ->selectRaw('gri.purchase_order_item_id, SUM(gri.received_quantity) as received_quantity')
                ->pluck('received_quantity', 'purchase_order_item_id');
        }

        if ($receiptItems->isEmpty()) {
            $items = collect([[
                'product' => $stockInward->product,
                'ordered_quantity' => null,
                'previously_received_quantity' => null,
                'received_quantity' => $stockInward->received_quantity ?: $stockInward->quantity,
                'accepted_quantity' => null,
                'rejected_quantity' => $stockInward->rejected_quantity,
                'unit' => optional($stockInward->product)->unit,
                'purchase_rate' => $stockInward->purchase_price,
                'subtotal' => $stockInward->subtotal,
                'discount_amount' => null,
                'tax_amount' => $stockInward->tax_total,
                'grand_total' => $stockInward->grand_total,
                'sgst_amount' => $stockInward->sgst_amount,
                'cgst_amount' => $stockInward->cgst_amount,
                'igst_amount' => null,
            ]]);
            $stockInwardIds = [$stockInward->id];
        } else {
            $items = $receiptItems->map(function ($item) use ($previouslyReceived) {
                $inward = $item->stockInward;
                $purchaseOrderItem = $item->purchaseOrderItem;
                $purchaseRate = $inward
                    ? $inward->purchase_price
                    : ($purchaseOrderItem ? $purchaseOrderItem->purchase_rate : $item->purchase_rate);
                $acceptedQuantity = $inward ? $inward->quantity : $item->accepted_quantity;
                $grossAmount = round((float) $acceptedQuantity * (float) $purchaseRate, 2);
                $storedTax = $inward ? (float) $inward->tax_total : 0;
                $storedCgst = $inward ? (float) $inward->cgst_amount : 0;
                $storedSgst = $inward ? (float) $inward->sgst_amount : 0;
                $cgstRate = $purchaseOrderItem
                    ? (float) $purchaseOrderItem->cgst_rate
                    : ($inward ? (float) $inward->cgst_rate : 0);
                $sgstRate = $purchaseOrderItem
                    ? (float) $purchaseOrderItem->sgst_rate
                    : ($inward ? (float) $inward->sgst_rate : 0);
                $taxRate = $purchaseOrderItem
                    ? (float) $purchaseOrderItem->tax_percent
                    : 0;
                $hasTaxRates = $cgstRate + $sgstRate > 0 || $taxRate > 0;

                if ($inward && ($storedCgst != 0 || $storedSgst != 0)) {
                    $cgstAmount = $storedCgst;
                    $sgstAmount = $storedSgst;
                    $taxAmount = $storedTax ?: round($cgstAmount + $sgstAmount, 2);
                    $igstAmount = 0;
                } elseif ($inward && $storedTax != 0 && !$hasTaxRates) {
                    $cgstAmount = null;
                    $sgstAmount = null;
                    $taxAmount = $storedTax;
                    $igstAmount = null;
                } else {
                    $discountRate = $purchaseOrderItem
                        ? (float) $purchaseOrderItem->discount_percent
                        : 0;
                    $discountAmount = round($grossAmount * $discountRate / 100, 2);
                    $taxableAmount = round($grossAmount - $discountAmount, 2);
                    if ($cgstRate + $sgstRate <= 0 && $taxRate > 0) {
                        $cgstRate = round($taxRate / 2, 2);
                        $sgstRate = round($taxRate - $cgstRate, 2);
                    }

                    $cgstAmount = round($taxableAmount * $cgstRate / 100, 2);
                    $sgstAmount = round($taxableAmount * $sgstRate / 100, 2);
                    $derivedTax = round($cgstAmount + $sgstAmount, 2);
                    $taxAmount = $storedTax ?: $derivedTax;
                    if ($storedTax && abs($storedTax - $derivedTax) > 0.01) {
                        $cgstAmount = null;
                        $sgstAmount = null;
                    }
                    $igstAmount = $cgstAmount === null ? null : 0;
                }

                return [
                    'product' => $item->product,
                    'ordered_quantity' => $item->purchaseOrderItem
                        ? $item->purchaseOrderItem->ordered_quantity
                        : $item->ordered_quantity,
                    'previously_received_quantity' => (float) ($previouslyReceived[$item->purchase_order_item_id] ?? 0),
                    'received_quantity' => $item->received_quantity,
                    'accepted_quantity' => $item->accepted_quantity,
                    'rejected_quantity' => $item->rejected_quantity,
                    'unit' => optional($item->product)->unit,
                    'purchase_rate' => $purchaseRate,
                    'subtotal' => $inward ? $inward->subtotal : null,
                    'discount_amount' => $purchaseOrderItem
                        ? round($grossAmount * (float) $purchaseOrderItem->discount_percent / 100, 2)
                        : null,
                    'tax_amount' => $inward ? $taxAmount : null,
                    'grand_total' => $inward ? $inward->grand_total : null,
                    'sgst_amount' => $inward ? $sgstAmount : null,
                    'cgst_amount' => $inward ? $cgstAmount : null,
                    'igst_amount' => $inward ? $igstAmount : null,
                ];
            });
            $stockInwardIds = $receiptItems->map(function ($item) {
                return optional($item->stockInward)->id;
            })->filter()->values()->all();
        }

        $quantitySummary = $items->groupBy(function ($item) {
            return $item['unit'] ? ($item['unit']->short_name ?: $item['unit']->name) : 'Unit unavailable';
        })->map(function ($unitItems) {
            return [
                'ordered' => $unitItems->contains(function ($item) {
                    return $item['ordered_quantity'] === null;
                }) ? null : $unitItems->sum('ordered_quantity'),
                'received' => $unitItems->sum('received_quantity'),
                'accepted' => $unitItems->contains(function ($item) {
                    return $item['accepted_quantity'] === null;
                }) ? null : $unitItems->sum('accepted_quantity'),
                'rejected' => $unitItems->sum('rejected_quantity'),
            ];
        });

        $financialSummary = [
            'subtotal' => $items->contains(function ($item) {
                return $item['subtotal'] === null;
            }) ? null : $items->sum('subtotal'),
            'discount' => $items->contains(function ($item) {
                return $item['discount_amount'] === null;
            }) ? null : $items->sum('discount_amount'),
            'tax' => $items->contains(function ($item) {
                return $item['tax_amount'] === null;
            }) ? null : $items->sum('tax_amount'),
            'grand_total' => $items->contains(function ($item) {
                return $item['grand_total'] === null;
            }) ? null : $items->sum('grand_total'),
            'cgst' => $items->contains(function ($item) {
                return $item['cgst_amount'] === null;
            }) ? null : $items->sum('cgst_amount'),
            'sgst' => $items->contains(function ($item) {
                return $item['sgst_amount'] === null;
            }) ? null : $items->sum('sgst_amount'),
            'igst' => $items->contains(function ($item) {
                return $item['igst_amount'] === null;
            }) ? null : $items->sum('igst_amount'),
        ];

        $supplierBill = null;
        $invoiceNumber = $goodsReceipt && $goodsReceipt->invoice_number
            ? $goodsReceipt->invoice_number
            : $stockInward->invoice_number;
        if ($goodsReceipt && $invoiceNumber) {
            $supplierBill = \App\SupplierBill::where('purchase_order_id', $goodsReceipt->purchase_order_id)
                ->where('supplier_id', $goodsReceipt->supplier_id)
                ->where('invoice_number', $invoiceNumber)
                ->first();
            if ($supplierBill && $items->contains(function ($item) {
                return $item['discount_amount'] === null;
            })) {
                $financialSummary['discount'] = $supplierBill->discount_amount;
            }
        }

        $stockMovements = DB::table('stock_transactions')
            ->where('reference_type', 'stock_inward')
            ->whereIn('reference_id', $stockInwardIds ?: [$stockInward->id])
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get();

        if ($request->ajax()) {
            return view('stock_inwards._details_modal', compact(
                'stockInward',
                'receiptItem',
                'goodsReceipt',
                'purchaseOrder',
                'supplierBill',
                'items',
                'quantitySummary',
                'financialSummary',
                'stockMovements'
            ));
        }

        return view('stock_inwards.show', compact('stockInward'));
    }

    public function create()
    {
        abort_unless(Auth::user()->isAdmin(), 403);

        return redirect()
            ->route('purchase-orders.index')
            ->with('success', 'Select a receivable purchase order to record goods through the GRN workflow.');
    }

    public function edit($id)
    {
        $stockInward = StockInward::with(['product.category', 'supplier'])->findOrFail($id);
        abort_if($stockInward->isGoodsReceiptGenerated(), 403, 'Posted Goods Received inward entries cannot be edited.');
        abort_if($stockInward->is_active, 403, 'Posted inward entries must be corrected through an authorized stock adjustment.');
        abort_unless(Auth::user()->isAdmin(), 403);

        $categories = Category::orderBy('name')->get();
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

        return view('stock_inwards.edit', compact(
            'stockInward',
            'categories',
            'products',
            'productOptions',
            'suppliers'
        ));
    }

    public function store(Request $request)
    {
        abort(403, 'Stock inward is created by posting goods received against a purchase order.');

        $validated = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'product_id' => [
                'required',
                Rule::exists('products', 'id')->where('category_id', $request->input('category_id')),
            ],
            'supplier_id' => 'required|exists:suppliers,id',
            'store_id' => 'required|integer|exists:stores,id,is_active,1',
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
            $product = Product::lockForUpdate()->findOrFail($validated['product_id']);
            $order = PurchaseOrder::create([
                'po_number' => 'TEMP-' . Str::uuid(),
                'supplier_id' => $validated['supplier_id'],
                'store_id' => $validated['store_id'],
                'po_date' => $validated['inward_date'],
                'status' => 'received',
                'subtotal' => $subtotal,
                'discount_amount' => 0,
                'tax_amount' => $taxTotal,
                'grand_total' => $grandTotal,
                'notes' => $validated['remarks'] ?? null,
                'created_by' => Auth::id(),
            ]);
            $order->po_number = 'PO-' . date('Ymd', strtotime($validated['inward_date']))
                . '-' . str_pad($order->id, 3, '0', STR_PAD_LEFT);
            $order->save();

            $orderItem = $order->items()->create([
                'product_id' => $product->id,
                'unit_id' => $product->unit_id,
                'quantity' => $quantity,
                'received_quantity' => $quantity,
                'purchase_rate' => $purchasePrice,
                'discount_percent' => 0,
                'tax_percent' => round($sgstRate + $cgstRate, 2),
                'total_amount' => $subtotal,
            ]);

            $receipt = GoodsReceipt::create([
                'purchase_order_id' => $order->id,
                'submission_token' => (string) Str::uuid(),
                'supplier_id' => $validated['supplier_id'],
                'store_id' => $validated['store_id'],
                'received_date' => $validated['inward_date'],
                'invoice_number' => $validated['invoice_number'] ?? null,
                'remarks' => $validated['remarks'] ?? null,
                'status' => 'posted',
                'received_by' => Auth::id(),
                'created_by' => Auth::id(),
            ]);
            $receipt->grn_number = 'GRN-' . date('Ymd', strtotime($validated['inward_date']))
                . '-' . str_pad($receipt->id, 3, '0', STR_PAD_LEFT);
            $receipt->save();

            $receiptItem = $receipt->items()->create([
                'purchase_order_item_id' => $orderItem->id,
                'product_id' => $product->id,
                'ordered_quantity' => $quantity,
                'received_quantity' => $quantity,
                'rejected_quantity' => 0,
                'accepted_quantity' => $quantity,
                'purchase_rate' => $purchasePrice,
            ]);

            $inward = StockInward::create([
                'product_id' => $validated['product_id'],
                'supplier_id' => $validated['supplier_id'],
                'invoice_number' => $validated['invoice_number'] ?? null,
                'inward_date' => $validated['inward_date'],
                'quantity' => $quantity,
                'received_quantity' => $quantity,
                'rejected_quantity' => 0,
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
                'goods_receipt_item_id' => $receiptItem->id,
                'store_id' => $validated['store_id'],
                'status' => 'posted',
                'created_by' => Auth::id(),
                'is_active' => true,
            ]);
            $inward->inward_number = 'INW-' . date('Ymd', strtotime($validated['inward_date']))
                . '-' . str_pad($inward->id, 4, '0', STR_PAD_LEFT);
            $inward->save();

            $product->increment('current_stock', $quantity);

            $storeBalance = (float) DB::table('stock_transactions')
                ->where('store_id', $validated['store_id'])
                ->where('product_id', $product->id)
                ->sum(DB::raw('quantity_in - quantity_out'));
            DB::table('stock_transactions')->insert([
                'store_id' => $validated['store_id'],
                'product_id' => $product->id,
                'transaction_type' => 'purchase',
                'reference_type' => 'stock_inward',
                'reference_id' => $inward->id,
                'quantity_in' => $quantity,
                'quantity_out' => 0,
                'balance_quantity' => round($storeBalance + $quantity, 2),
                'unit_price' => $purchasePrice,
                'transaction_date' => $validated['inward_date'],
                'remarks' => $receipt->grn_number,
                'created_by' => Auth::id(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return redirect()
            ->route('stock-inwards.index')
            ->with('success', 'Stock inward recorded and the related Purchase Order and Goods Received entry were created.');
    }

    public function update(Request $request, $id)
    {
        $stockInward = StockInward::findOrFail($id);
        abort_if($stockInward->isGoodsReceiptGenerated(), 403, 'Posted Goods Received inward entries cannot be edited.');
        abort_if($stockInward->is_active, 403, 'Posted inward entries must be corrected through an authorized stock adjustment.');
        abort_unless(Auth::user()->isAdmin(), 403);

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

        $originalQuantity = (float) $stockInward->quantity;
        $newQuantity = (float) $validated['quantity'];
        $newPurchasePrice = round((float) $validated['purchase_price'], 2);
        $newSgstRate = round((float) $validated['sgst_rate'], 2);
        $newCgstRate = round((float) $validated['cgst_rate'], 2);
        $newSubtotal = round($newQuantity * $newPurchasePrice, 2);
        $newSgstAmount = round($newSubtotal * $newSgstRate / 100, 2);
        $newCgstAmount = round($newSubtotal * $newCgstRate / 100, 2);
        $newTaxTotal = round($newSgstAmount + $newCgstAmount, 2);
        $newGrandTotal = round($newSubtotal + $newTaxTotal, 2);

        DB::transaction(function () use ($stockInward, $validated, $originalQuantity, $newQuantity, $newPurchasePrice, $newSgstRate, $newCgstRate, $newSubtotal, $newSgstAmount, $newCgstAmount, $newTaxTotal, $newGrandTotal) {
            $oldProductId = $stockInward->product_id;
            $newProductId = (int) $validated['product_id'];

            $product = Product::lockForUpdate()->findOrFail($oldProductId);
            if ($stockInward->is_active) {
                $product->decrement('current_stock', $originalQuantity);
            }

            if ($oldProductId !== $newProductId) {
                $replacementProduct = Product::lockForUpdate()->findOrFail($newProductId);
                if ($stockInward->is_active) {
                    $replacementProduct->increment('current_stock', $newQuantity);
                }
                $stockInward->product_id = $newProductId;
            } elseif ($stockInward->is_active) {
                $product->increment('current_stock', $newQuantity);
            }

            $stockInward->fill([
                'product_id' => $newProductId,
                'supplier_id' => $validated['supplier_id'],
                'invoice_number' => $validated['invoice_number'] ?? null,
                'inward_date' => $validated['inward_date'],
                'quantity' => $newQuantity,
                'purchase_price' => $newPurchasePrice,
                'total_amount' => $newSubtotal,
                'sgst_rate' => $newSgstRate,
                'cgst_rate' => $newCgstRate,
                'sgst_amount' => $newSgstAmount,
                'cgst_amount' => $newCgstAmount,
                'tax_total' => $newTaxTotal,
                'subtotal' => $newSubtotal,
                'grand_total' => $newGrandTotal,
                'remarks' => $validated['remarks'] ?? null,
            ]);
            $stockInward->save();
        });

        return redirect()
            ->route('stock-inwards.index')
            ->with('success', 'Stock inward updated successfully.');
    }

    public function status(Request $request, $id)
    {
        abort_unless(Auth::user()->isAdmin(), 403);

        $validated = $request->validate([
            'is_active' => 'required|boolean',
            'listing_status' => 'required|in:active,inactive',
        ]);

        DB::transaction(function () use ($id, $validated) {
            $stockInward = StockInward::lockForUpdate()->findOrFail($id);
            if ($stockInward->isGoodsReceiptGenerated()) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'status' => 'Goods Received inward entries cannot be deactivated; correct the source receipt through a stock adjustment.',
                ]);
            }

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