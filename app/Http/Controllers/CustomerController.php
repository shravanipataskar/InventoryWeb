<?php

namespace App\Http\Controllers;

use App\Customer;
use App\StockOutward;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

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

        $customer = Customer::create($validated);

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
        $customer->update($validated);

        return redirect()
            ->route('customers.show', $customer->id)
            ->with('success', 'Customer updated successfully.');
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
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9\s().-]{7,30}$/'],
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:2000',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'pincode' => 'nullable|string|max:20',
            'gstin' => 'nullable|string|max:20',
            'pan' => 'nullable|string|max:20',
            'status' => 'required|in:active,inactive',
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
