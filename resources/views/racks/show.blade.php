@extends('layouts.app')

@section('title', 'Rack ' . $rack->name)
@section('topbar-title', 'Rack ' . $rack->name)

@section('content')
    <div class="page-heading">
        <div><span class="section-kicker">HALL {{ $rack->hall->name }} / RACK {{ $rack->name }}</span><h1>Shells</h1><p>Manage Shells stored in this Rack.</p></div>
        <a class="button button-primary" href="{{ route('shelves.create', $rack->id) }}"><span class="button-plus">+</span> Add Shell</a>
    </div>
    <div class="page-heading">
        <div><a class="button button-light" href="{{ route('halls.show', $rack->hall_id) }}">Back to Hall {{ $rack->hall->name }}</a></div>
        <a class="button button-light" href="{{ route('racks.edit', $rack->id) }}">Edit Rack</a>
    </div>
    <section class="panel listing-panel" data-enhanced-table data-page-size="10">
        <div class="table-toolbar">
            <label class="search-field"><span class="search-glyph" aria-hidden="true">⌕</span><input type="search" placeholder="Search shells..." aria-label="Search shells" data-table-search></label>
            <div class="status-tabs" aria-label="Shell status">
                <a class="status-tab {{ $listingStatus === 'active' ? 'is-selected' : '' }}" href="{{ route('racks.show', ['rack' => $rack->id, 'status' => 'active']) }}" {{ $listingStatus === 'active' ? 'aria-current=page' : '' }}>Active</a>
                <a class="status-tab {{ $listingStatus === 'inactive' ? 'is-selected' : '' }}" href="{{ route('racks.show', ['rack' => $rack->id, 'status' => 'inactive']) }}" {{ $listingStatus === 'inactive' ? 'aria-current=page' : '' }}>Inactive</a>
            </div>
        </div>
        <div class="table-wrap">
            <table class="data-table listing-table">
                <thead><tr><th>#</th><th>SHELL</th><th>STATUS</th><th>ACTIONS</th></tr></thead>
                <tbody>
                @forelse ($shelves as $shelf)
                    <tr data-table-row>
                        <td class="muted-cell">{{ $loop->iteration }}</td>
                        <td><strong class="table-primary-text">{{ $shelf->name }}</strong></td>
                        <td>@include('components.status-badge', ['status' => $shelf->is_active ? 'Active' : 'Inactive'])</td>
                        <td class="action-cell">
                            <a class="button button-small button-light" href="{{ route('shelves.edit', $shelf->id) }}">Edit</a>
                            <form class="status-action-form" action="{{ route('shelves.status', $shelf->id) }}" method="POST">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="is_active" value="{{ $shelf->is_active ? 0 : 1 }}">
                                <input type="hidden" name="listing_status" value="{{ $listingStatus }}">
                                <button class="button button-small {{ $shelf->is_active ? 'button-deactivate' : 'button-activate' }}" type="submit">{{ $shelf->is_active ? 'Inactive' : 'Active' }}</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4">@include('components.empty-state', ['icon' => 'icon-layers', 'title' => 'No ' . $listingStatus . ' shells', 'message' => 'Add a Shell to organize this Rack.'])</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="filter-empty" data-filter-empty hidden>No {{ $listingStatus }} shells match your search.</div>
        <div class="table-footer"><span class="table-summary" data-table-summary></span><div class="pagination" data-table-pagination></div></div>
    </section>
@endsection
