<?php

namespace App\Http\Controllers;

use App\Company;
use App\Store;
use App\Hall;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Throwable;

class LocationController extends Controller
{
    /**
     * Show the location setup wizard (initial page)
     */
    public function setup()
    {
        $companies = Company::where('is_active', true)->orderBy('name')->get();
        $hasExistingLocations = Store::exists();

        return view('locations.setup', compact('companies', 'hasExistingLocations'));
    }

    /**
     * Display all inventory locations (location management page)
     */
    public function index(Request $request)
    {
        $query = Store::with('company')->withCount('halls');

        // Apply filters
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('location', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $status = $request->input('status');
            if ($status === 'active') {
                $query->where('is_active', true);
            } elseif ($status === 'inactive') {
                $query->where('is_active', false);
            }
        }

        $locations = $query->orderBy('name')->paginate(20)->appends($request->query());

        $totalCount = Store::count();
        $activeCount = Store::where('is_active', true)->count();

        return view('locations.index', compact('locations', 'totalCount', 'activeCount'));
    }

    /**
     * Store new locations (bulk or single)
     */
    public function store(Request $request)
    {
        // Check if this is bulk location creation from wizard
        if ($request->has('warehouse_count') && $request->has('locations') && is_array($request->input('locations'))) {
            return $this->storeBulkLocations($request);
        }

        // Single location creation
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                function ($attribute, $value, $fail) {
                    if (!Store::isNameUnique($value)) {
                        $fail('The location name "' . trim($value) . '" already exists. Please enter a different location.');
                    }
                },
            ],
            'code' => 'nullable|string|max:255|unique:stores,code',
            'company_id' => 'required|integer|exists:companies,id,is_active,1',
            'location' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
        ]);

        // Generate code if not provided
        if (empty($validated['code'])) {
            $validated['code'] = $this->generateLocationCode($validated['name']);
        }

        // Start transaction for single location creation.
        try {
            DB::beginTransaction();

            Store::create([
                'name' => trim($validated['name']),
                'code' => trim($validated['code']),
                'company_id' => $validated['company_id'],
                'location' => isset($validated['location']) ? trim($validated['location']) : null,
                'description' => isset($validated['description']) ? trim($validated['description']) : null,
                'is_active' => true,
            ]);

            DB::commit();
        } catch (Throwable $exception) {
            DB::rollBack();
            throw $exception;
        }

        return redirect()
            ->route('locations.index')
            ->with('success', 'Location added successfully.');
    }

    /**
     * Store multiple locations from wizard
     */
    protected function storeBulkLocations(Request $request)
    {
        $validated = $request->validate([
            'company_id' => 'required|integer|exists:companies,id,is_active,1',
            'warehouse_count' => 'required|integer|min:1|max:50',
            'locations' => 'required|array|min:1|max:50',
            'locations.*.name' => 'required|string|max:255',
        ]);

        // Validate uniqueness within the submission
        $names = array_map(function ($loc) {
            return strtolower(trim($loc['name']));
        }, $validated['locations']);

        // Check for duplicates in submission
        $duplicatesInSubmission = array_filter(array_count_values($names), function ($count) {
            return $count > 1;
        });

        if (!empty($duplicatesInSubmission)) {
            $duplicateNames = array_keys($duplicatesInSubmission);
            return back()->withErrors([
                'locations' => 'Duplicate location names found: ' . implode(', ', $duplicateNames) . '. Each location name must be unique.',
            ])->withInput();
        }

        // Validate uniqueness against existing locations
        foreach ($validated['locations'] as $index => $locationData) {
            $name = trim($locationData['name']);
            if (!Store::isNameUnique($name)) {
                return back()->withErrors([
                    "locations.{$index}.name" => "Location '{$name}' already exists. Please enter a different location.",
                ])->withInput();
            }
        }

        // Generate unique codes and insert locations
        $locationsToInsert = [];
        $existingCodes = Store::pluck('code')->toArray();

        foreach ($validated['locations'] as $index => $locationData) {
            $name = trim($locationData['name']);
            $code = $this->generateUniqueCode($name, $existingCodes, $locationsToInsert);

            $locationsToInsert[] = [
                'name' => $name,
                'code' => $code,
                'company_id' => $validated['company_id'],
                'location' => null,
                'description' => null,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // Start transaction for bulk location creation.
        try {
            DB::beginTransaction();

            DB::table('stores')->insert($locationsToInsert);

            DB::commit();
        } catch (Throwable $exception) {
            DB::rollBack();
            throw $exception;
        }

        $count = count($locationsToInsert);
        return redirect()
            ->route('locations.index')
            ->with('success', "{$count} location(s) created successfully.");
    }

    /**
     * Show edit form for a location
     */
    public function edit($id)
    {
        $location = Store::findOrFail($id);
        $companies = Company::where('is_active', true)->orderBy('name')->get();

        return view('locations.edit', compact('location', 'companies'));
    }

    /**
     * Update a location
     */
    public function update(Request $request, $id)
    {
        $location = Store::findOrFail($id);

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                function ($attribute, $value, $fail) use ($location) {
                    if (!Store::isNameUnique($value, $location->id)) {
                        $fail('The location name "' . trim($value) . '" already exists. Please enter a different location.');
                    }
                },
            ],
            'code' => [
                'required',
                'string',
                'max:255',
                Rule::unique('stores', 'code')->ignore($location->id),
            ],
            'company_id' => 'required|integer|exists:companies,id,is_active,1',
            'location' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
        ]);

        // Start transaction for location update.
        try {
            DB::beginTransaction();

            $location->update([
                'name' => trim($validated['name']),
                'code' => trim($validated['code']),
                'company_id' => $validated['company_id'],
                'location' => isset($validated['location']) ? trim($validated['location']) : null,
                'description' => isset($validated['description']) ? trim($validated['description']) : null,
            ]);

            DB::commit();
        } catch (Throwable $exception) {
            DB::rollBack();
            throw $exception;
        }

        return redirect()
            ->route('locations.index')
            ->with('success', 'Location updated successfully.');
    }

    /**
     * Toggle location status (activate/deactivate)
     */
    public function toggleStatus($id)
    {
        $location = Store::findOrFail($id);

        // Check if location can be deactivated
        if ($location->is_active && !$location->canBeDeactivated()) {
            return back()->with('error', 'Cannot deactivate location with existing stock. Please transfer or remove stock first.');
        }

        // Start transaction for location status update.
        try {
            DB::beginTransaction();

            $location->toggleStatus();

            DB::commit();
        } catch (Throwable $exception) {
            DB::rollBack();
            throw $exception;
        }

        $message = $location->is_active 
            ? 'Location activated successfully.' 
            : 'Location deactivated successfully.';

        return redirect()
            ->route('locations.index')
            ->with('success', $message);
    }

    /**
     * Show halls for a specific location
     */
    public function halls(Request $request, $locationId)
    {
        $location = Store::findOrFail($locationId);
        $listingStatus = $request->query('status') === 'inactive' ? 'inactive' : 'active';
        
        $halls = Hall::with('store')
            ->withCount('racks')
            ->where('store_id', $locationId)
            ->where('is_active', $listingStatus === 'active')
            ->orderBy('name')
            ->get();

        return view('locations.halls', compact('location', 'halls', 'listingStatus'));
    }

    /**
     * Generate location code from name
     */
    protected function generateLocationCode($name)
    {
        $existingCodes = Store::pluck('code')->toArray();
        return $this->generateUniqueCode($name, $existingCodes, []);
    }

    /**
     * Generate unique code for a location
     */
    protected function generateUniqueCode($name, $existingCodes, $pendingLocations)
    {
        $baseCode = 'WH-' . strtoupper(substr(preg_replace('/[^A-Z0-9]/i', '', $name), 0, 3));
        $code = $baseCode;
        $suffix = 1;

        $allCodes = array_merge($existingCodes, array_column($pendingLocations, 'code'));

        while (in_array($code, $allCodes)) {
            $code = $baseCode . '-' . str_pad($suffix, 3, '0', STR_PAD_LEFT);
            $suffix++;
        }

        return $code;
    }
}