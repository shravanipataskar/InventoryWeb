<?php

namespace App\Http\Controllers;

use App\Hall;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HallController extends Controller
{
    public function index(Request $request)
    {
        $listingStatus = $request->query('status') === 'inactive' ? 'inactive' : 'active';
        $halls = Hall::withCount('racks')
            ->where('is_active', $listingStatus === 'active')
            ->orderBy('id', 'desc')
            ->get();

        return view('halls.index', compact('halls', 'listingStatus'));
    }

    public function create()
    {
        return view('halls.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|alpha_num|max:50|unique:halls,name',
        ]);

        Hall::create([
            'name' => $validated['name'],
            'is_active' => true,
        ]);

        return redirect()->route('halls.index')
            ->with('success', 'Hall created successfully.');
    }

    public function show(Request $request, $id)
    {
        $hall = Hall::findOrFail($id);
        $listingStatus = $request->query('status') === 'inactive' ? 'inactive' : 'active';
        $racks = $hall->racks()
            ->withCount('shelves')
            ->where('is_active', $listingStatus === 'active')
            ->orderBy('name')
            ->get();

        return view('halls.show', compact('hall', 'racks', 'listingStatus'));
    }

    public function edit($id)
    {
        $hall = Hall::findOrFail($id);

        return view('halls.edit', compact('hall'));
    }

    public function update(Request $request, $id)
    {
        $hall = Hall::findOrFail($id);
        $validated = $request->validate([
            'name' => [
                'required',
                'alpha_num',
                'max:50',
                Rule::unique('halls', 'name')->ignore($hall->id),
            ],
        ]);

        $hall->update(['name' => $validated['name']]);

        return redirect()->route('halls.index')
            ->with('success', 'Hall updated successfully.');
    }

    public function status(Request $request, $id)
    {
        $validated = $request->validate([
            'is_active' => 'required|boolean',
            'listing_status' => 'required|in:active,inactive',
        ]);

        $hall = Hall::findOrFail($id);
        $hall->is_active = $validated['is_active'];
        $hall->save();

        return redirect()->route('halls.index', ['status' => $validated['listing_status']])
            ->with('success', $hall->is_active
                ? 'Hall activated successfully.'
                : 'Hall deactivated successfully. The record is retained.');
    }

    public function racksOptions($id)
    {
        $hall = Hall::findOrFail($id);

        return response()->json($hall->racks()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']));
    }

    public function destroy($id)
    {
        $hall = Hall::findOrFail($id);
        $hall->is_active = false;
        $hall->save();

        return redirect()->route('halls.index')
            ->with('success', 'Hall deactivated successfully. The record is retained.');
    }
}
