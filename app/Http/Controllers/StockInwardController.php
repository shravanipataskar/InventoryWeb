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
        $listingStatus = $request->query('status') === 'inactive' ? 'inactive' : 'active';
        $stockInwards = StockInward::with([
                'product.category',
                'product.unit',
                'supplier',
                'store',
                'goodsReceiptItem.goodsReceipt.purchaseOrder',
            ])
            ->where('is_active', $listingStatus === 'active')
            ->orderBy('id', 'desc')
            ->get();

        return view('stock_inwards.index', compact('stockInwards', 'listingStatus'));
    }

    public function show($id)
    {
        $stockInward = StockInward::with([
            'product.category',
            'product.unit',
            'supplier',
            'store',
            'creator',
            'goodsReceiptItem.goodsReceipt.purchaseOrder',
        ])->findOrFail($id);

        return view('stock_inwards.show', compact('stockInward'));
    }

    public function create()
    {
        abort_unless(Auth::user()->role === 'admin', 403);

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
        $stores = DB::table('stores')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('stock_inwards.create', compact(
            'categories',
            'products',
            'productOptions',
            'suppliers',
            'stores'
        ));
    }

    public function edit($id)
    {
        $stockInward = StockInward::with(['product.category', 'supplier'])->findOrFail($id);
        abort_if($stockInward->isGoodsReceiptGenerated(), 403, 'Posted Goods Received inward entries cannot be edited.');
        abort_unless(Auth::user()->role === 'admin', 403);

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
        abort_unless(Auth::user()->role === 'admin', 403);

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
        abort_unless(Auth::user()->role === 'admin', 403);

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
        abort_unless(Auth::user()->role === 'admin', 403);

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