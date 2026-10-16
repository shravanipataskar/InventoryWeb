<?php

namespace App\Http\Controllers;

use App\PurchaseOrder;
use App\Services\PurchasePaymentAllocationService;
use App\Supplier;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class SupplierController extends Controller
{
    public function index(Request $request)
    {
        $listingStatus = $request->query('status') === 'inactive' ? 'inactive' : 'active';
        $suppliers = Supplier::where('is_active', $listingStatus === 'active')
            ->orderBy('id', 'desc')
            ->get();

        return view('suppliers.index', compact('suppliers', 'listingStatus'));
    }

    public function create()
    {
        return view('suppliers.create');
    }

    public function store(Request $request)
    {
        $this->normalizeIdentityFields($request);
        $validated = $request->validate([
            'supplier_code' => 'required|string|max:100|unique:suppliers,supplier_code',
            'name' => 'required|string|max:255',
            'company_name' => 'required|string|max:255|unique:suppliers,company_name',
            'warehouse_location' => 'nullable|string|max:1000',
            'phone' => 'required|string|max:20',
            'email' => 'required|email|max:255',
            'gst_number' => 'nullable|string|max:50',
            'pan_number' => 'nullable|string|max:20',
            'aadhaar_card' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'pan_card' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'address' => 'nullable|string|max:2000',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'pincode' => 'nullable|string|max:20',
            'payment_terms' => 'nullable|in:due_on_receipt,net_7,net_15,net_30,net_45,net_60',
            'bank_name' => 'nullable|string|max:255',
            'account_holder_name' => 'nullable|string|max:255',
            'account_number' => 'nullable|string|max:100',
            'ifsc_code' => 'nullable|string|max:20',
            'branch_name' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:5000',
        ], [
            'supplier_code.unique' => 'Supplier already exists.',
            'company_name.unique' => 'Supplier already exists.',
        ]);

        $this->validateActiveDuplicate($validated);

        // Start transaction for supplier creation and document storage
        try {
            DB::beginTransaction();

            $validated = $this->storeDocuments($request, $validated);
            Supplier::create(array_merge($validated, [
                'is_active' => 1,
            ]));

            DB::commit();
        } catch (Throwable $exception) {
            DB::rollBack();
            throw $exception;
        }

        return redirect()
            ->route('suppliers.index')
            ->with('success', 'Supplier created successfully.');
    }

    public function edit($id)
    {
        $supplier = Supplier::findOrFail($id);

        return view('suppliers.edit', compact('supplier'));
    }

    public function update(Request $request, $id)
    {
        $supplier = Supplier::findOrFail($id);
        $this->normalizeIdentityFields($request);
        $validated = $request->validate([
            'supplier_code' => ['required', 'string', 'max:100', Rule::unique('suppliers', 'supplier_code')->ignore($supplier->id)],
            'name' => 'required|string|max:255',
            'company_name' => ['required', 'string', 'max:255', Rule::unique('suppliers', 'company_name')->ignore($supplier->id)],
            'warehouse_location' => 'nullable|string|max:1000',
            'phone' => 'required|string|max:20',
            'email' => 'required|email|max:255',
            'gst_number' => 'nullable|string|max:50',
            'pan_number' => 'nullable|string|max:20',
            'aadhaar_card' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'pan_card' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'address' => 'nullable|string|max:2000',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'pincode' => 'nullable|string|max:20',
            'payment_terms' => 'nullable|in:due_on_receipt,net_7,net_15,net_30,net_45,net_60',
            'bank_name' => 'nullable|string|max:255',
            'account_holder_name' => 'nullable|string|max:255',
            'account_number' => 'nullable|string|max:100',
            'ifsc_code' => 'nullable|string|max:20',
            'branch_name' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:5000',
        ], [
            'supplier_code.unique' => 'Supplier already exists.',
            'company_name.unique' => 'Supplier already exists.',
        ]);

        $this->validateActiveDuplicate($validated, $supplier->id);

        $oldDocuments = [];
        foreach (['aadhaar_card', 'pan_card'] as $document) {
            if ($request->hasFile($document)) {
                $oldDocuments[] = $supplier->{$document};
            }
        }

        // Start transaction for supplier update and document replacement
        try {
            DB::beginTransaction();

            $supplier->update($this->storeDocuments($request, $validated));
            foreach ($oldDocuments as $oldDocument) {
                if ($oldDocument && Storage::disk('local')->exists($oldDocument)) {
                    Storage::disk('local')->delete($oldDocument);
                }
            }

            DB::commit();
        } catch (Throwable $exception) {
            DB::rollBack();
            throw $exception;
        }

        return redirect()
            ->route('suppliers.index')
            ->with('success', 'Supplier updated successfully.');
    }

    public function status(Request $request, $id)
    {
        $validated = $request->validate([
            'is_active' => 'required|boolean',
            'listing_status' => 'required|in:active,inactive',
        ]);

        // Start transaction for updating supplier status
        try {
            DB::beginTransaction();

            $supplier = Supplier::findOrFail($id);
            $supplier->is_active = $validated['is_active'];
            $supplier->save();

            DB::commit();
        } catch (Throwable $exception) {
            DB::rollBack();
            throw $exception;
        }

        return redirect()
            ->route('suppliers.index', ['status' => $validated['listing_status']])
            ->with('success', $supplier->is_active
                ? 'Supplier activated successfully.'
                : 'Supplier deactivated successfully. The record is retained.');
    }

    public function destroy($id)
    {
        // Start transaction for supplier deactivation
        try {
            DB::beginTransaction();

            $supplier = Supplier::findOrFail($id);
            $supplier->is_active = false;
            $supplier->save();

            DB::commit();
        } catch (Throwable $exception) {
            DB::rollBack();
            throw $exception;
        }

        return redirect()
            ->route('suppliers.index')
            ->with('success', 'Supplier deactivated successfully. The record is retained.');
    }

    public function document($id, $document, Request $request)
    {
        abort_unless(in_array($document, ['aadhaar_card', 'pan_card'], true), 404);

        $supplier = Supplier::findOrFail($id);
        $path = $supplier->{$document};
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        if ($request->boolean('download')) {
            return Storage::disk('local')->download($path, $document . '.' . pathinfo($path, PATHINFO_EXTENSION));
        }

        return response()->file(Storage::disk('local')->path($path));
    }

    public function show(PurchasePaymentAllocationService $paymentAllocations, $id)
    {
        $supplier = Supplier::findOrFail($id);
        $canViewPurchaseHistory = request()->user()->hasPermission('purchase', 'view');
        $purchaseOrders = $canViewPurchaseHistory
            ? PurchaseOrder::withCount('items')->with('payments')->where('supplier_id', $supplier->id)->orderByDesc('po_date')->paginate(10, ['*'], 'purchase_page')
            : null;

        if ($purchaseOrders) {
            $purchaseOrders->getCollection()->each(function ($order) use ($paymentAllocations) {
                $order->setAttribute('paid_total', \App\Support\Money::fromMinor(
                    $paymentAllocations->paidMinorForOrder($order->id)
                ));
            });
        }

        $purchaseSummary = $canViewPurchaseHistory ? [
            'count' => PurchaseOrder::where('supplier_id', $supplier->id)->count(),
            'value' => PurchaseOrder::where('supplier_id', $supplier->id)->where('status', '<>', 'cancelled')->sum('grand_total'),
            'pending' => PurchaseOrder::where('supplier_id', $supplier->id)
                ->whereIn('status', ['draft', 'released', 'ordered', 'pending', 'approved', 'partially_received'])->count(),
            'last_date' => PurchaseOrder::where('supplier_id', $supplier->id)->max('po_date'),
        ] : null;

        return view('suppliers.show', compact('supplier', 'canViewPurchaseHistory', 'purchaseOrders', 'purchaseSummary'));
    }

    private function normalizeIdentityFields(Request $request)
    {
        foreach (['supplier_code', 'company_name', 'name', 'phone', 'email'] as $field) {
            if ($request->has($field) && is_string($request->input($field))) {
                $request->merge([$field => trim($request->input($field))]);
            }
        }
    }

    private function validateActiveDuplicate(array $validated, $ignoreId = null)
    {
        $duplicate = Supplier::where('is_active', true)
            ->when($ignoreId, function ($query) use ($ignoreId) {
                $query->where('id', '<>', $ignoreId);
            })
            ->where(function ($query) use ($validated) {
                $query->whereRaw('LOWER(TRIM(email)) = ?', [mb_strtolower($validated['email'])])
                    ->orWhere(function ($query) use ($validated) {
                        $query->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower($validated['name'])])
                            ->whereRaw('TRIM(phone) = ?', [$validated['phone']]);
                    });
            })
            ->exists();

        if ($duplicate) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'supplier_code' => 'Supplier already exists.',
            ]);
        }
    }

    private function storeDocuments(Request $request, array $validated)
    {
        foreach (['aadhaar_card', 'pan_card'] as $document) {
            if ($request->hasFile($document)) {
                $validated[$document] = $request->file($document)->store('supplier-documents', 'local');
            }
        }

        return $validated;
    }
}