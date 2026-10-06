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
            <select class="filter-select" aria-label="Filter by status" data-filter-key="status"><option value="">All statuses</option><option value="active">Active</option><option value="inactive">Inactive</option></select>
            <button class="button button-light" type="button" data-filter-reset>Reset</button>
        </div>
        <div class="table-wrap">
            <table class="data-table listing-table">
                <thead><tr><th>#</th><th>UNIT NAME</th><th>SHORT NAME</th><th>STATUS</th><th>ACTIONS</th></tr></thead>
                <tbody>
                @forelse ($units as $unit)
                    <tr data-table-row data-status="{{ $unit->is_active ? 'active' : 'inactive' }}">
                        <td class="muted-cell">{{ $loop->iteration }}</td>
                        <td><strong class="table-primary-text">{{ $unit->name }}</strong></td>
                        <td><span class="unit-code">{{ $unit->short_name }}</span></td>
                        <td>@include('components.status-badge', ['status' => $unit->is_active ? 'Active' : 'Inactive'])</td>
                        <td class="action-cell">
                            <a class="button button-small button-light" href="{{ route('units.edit', $unit->id) }}">Edit</a>
                            <form action="{{ route('units.destroy', $unit->id) }}" method="POST" onsubmit="return confirm('Delete this unit? This action cannot be undone.');">
                                @csrf
                                @method('DELETE')
                                <button class="button button-small button-danger" type="submit">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5">@include('components.empty-state', ['icon' => 'icon-ruler', 'title' => 'No units yet', 'message' => 'Add a measurement unit to use with your products.'])</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="filter-empty" data-filter-empty hidden>No units match your search or filters.</div>
        <div class="table-footer"><span class="table-summary" data-table-summary></span><div class="pagination" data-table-pagination></div></div>
    </section>
@endsection
