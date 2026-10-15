<?php

namespace App\Http\Controllers;

use App\Customer;
use App\StockOutward;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use RuntimeException;
use Throwable;

class CustomerController extends Controller
{
    private $customerTypes = [
        'Company',
        'School',
        'Retail',
        'Government',
        'Internal',
        'Other',
    ];

    public function index(Request $request)
    {
        $listingStatus = $request->query('status') === 'inactive' ? 'inactive' : 'active';
        $query = Customer::where('is_active', $listingStatus === 'active');

        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where(function ($customerQuery) use ($search) {
                $customerQuery->where('name', 'like', '%' . $search . '%')
                    ->orWhere('customer_type', 'like', '%' . $search . '%')
                    ->orWhere('contact_person', 'like', '%' . $search . '%')
                    ->orWhere('phone', 'like', '%' . $search . '%')
                    ->orWhere('email', 'like', '%' . $search . '%')
                    ->orWhere('city', 'like', '%' . $search . '%');
            });
        }

        $customers = $query->orderBy('name')->paginate(15)->appends($request->query());
        $totalCustomers = Customer::count();
        $activeCustomers = Customer::where('is_active', true)->count();
        $inactiveCustomers = $totalCustomers - $activeCustomers;

        return view('customers.index', compact(
            'customers',
            'totalCustomers',
            'activeCustomers',
            'inactiveCustomers',
            'listingStatus'
        ));
    }

    public function create()
    {
        $customerTypes = $this->customerTypes;

        return view('customers.create', compact('customerTypes'));
    }

    public function store(Request $request)
    {
        $validated = $this->validateCustomer($request);
        $validated['is_active'] = $request->input('status') === 'active';
        unset($validated['status']);
        $validated['customer_code'] = $this->generateCustomerCode();

        $uploadedDocuments = $this->storeDocuments($request, $validated);
        try {
            $customer = Customer::create($uploadedDocuments);
        } catch (Throwable $exception) {
            $this->deleteStoredDocuments($this->documentPaths($uploadedDocuments));
            throw $exception;
        }

        return redirect()
            ->route('customers.show', $customer->id)
            ->with('success', 'Customer created successfully.');
    }

    public function show($id)
    {
        $customer = Customer::findOrFail($id);
        $stockOutwards = StockOutward::with([
            'product.unit',
            'product.hall',
            'product.rack',
            'product.shelf',
        ])
            ->where(function ($query) use ($customer) {
                $query->where('customer_id', $customer->id)
                    ->orWhere(function ($legacyQuery) use ($customer) {
                        $legacyQuery->whereNull('customer_id')
                            ->where('issued_to', $customer->name);
                    });
            })
            ->orderBy('outward_date', 'desc')
            ->orderBy('id', 'desc')
            ->paginate(15);

        return view('customers.show', compact('customer', 'stockOutwards'));
    }

    public function edit($id)
    {
        $customer = Customer::findOrFail($id);
        $customerTypes = $this->customerTypes;

        return view('customers.edit', compact('customer', 'customerTypes'));
    }

    public function update(Request $request, $id)
    {
        $customer = Customer::findOrFail($id);
        $validated = $this->validateCustomer($request, $customer);
        $validated['is_active'] = $request->input('status') === 'active';
        unset($validated['status']);
        $oldDocuments = [];
        foreach (['aadhaar_card', 'pan_card'] as $document) {
            if ($request->hasFile($document)) {
                $oldDocuments[] = $customer->{$document};
            }
        }

        $uploadedDocuments = $this->storeDocuments($request, $validated);
        try {
            $customer->update($uploadedDocuments);
        } catch (Throwable $exception) {
            $this->deleteStoredDocuments($this->documentPaths($uploadedDocuments));
            throw $exception;
        }

        $this->deleteStoredDocuments(array_filter($oldDocuments));

        return redirect()
            ->route('customers.show', $customer->id)
            ->with('success', 'Customer updated successfully.');
    }

    public function document(Request $request, $id, $document)
    {
        abort_unless(in_array($document, ['aadhaar_card', 'pan_card'], true), 404);
        $user = auth()->user();
        abort_unless(
            $user && ($user->hasPermission('customers', 'view') || $user->hasPermission('customers', 'edit')),
            403
        );

        $customer = Customer::findOrFail($id);
        $path = $customer->{$document};
        abort_unless(
            $path && strpos($path, 'customers/documents/') === 0 && Storage::disk('local')->exists($path),
            404
        );

        if ($request->boolean('download')) {
            return Storage::disk('local')->download(
                $path,
                $document . '.' . pathinfo($path, PATHINFO_EXTENSION)
            );
        }

        return response()->file(Storage::disk('local')->path($path));
    }

    public function status(Request $request, $id)
    {
        $validated = $request->validate([
            'is_active' => 'required|boolean',
            'listing_status' => 'required|in:active,inactive',
        ]);

        $customer = Customer::findOrFail($id);
        $customer->is_active = $validated['is_active'];
        $customer->save();

        return redirect()
            ->route('customers.index', ['status' => $validated['listing_status']])
            ->with('success', $customer->is_active
                ? 'Customer activated successfully.'
                : 'Customer deactivated successfully.');
    }

    private function validateCustomer(Request $request, Customer $customer = null)
    {
        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('customers', 'name')->ignore($customer ? $customer->id : null),
            ],
            'customer_type' => ['required', Rule::in($this->customerTypes)],
            'contact_person' => 'nullable|string|max:255',
            'phone' => ['required', 'string', 'max:30', 'regex:/^\+?[0-9\s().-]{7,30}$/'],
            'email' => 'nullable|email|max:255',
            'store_warehouse_location' => 'nullable|string|max:1000',
            'address' => 'nullable|string|max:2000',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'pincode' => 'nullable|string|max:20',
            'gstin' => 'nullable|string|max:20',
            'pan' => 'nullable|string|max:20',
            'aadhaar_card' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'pan_card' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'status' => 'required|in:active,inactive',
        ], [
            'aadhaar_card.image' => 'Please upload a valid Aadhaar Card image.',
            'aadhaar_card.mimes' => 'Please upload a valid Aadhaar Card image.',
            'aadhaar_card.max' => 'Maximum file size is 5 MB.',
            'pan_card.image' => 'Please upload a valid PAN Card image.',
            'pan_card.mimes' => 'Please upload a valid PAN Card image.',
            'pan_card.max' => 'Maximum file size is 5 MB.',
        ]);
    }

    private function storeDocuments(Request $request, array $validated)
    {
        $storedPaths = [];
        try {
            foreach (['aadhaar_card', 'pan_card'] as $document) {
                if ($request->hasFile($document)) {
                    $path = $request->file($document)->store('customers/documents', 'local');
                    if (!$path) {
                        throw new RuntimeException('The customer document could not be stored.');
                    }

                    $validated[$document] = $path;
                    $storedPaths[] = $path;
                }
            }
        } catch (Throwable $exception) {
            $this->deleteStoredDocuments($storedPaths);
            throw $exception;
        }

        return $validated;
    }

    private function deleteStoredDocuments(array $paths)
    {
        foreach ($paths as $path) {
            if (!$path) {
                continue;
            }

            try {
                if (Storage::disk('local')->exists($path)
                    && !Storage::disk('local')->delete($path)) {
                    Log::error('Unable to remove a customer document during cleanup.', [
                        'operation' => 'customer_document.cleanup',
                        'document_path' => $path,
                    ]);
                }
            } catch (Throwable $cleanupException) {
                Log::error('Unable to remove a customer document during cleanup.', [
                    'operation' => 'customer_document.cleanup',
                    'document_path' => $path,
                    'exception' => get_class($cleanupException),
                ]);
            }
        }
    }

    private function documentPaths(array $attributes)
    {
        return array_filter([
            $attributes['aadhaar_card'] ?? null,
            $attributes['pan_card'] ?? null,
        ]);
    }

    private function generateCustomerCode()
    {
        do {
            $code = 'CUS-' . now()->format('ymd') . '-' . Str::upper(Str::random(6));
        } while (Customer::where('customer_code', $code)->exists());

        return $code;
    }
}
