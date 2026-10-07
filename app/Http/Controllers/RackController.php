<?php

namespace App\Http\Controllers;

use App\Hall;
use App\Rack;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RackController extends Controller
{
    public function create($hallId)
    {
        $hall = Hall::findOrFail($hallId);

        return view('racks.create', compact('hall'));
    }

    public function store(Request $request, $hallId)
    {
        $hall = Hall::findOrFail($hallId);
        $validated = $request->validate([
            'name' => [
                'required',
                'alpha_num',
                'max:50',
                Rule::unique('racks', 'name')->where(function ($query) use ($hall) {
                    return $query->where('hall_id', $hall->id);
                }),
            ],
        ]);

        $hall->racks()->create([
            'name' => $validated['name'],
            'is_active' => true,
        ]);

        return redirect()->route('halls.show', $hall->id)
            ->with('success', 'Rack created successfully.');
    }

    public function show(Request $request, $id)
    {
        $rack = Rack::with('hall')->findOrFail($id);
        $listingStatus = $request->query('status') === 'inactive' ? 'inactive' : 'active';
        $shelves = $rack->shelves()
            ->where('is_active', $listingStatus === 'active')
            ->orderBy('name')
            ->get();

        return view('racks.show', compact('rack', 'shelves', 'listingStatus'));
    }

    public function edit($id)
    {
        $rack = Rack::with('hall')->findOrFail($id);

        return view('racks.edit', compact('rack'));
    }

    public function update(Request $request, $id)
    {
        $rack = Rack::findOrFail($id);
        $validated = $request->validate([
            'name' => [
                'required',
                'alpha_num',
                'max:50',
                Rule::unique('racks', 'name')
                    ->where(function ($query) use ($rack) {
                        return $query->where('hall_id', $rack->hall_id);
                    })
                    ->ignore($rack->id),
            ],
        ]);

        $rack->update(['name' => $validated['name']]);

        return redirect()->route('halls.show', $rack->hall_id)
            ->with('success', 'Rack updated successfully.');
    }

    public function status(Request $request, $id)
    {
        $validated = $request->validate([
            'is_active' => 'required|boolean',
            'listing_status' => 'required|in:active,inactive',
        ]);

        $rack = Rack::findOrFail($id);
        $rack->is_active = $validated['is_active'];
        $rack->save();

        return redirect()->route('halls.show', [
            'hall' => $rack->hall_id,
            'status' => $validated['listing_status'],
        ])->with('success', $rack->is_active
            ? 'Rack activated successfully.'
            : 'Rack deactivated successfully. The record is retained.');
    }

    public function shelvesOptions($id)
    {
        $rack = Rack::findOrFail($id);

        return response()->json($rack->shelves()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']));
    }

    public function destroy($id)
    {
        $rack = Rack::findOrFail($id);
        $rack->is_active = false;
        $rack->save();

        return redirect()->route('halls.show', $rack->hall_id)
            ->with('success', 'Rack deactivated successfully. The record is retained.');
    }
}
