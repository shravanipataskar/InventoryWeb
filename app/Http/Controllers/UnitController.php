<?php

namespace App\Http\Controllers;

use App\Unit;
use Illuminate\Http\Request;

class UnitController extends Controller
{
    public function index(Request $request)
    {
        $listingStatus = $request->query('status') === 'inactive' ? 'inactive' : 'active';
        $units = Unit::where('is_active', $listingStatus === 'active')
            ->orderBy('id', 'desc')
            ->get();

        return view('units.index', compact('units', 'listingStatus'));
    }

    public function create()
    {
        return view('units.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'short_name' => 'required|string|max:20',
        ]);

        Unit::create([
            'name' => $request->name,
            'short_name' => $request->short_name,
            'is_active' => 1,
        ]);

        return redirect()
            ->route('units.index')
            ->with('success', 'Unit created successfully.');
    }

    public function edit($id)
    {
        $unit = Unit::findOrFail($id);

        return view('units.edit', compact('unit'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'short_name' => 'required|string|max:20',
        ]);

        $unit = Unit::findOrFail($id);

        $unit->update([
            'name' => $request->name,
            'short_name' => $request->short_name,
        ]);

        return redirect()
            ->route('units.index')
            ->with('success', 'Unit updated successfully.');
    }

    public function status(Request $request, $id)
    {
        $validated = $request->validate([
            'is_active' => 'required|boolean',
            'listing_status' => 'required|in:active,inactive',
        ]);

        $unit = Unit::findOrFail($id);
        $unit->is_active = $validated['is_active'];
        $unit->save();

        return redirect()
            ->route('units.index', ['status' => $validated['listing_status']])
            ->with('success', $unit->is_active
                ? 'Unit activated successfully.'
                : 'Unit deactivated successfully. The record is retained.');
    }

    public function destroy($id)
    {
        $unit = Unit::findOrFail($id);
        $unit->is_active = false;
        $unit->save();

        return redirect()
            ->route('units.index')
            ->with('success', 'Unit deactivated successfully. The record is retained.');
    }
}