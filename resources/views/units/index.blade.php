@extends('layouts.app')

@section('title', 'Units')
@section('topbar-title', 'Units')

@section('content')
    <div class="page-heading">
        <div><span class="section-kicker">CATALOGUE</span><h1>Units</h1><p>Manage inventory measurement units.</p></div>
        <a class="button button-primary" href="{{ route('units.create') }}"><span class="button-plus">+</span> Add Unit</a>
    </div>

    <section class="panel listing-panel" data-enhanced-table data-page-size="10">
        <div class="table-toolbar">
            <label class="search-field"><span class="search-glyph" aria-hidden="true">⌕</span><input type="search" placeholder="Search units..." aria-label="Search units" data-table-search></label>
            <div class="status-tabs" aria-label="Unit status">
                <a class="status-tab {{ $listingStatus === 'active' ? 'is-selected' : '' }}" href="{{ route('units.index', ['status' => 'active']) }}" {{ $listingStatus === 'active' ? 'aria-current=page' : '' }}>Active</a>
                <a class="status-tab {{ $listingStatus === 'inactive' ? 'is-selected' : '' }}" href="{{ route('units.index', ['status' => 'inactive']) }}" {{ $listingStatus === 'inactive' ? 'aria-current=page' : '' }}>Inactive</a>
            </div>
        </div>
        <div class="table-wrap">
            <table class="data-table listing-table">
                <thead><tr><th>#</th><th>UNIT NAME</th><th>SHORT NAME</th><th>STATUS</th><th>ACTIONS</th></tr></thead>
                <tbody>
                @forelse ($units as $unit)
                    <tr data-table-row>
                        <td class="muted-cell">{{ $loop->iteration }}</td>
                        <td><strong class="table-primary-text">{{ $unit->name }}</strong></td>
                        <td><span class="unit-code">{{ $unit->short_name }}</span></td>
                        <td>@include('components.status-badge', ['status' => $unit->is_active ? 'Active' : 'Inactive'])</td>
                        <td class="action-cell">
                            <a class="button button-small button-light" href="{{ route('units.edit', $unit->id) }}">Edit</a>
                            <form class="status-action-form" action="{{ route('units.status', $unit->id) }}" method="POST">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="is_active" value="{{ $unit->is_active ? 0 : 1 }}">
                                <input type="hidden" name="listing_status" value="{{ $listingStatus }}">
                                <button class="button button-small {{ $unit->is_active ? 'button-deactivate' : 'button-activate' }}" type="submit">{{ $unit->is_active ? 'Inactive' : 'Active' }}</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5">@include('components.empty-state', ['icon' => 'icon-ruler', 'title' => 'No units yet', 'message' => 'Add a measurement unit to use with your products.'])</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="filter-empty" data-filter-empty hidden>No {{ $listingStatus }} units match your search.</div>
        <div class="table-footer"><span class="table-summary" data-table-summary></span><div class="pagination" data-table-pagination></div></div>
    </section>
@endsection
