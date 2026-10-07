@extends('layouts.app')

@section('title', 'Hall ' . $hall->name)
@section('topbar-title', 'Hall ' . $hall->name)

@section('content')
    <div class="page-heading">
        <div><span class="section-kicker">HALL MANAGEMENT</span><h1>Hall {{ $hall->name }}</h1><p>Manage racks and shells stored in this Hall.</p></div>
        <a class="button button-primary" href="{{ route('racks.create', $hall->id) }}"><span class="button-plus">+</span> Add Rack</a>
    </div>
    <div class="page-heading">
        <div>@include('components.status-badge', ['status' => $hall->is_active ? 'Active' : 'Inactive'])</div>
        <a class="button button-light" href="{{ route('halls.index') }}">Back to halls</a>
    </div>
    <section class="panel listing-panel" data-enhanced-table data-page-size="10">
        <div class="table-toolbar">
            <label class="search-field"><span class="search-glyph" aria-hidden="true">⌕</span><input type="search" placeholder="Search racks..." aria-label="Search racks" data-table-search></label>
            <div class="status-tabs" aria-label="Rack status">
                <a class="status-tab {{ $listingStatus === 'active' ? 'is-selected' : '' }}" href="{{ route('halls.show', ['hall' => $hall->id, 'status' => 'active']) }}" {{ $listingStatus === 'active' ? 'aria-current=page' : '' }}>Active</a>
                <a class="status-tab {{ $listingStatus === 'inactive' ? 'is-selected' : '' }}" href="{{ route('halls.show', ['hall' => $hall->id, 'status' => 'inactive']) }}" {{ $listingStatus === 'inactive' ? 'aria-current=page' : '' }}>Inactive</a>
            </div>
        </div>
        <div class="table-wrap">
            <table class="data-table listing-table">
                <thead><tr><th>#</th><th>RACK</th><th>NUMBER OF SHELLS</th><th>STATUS</th><th>ACTIONS</th></tr></thead>
                <tbody>
                @forelse ($racks as $rack)
                    <tr data-table-row>
                        <td class="muted-cell">{{ $loop->iteration }}</td>
                        <td><strong class="table-primary-text">{{ $rack->name }}</strong></td>
                        <td>{{ $rack->shelves_count }}</td>
                        <td>@include('components.status-badge', ['status' => $rack->is_active ? 'Active' : 'Inactive'])</td>
                        <td class="action-cell">
                            <a class="button button-small button-light" href="{{ route('racks.show', $rack->id) }}">Manage Shells</a>
                            <a class="button button-small button-light" href="{{ route('racks.edit', $rack->id) }}">Edit</a>
                            <form class="status-action-form" action="{{ route('racks.status', $rack->id) }}" method="POST">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="is_active" value="{{ $rack->is_active ? 0 : 1 }}">
                                <input type="hidden" name="listing_status" value="{{ $listingStatus }}">
                                <button class="button button-small {{ $rack->is_active ? 'button-deactivate' : 'button-activate' }}" type="submit">{{ $rack->is_active ? 'Inactive' : 'Active' }}</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5">@include('components.empty-state', ['icon' => 'icon-layers', 'title' => 'No ' . $listingStatus . ' racks', 'message' => 'Add a Rack to organize the Hall.'])</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="filter-empty" data-filter-empty hidden>No {{ $listingStatus }} racks match your search.</div>
        <div class="table-footer"><span class="table-summary" data-table-summary></span><div class="pagination" data-table-pagination></div></div>
    </section>
@endsection
