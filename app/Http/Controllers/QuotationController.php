<?php

namespace App\Http\Controllers;

use App\Category;
use App\Product;
use App\Quotation;
use App\QuotationRequest;
use App\Store;
use App\Supplier;
use App\PurchaseOrder;
use App\Support\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class QuotationController extends Controller
{
    public function index(Request $request)
    {
        $requests = QuotationRequest::with(['creator', 'quotations.supplier'])
            ->withCount('quotations')
            ->orderByDesc('id')
            ->paginate(20)
            ->appends($request->query());

        return view('quotations.index', compact('requests'));
    }

    public function create()
    {
        $categories = Category::where('is_active', true)->orderBy('name')->get();
        $products = Product::where('is_active', true)->with(['category', 'unit'])->orderBy('name')->get();
        $suppliers = Supplier::where('is_active', true)->orderBy('name')->get();
        $stores = Store::where('is_active', true)->orderBy('name')->get();

        return view('quotations.create', compact('categories', 'products', 'suppliers', 'stores'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'request_date' => 'required|date',
            'required_date' => 'nullable|date|after_or_equal:request_date',
            'store_id' => 'required|integer|exists:stores,id,is_active,1',
            'remarks' => 'nullable|string|max:4000',
            'supplier_ids' => 'required|array|min:1',
            'supplier_ids.*' => 'integer|distinct|exists:suppliers,id,is_active,1',
            'items' => 'required|array|min:1',
            'items.*.category_id' => 'required|exists:categories,id',
            'items.*.product_id' => [
                'required',
                'distinct',
                Rule::exists('products', 'id')->where('is_active', true),
            ],
            'items.*.quantity' => 'required|numeric|min:0.01',
        ]);

        foreach ($validated['items'] as $item) {
            if (!Product::where('id', $item['product_id'])->where('category_id', $item['category_id'])->where('is_active', true)->exists()) {
                throw ValidationException::withMessages(['items' => 'Each product must belong to its selected category.']);
            }
        }

        $quotationRequest = DB::transaction(function () use ($validated) {
            $requestRecord = QuotationRequest::create([
                'quotation_request_code' => 'TEMP-' . Str::uuid(),
                'request_date' => $validated['request_date'],
                'required_date' => $validated['required_date'] ?? null,
                'store_id' => $validated['store_id'],
                'created_by' => Auth::id(),
                'status' => 'draft',
                'remarks' => $validated['remarks'] ?? null,
            ]);
            $requestRecord->quotation_request_code = 'QR-' . date('Ymd', strtotime($validated['request_date']))
                . '-' . str_pad($requestRecord->id, 4, '0', STR_PAD_LEFT);
            $requestRecord->save();

            foreach ($validated['supplier_ids'] as $supplierId) {
                $quotation = $requestRecord->quotations()->create([
                    'supplier_id' => $supplierId,
                    'quotation_code' => 'TEMP-' . Str::uuid(),
                    'quotation_date' => $validated['request_date'],
                    'status' => 'draft',
                    'created_by' => Auth::id(),
                ]);
                $quotation->quotation_code = 'QT-' . date('Ymd', strtotime($validated['request_date']))
                    . '-' . str_pad($quotation->id, 4, '0', STR_PAD_LEFT);
                $quotation->save();

                foreach ($validated['items'] as $item) {
                    $product = Product::findOrFail($item['product_id']);
                    $quotation->items()->create([
                        'product_id' => $product->id,
                        'category_id' => $product->category_id,
                        'unit_id' => $product->unit_id,
                        'quantity' => round((float) $item['quantity'], 2),
                    ]);
                }
            }

            return $requestRecord;
        });

        ActivityLogger::log('Quotation Created', 'Quotation', 'Quotation request ' . $quotationRequest->quotation_request_code . ' was created.');

        return redirect()->route('quotations.show', $quotationRequest->id)
            ->with('success', 'Quotation request created for the selected suppliers.');
    }

    public function show($id)
    {
        $quotationRequest = $this->loadRequest($id);

        return view('quotations.show', compact('quotationRequest'));
    }

    public function approval()
    {
        $quotations = QuotationRequest::with([
                'creator',
                'quotations.supplier',
                'quotations.items',
            ])
            ->whereIn('status', ['submitted', 'under_review'])
            ->orderByDesc('id')
            ->get();
        $quotations->each(function ($requestRecord) {
            $requestRecord->setAttribute('quotations_count', $requestRecord->quotations->count());
            $requestRecord->setAttribute('product_count', $requestRecord->quotations
                ->flatMap(function ($quotation) {
                    return $quotation->items->pluck('product_id');
                })
                ->unique()
                ->count());
        });

        return view('quotations.approval', compact('quotations'));
    }

    public function submit(Request $request, $id)
    {
        $quotation = Quotation::with('items')->findOrFail($id);
        abort_unless(in_array($quotation->status, ['draft', 'under_review'], true), 422, 'This quotation cannot be submitted.');

        $validated = $request->validate([
            'items' => 'required|array',
            'items.*.supplier_rate' => 'required|numeric|min:0',
            'items.*.gst_rate' => 'required|numeric|min:0|max:100',
        ]);

        DB::transaction(function () use ($quotation, $validated) {
            $subtotal = $cgstTotal = $sgstTotal = 0;
            foreach ($quotation->items as $item) {
                if (!isset($validated['items'][$item->id])) {
                    throw ValidationException::withMessages(['items' => 'Every quotation item must be completed.']);
                }
                $line = $validated['items'][$item->id];
                $rate = round((float) $line['supplier_rate'], 2);
                $quantity = round((float) $item->quantity, 2);
                $basic = round($quantity * $rate, 2);
                $gstRate = round((float) $line['gst_rate'], 2);
                $cgstRate = round($gstRate / 2, 2);
                $sgstRate = round($gstRate - $cgstRate, 2);
                $cgst = round($basic * $cgstRate / 100, 2);
                $sgst = round($basic * $sgstRate / 100, 2);
                $item->update([
                    'supplier_rate' => $rate,
                    'basic_amount' => $basic,
                    'gst_rate' => $gstRate,
                    'cgst_rate' => $cgstRate,
                    'cgst_amount' => $cgst,
                    'sgst_rate' => $sgstRate,
                    'sgst_amount' => $sgst,
                    'tax_amount' => round($cgst + $sgst, 2),
                    'total_amount' => round($basic + $cgst + $sgst, 2),
                ]);
                $subtotal += $basic;
                $cgstTotal += $cgst;
                $sgstTotal += $sgst;
            }
            $quotation->update([
                'status' => 'submitted',
                'subtotal' => round($subtotal, 2),
                'cgst_total' => round($cgstTotal, 2),
                'sgst_total' => round($sgstTotal, 2),
                'tax_total' => round($cgstTotal + $sgstTotal, 2),
                'grand_total' => round($subtotal + $cgstTotal + $sgstTotal, 2),
                'submitted_at' => now(),
                'submitted_by' => Auth::id(),
            ]);
            $quotation->request()->update(['status' => 'submitted']);
        });

        ActivityLogger::log('Quotation Submitted', 'Quotation', 'Quotation ' . $quotation->quotation_code . ' was submitted.');

        return redirect()->route('quotations.show', $quotation->quotation_request_id)
            ->with('success', 'Supplier quotation submitted for authority review.');
    }

    public function approve($id)
    {
        $quotation = DB::transaction(function () use ($id) {
            $quotation = Quotation::lockForUpdate()->findOrFail($id);
            $requestRecord = QuotationRequest::lockForUpdate()->findOrFail($quotation->quotation_request_id);
            if ($requestRecord->status === 'approved' || $quotation->status === 'approved' || $quotation->purchase_order_id) {
                throw ValidationException::withMessages(['quotation' => 'This quotation has already been approved.']);
            }
            if (!in_array($requestRecord->status, ['submitted', 'under_review'], true)
                || !in_array($quotation->status, ['submitted', 'under_review'], true)) {
                throw ValidationException::withMessages(['quotation' => 'Only submitted quotations can be approved.']);
            }
            if (Quotation::where('quotation_request_id', $requestRecord->id)->where('status', 'approved')->exists()) {
                throw ValidationException::withMessages(['quotation' => 'Another quotation is already approved for this request.']);
            }

            $quotation->update(['status' => 'approved', 'approved_at' => now(), 'approved_by' => Auth::id()]);
            Quotation::where('quotation_request_id', $requestRecord->id)
                ->where('id', '<>', $quotation->id)
                ->whereNotIn('status', ['approved'])
                ->update(['status' => 'rejected', 'rejected_at' => now(), 'rejected_by' => Auth::id(), 'rejection_reason' => 'Another supplier quotation was approved.']);
            $requestRecord->update(['status' => 'approved']);

            return $quotation;
        });

        ActivityLogger::log('Quotation Approved', 'Quotation', 'Quotation ' . $quotation->quotation_code . ' was approved.');

        return back()->with('success', 'Quotation approved. Other quotations for this request were rejected.');
    }

    public function reject(Request $request, $id)
    {
        $validated = $request->validate(['rejection_reason' => 'required|string|max:2000']);
        $quotation = DB::transaction(function () use ($id, $validated) {
            $quotation = Quotation::lockForUpdate()->findOrFail($id);
            $requestRecord = QuotationRequest::lockForUpdate()->findOrFail($quotation->quotation_request_id);
            abort_if($requestRecord->status === 'approved', 422, 'An approved quotation request cannot be rejected.');
            abort_if($quotation->purchase_order_id, 422, 'A quotation linked to a purchase cannot be rejected.');
            Quotation::where('quotation_request_id', $requestRecord->id)->update([
                'status' => 'rejected',
                'rejected_at' => now(),
                'rejected_by' => Auth::id(),
                'rejection_reason' => $validated['rejection_reason'],
            ]);
            $requestRecord->update(['status' => 'rejected']);

            return $quotation;
        });
        ActivityLogger::log('Quotation Rejected', 'Quotation', 'Quotation ' . $quotation->quotation_code . ' was rejected.');

        return back()->with('success', 'Quotation rejected.');
    }

    public function generatePurchase($id)
    {
        $quotation = DB::transaction(function () use ($id) {
            $quotation = Quotation::with(['request', 'items'])->lockForUpdate()->findOrFail($id);
            $requestRecord = QuotationRequest::lockForUpdate()->findOrFail($quotation->quotation_request_id);
            if ($requestRecord->status !== 'approved' || $quotation->status !== 'approved') {
                throw ValidationException::withMessages(['quotation' => 'Only an approved quotation can generate a purchase.']);
            }
            if ($quotation->purchase_order_id || $requestRecord->quotations()->whereNotNull('purchase_order_id')->exists()) {
                throw ValidationException::withMessages(['quotation' => 'Purchase has already been generated for this quotation.']);
            }

            $order = PurchaseOrder::create([
                'po_number' => 'TEMP-' . Str::uuid(),
                'supplier_id' => $quotation->supplier_id,
                'store_id' => $quotation->request->store_id,
                'po_date' => $quotation->quotation_date,
                'expected_date' => $quotation->request->required_date,
                'status' => 'approved',
                'subtotal' => $quotation->subtotal,
                'discount_amount' => 0,
                'tax_amount' => $quotation->tax_total,
                'cgst_total' => $quotation->cgst_total,
                'sgst_total' => $quotation->sgst_total,
                'grand_total' => $quotation->grand_total,
                'notes' => 'Generated from quotation ' . $quotation->quotation_code,
                'created_by' => Auth::id(),
                'quotation_id' => $quotation->id,
                'quotation_request_id' => $quotation->quotation_request_id,
            ]);
            $order->po_number = 'PO-' . date('Ymd', strtotime($quotation->quotation_date))
                . '-' . str_pad($order->id, 3, '0', STR_PAD_LEFT);
            $order->save();

            foreach ($quotation->items as $item) {
                $order->items()->create([
                    'product_id' => $item->product_id,
                    'unit_id' => $item->unit_id,
                    'quantity' => $item->quantity,
                    'received_quantity' => 0,
                    'purchase_rate' => $item->supplier_rate,
                    'discount_percent' => 0,
                    'tax_percent' => $item->gst_rate,
                    'total_amount' => $item->total_amount,
                    'cgst_rate' => $item->cgst_rate,
                    'cgst_amount' => $item->cgst_amount,
                    'sgst_rate' => $item->sgst_rate,
                    'sgst_amount' => $item->sgst_amount,
                ]);
            }
            $quotation->update(['purchase_order_id' => $order->id]);

            return $quotation;
        });

        ActivityLogger::log('Purchase Generated from Quotation', 'Purchase', 'Purchase generated from quotation ' . $quotation->quotation_code . '.');

        return redirect()->route('purchase-orders.show', $quotation->purchase_order_id)
            ->with('success', 'Purchase order generated from the approved quotation.');
    }

    private function loadRequest($id)
    {
        return QuotationRequest::with([
            'creator',
            'store',
            'quotations.supplier',
            'quotations.items.product.category',
            'quotations.items.product.unit',
            'quotations.purchaseOrder',
        ])->findOrFail($id);
    }
}
