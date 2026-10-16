<?php

namespace App\Http\Controllers;

use App\Hall;
use App\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Throwable;

class HallController extends Controller
{
    public function index(Request $request)
    {
        $listingStatus = $request->query('status') === 'inactive' ? 'inactive' : 'active';
        $storeFilter = $request->query('store_id');
        
        $query = Hall::with('store')->withCount('racks')
            ->where('is_active', $listingStatus === 'active');
        
        if ($storeFilter) {
            $query->where('store_id', $storeFilter);
        }
        
        $halls = $query->orderBy('id', 'desc')->get();
        $stores = Store::where('is_active', true)->orderBy('name')->get(['id', 'name']);
        try {
            $listingStatus = $request->query('status') === 'inactive' ? 'inactive' : 'active';

            $halls = Hall::withCount('racks')
                ->where('is_active', $listingStatus === 'active')
                ->orderBy('id', 'desc')
                ->get();

        return view('halls.index', compact('halls', 'listingStatus', 'stores', 'storeFilter'));
            return view('halls.index', compact('halls', 'listingStatus'));
        } catch (Throwable $exception) {
            Log::error('Failed to load halls.', [
                'operation' => 'hall.index',
                'exception' => get_class($exception),
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    public function create()
    {
        $stores = Store::where('is_active', true)->orderBy('name')->get(['id', 'name']);
        
        return view('halls.create', compact('stores'));
        try {
            return view('halls.create');
        } catch (Throwable $exception) {
            Log::error('Failed to load the hall creation page.', [
                'operation' => 'hall.create',
                'exception' => get_class($exception),
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'store_id' => 'required|integer|exists:stores,id,is_active,1',
            'name' => [
                'required',
                'alpha_num',
                'max:50',
                Rule::unique('halls')->where(function ($query) use ($request) {
                    return $query->where('store_id', $request->input('store_id'));
                }),
            ],
        ]);

        try {
            // This transaction ensures the hall record is created completely or the database changes are rolled back.
            DB::beginTransaction();

        Hall::create([
            'store_id' => $validated['store_id'],
            'name' => $validated['name'],
            'is_active' => true,
        ]);
            Hall::create([
                'name' => $validated['name'],
                'is_active' => true,
            ]);

            DB::commit();
        } catch (Throwable $exception) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            Log::error('Failed to create a hall.', [
                'operation' => 'hall.store',
                'exception' => get_class($exception),
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        // Redirect to location-scoped hall management
        return redirect()->route('locations.halls', $validated['store_id'])
            ->with('success', 'Hall created successfully for the selected location.');
    }

    public function show(Request $request, $id)
    {
        $hall = Hall::with('store')->findOrFail($id);
        $listingStatus = $request->query('status') === 'inactive' ? 'inactive' : 'active';
        $racks = $hall->racks()
            ->withCount('shelves')
            ->where('is_active', $listingStatus === 'active')
            ->orderBy('name')
            ->get();
        try {
            $hall = Hall::findOrFail($id);

            $listingStatus = $request->query('status') === 'inactive' ? 'inactive' : 'active';

            $racks = $hall->racks()
                ->withCount('shelves')
                ->where('is_active', $listingStatus === 'active')
                ->orderBy('name')
                ->get();

            return view('halls.show', compact('hall', 'racks', 'listingStatus'));
        } catch (Throwable $exception) {
            Log::error('Failed to load hall details.', [
                'operation' => 'hall.show',
                'hall_id' => $id,
                'exception' => get_class($exception),
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    public function edit($id)
    {
        $hall = Hall::with('store')->findOrFail($id);
        $stores = Store::where('is_active', true)->orderBy('name')->get(['id', 'name']);
        try {
            $hall = Hall::findOrFail($id);

        return view('halls.edit', compact('hall', 'stores'));
            return view('halls.edit', compact('hall'));
        } catch (Throwable $exception) {
            Log::error('Failed to load the hall edit page.', [
                'operation' => 'hall.edit',
                'hall_id' => $id,
                'exception' => get_class($exception),
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    public function update(Request $request, $id)
    {
        $hall = Hall::findOrFail($id);

        $validated = $request->validate([
            'store_id' => 'required|integer|exists:stores,id,is_active,1',
            'name' => [
                'required',
                'alpha_num',
                'max:50',
                Rule::unique('halls', 'name')
                    ->where(function ($query) use ($request) {
                        return $query->where('store_id', $request->input('store_id'));
                    })
                    ->ignore($hall->id),
            ],
        ]);

        try {
            // This transaction ensures the hall name update is committed successfully or rolled back on failure.
            DB::beginTransaction();

        $hall->update([
            'store_id' => $validated['store_id'],
            'name' => $validated['name'],
        ]);
            $hall->update(['name' => $validated['name']]);

            DB::commit();
        } catch (Throwable $exception) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            Log::error('Failed to update a hall.', [
                'operation' => 'hall.update',
                'hall_id' => $id,
                'exception' => get_class($exception),
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        // Redirect to location-scoped hall management
        return redirect()->route('locations.halls', $validated['store_id'])
            ->with('success', 'Hall updated successfully.');
    }

    public function status(Request $request, $id)
    {
        $validated = $request->validate([
            'is_active' => 'required|boolean',
            'listing_status' => 'required|in:active,inactive',
        ]);

        $hall = Hall::findOrFail($id);

        try {
            // This transaction ensures the hall's active status is saved successfully or rolled back on failure.
            DB::beginTransaction();

            $hall->is_active = $validated['is_active'];
            $hall->save();

            DB::commit();
        } catch (Throwable $exception) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            Log::error('Failed to change hall status.', [
                'operation' => 'hall.status',
                'hall_id' => $id,
                'exception' => get_class($exception),
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        // Redirect to location-scoped hall management
        return redirect()->route('locations.halls', ['location' => $hall->store_id, 'status' => $validated['listing_status']])
            ->with('success', $hall->is_active
                ? 'Hall activated successfully.'
                : 'Hall deactivated successfully. The record is retained.');
    }

    /**
     * Get racks for a specific hall (API endpoint for cascading dropdowns)
     */
    public function racksOptions($id)
    {
        try {
            $hall = Hall::findOrFail($id);

            return response()->json($hall->racks()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']));
        } catch (Throwable $exception) {
            Log::error('Failed to load rack options for a hall.', [
                'operation' => 'hall.racks_options',
                'hall_id' => $id,
                'exception' => get_class($exception),
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    public function destroy($id)
    {
        $hall = Hall::findOrFail($id);

        try {
            // This transaction ensures hall deactivation is saved successfully or rolled back on failure.
            DB::beginTransaction();

            $hall->is_active = false;
            $hall->save();

            DB::commit();
        } catch (Throwable $exception) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            Log::error('Failed to deactivate a hall.', [
                'operation' => 'hall.destroy',
                'hall_id' => $id,
                'exception' => get_class($exception),
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        return redirect()->route('halls.index')
            ->with('success', 'Hall deactivated successfully. The record is retained.');
    }
}
