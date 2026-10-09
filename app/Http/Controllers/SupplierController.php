<?php

namespace App\Http\Controllers;

use App\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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
        $validated = $request->validate([
            'supplier_code' => 'required|string|max:100|unique:suppliers,supplier_code',
            'name' => 'required|string|max:255',
            'company_name' => 'required|string|max:255',
            'warehouse_location' => 'nullable|string|max:1000',
            'phone' => 'required|string|max:20',
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
        ]);

        $validated = $this->storeDocuments($request, $validated);
        Supplier::create(array_merge($validated, [
            'is_active' => 1,
        ]));

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
        $validated = $request->validate([
            'supplier_code' => 'required|string|max:100|unique:suppliers,supplier_code,' . $supplier->id,
            'name' => 'required|string|max:255',
            'company_name' => 'required|string|max:255',
            'warehouse_location' => 'nullable|string|max:1000',
            'phone' => 'required|string|max:20',
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
        ]);

        $oldDocuments = [];
        foreach (['aadhaar_card', 'pan_card'] as $document) {
            if ($request->hasFile($document)) {
                $oldDocuments[] = $supplier->{$document};
            }
        }
        $supplier->update($this->storeDocuments($request, $validated));
        foreach ($oldDocuments as $oldDocument) {
            if ($oldDocument && Storage::disk('local')->exists($oldDocument)) {
                Storage::disk('local')->delete($oldDocument);
            }
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

        $supplier = Supplier::findOrFail($id);
        $supplier->is_active = $validated['is_active'];
        $supplier->save();

        return redirect()
            ->route('suppliers.index', ['status' => $validated['listing_status']])
            ->with('success', $supplier->is_active
                ? 'Supplier activated successfully.'
                : 'Supplier deactivated successfully. The record is retained.');
    }

    public function destroy($id)
    {
        $supplier = Supplier::findOrFail($id);
        $supplier->is_active = false;
        $supplier->save();

        return redirect()
            ->route('suppliers.index')
            ->with('success', 'Supplier deactivated successfully. The record is retained.');
    }

    public function document($id, $document)
    {
        abort_unless(in_array($document, ['aadhaar_card', 'pan_card'], true), 404);

        $supplier = Supplier::findOrFail($id);
        $path = $supplier->{$document};
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download($path, $document . '.' . pathinfo($path, PATHINFO_EXTENSION));
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