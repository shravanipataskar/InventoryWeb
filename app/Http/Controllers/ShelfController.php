<?php

namespace App\Http\Controllers;

use App\Rack;
use App\Shelf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Throwable;

class ShelfController extends Controller
{
    public function create($rackId)
    {
        $rack = Rack::with('hall')->findOrFail($rackId);

        return view('shelves.create', compact('rack'));
    }

    public function store(Request $request, $rackId)
    {
        $rack = Rack::findOrFail($rackId);
        $validated = $request->validate([
            'name' => [
                'required',
                'alpha_num',
                'max:50',
                Rule::unique('shelves', 'name')->where(function ($query) use ($rack) {
                    return $query->where('rack_id', $rack->id);
                }),
            ],
        ]);

        // Start transaction for shelf creation
        try {
            DB::beginTransaction();

            $rack->shelves()->create([
                'name' => $validated['name'],
                'is_active' => true,
            ]);

            DB::commit();
        } catch (Throwable $exception) {
            DB::rollBack();
            throw $exception;
        }

        return redirect()->route('racks.show', $rack->id)
            ->with('success', 'Shell created successfully.');
    }

    public function edit($id)
    {
        $shelf = Shelf::with('rack.hall')->findOrFail($id);

        return view('shelves.edit', compact('shelf'));
    }

    public function update(Request $request, $id)
    {
        $shelf = Shelf::findOrFail($id);
        $validated = $request->validate([
            'name' => [
                'required',
                'alpha_num',
                'max:50',
                Rule::unique('shelves', 'name')
                    ->where(function ($query) use ($shelf) {
                        return $query->where('rack_id', $shelf->rack_id);
                    })
                    ->ignore($shelf->id),
            ],
        ]);

        // Start transaction for shelf update
        try {
            DB::beginTransaction();

            $shelf->update(['name' => $validated['name']]);

            DB::commit();
        } catch (Throwable $exception) {
            DB::rollBack();
            throw $exception;
        }

        return redirect()->route('racks.show', $shelf->rack_id)
            ->with('success', 'Shell updated successfully.');
    }

    public function status(Request $request, $id)
    {
        $validated = $request->validate([
            'is_active' => 'required|boolean',
            'listing_status' => 'required|in:active,inactive',
        ]);

        $shelf = Shelf::findOrFail($id);

        // Start transaction for updating shelf status
        try {
            DB::beginTransaction();

            $shelf->is_active = $validated['is_active'];
            $shelf->save();

            DB::commit();
        } catch (Throwable $exception) {
            DB::rollBack();
            throw $exception;
        }

        return redirect()->route('racks.show', [
            'rack' => $shelf->rack_id,
            'status' => $validated['listing_status'],
        ])->with('success', $shelf->is_active
            ? 'Shell activated successfully.'
            : 'Shell deactivated successfully. The record is retained.');
    }

    public function destroy($id)
    {
        $shelf = Shelf::findOrFail($id);

        // Start transaction for shelf deactivation
        try {
            DB::beginTransaction();

            $shelf->is_active = false;
            $shelf->save();

            DB::commit();
        } catch (Throwable $exception) {
            DB::rollBack();
            throw $exception;
        }

        return redirect()->route('racks.show', $shelf->rack_id)
            ->with('success', 'Shell deactivated successfully. The record is retained.');
    }
}