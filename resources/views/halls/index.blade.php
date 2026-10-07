@extends('layouts.app')

@section('title', 'Halls')
@section('topbar-title', 'Hall management')

@section('content')
    <div class="page-heading">
        <div><span class="section-kicker">CATALOGUE</span><h1>Hall Management</h1><p>Manage halls and their storage locations.</p></div>
        <a class="button button-primary" href="{{ route('halls.create') }}"><span class="button-plus">+</span> Add Hall</a>
    </div>

    <section class="panel listing-panel" data-enhanced-table data-page-size="10">
        <div class="table-toolbar">
            <label class="search-field"><span class="search-glyph" aria-hidden="true">⌕</span><input type="search" placeholder="Search halls..." aria-label="Search halls" data-table-search></label>
            <div class="status-tabs" aria-label="Hall status">
                <a class="status-tab {{ $listingStatus === 'active' ? 'is-selected' : '' }}" href="{{ route('halls.index', ['status' => 'active']) }}" {{ $listingStatus === 'active' ? 'aria-current=page' : '' }}>Active</a>
                <a class="status-tab {{ $listingStatus === 'inactive' ? 'is-selected' : '' }}" href="{{ route('halls.index', ['status' => 'inactive']) }}" {{ $listingStatus === 'inactive' ? 'aria-current=page' : '' }}>Inactive</a>
            </div>
        </div>
        <div class="table-wrap">
            <table class="data-table listing-table">
                <thead><tr><th>#</th><th>HALL</th><th>NUMBER OF RACKS</th><th>STATUS</th><th>ACTIONS</th></tr></thead>
                <tbody>
                @forelse ($halls as $hall)
                    <tr data-table-row>
                        <td class="muted-cell">{{ $loop->iteration }}</td>
                        <td><strong class="table-primary-text">{{ $hall->name }}</strong></td>
                        <td>{{ $hall->racks_count }}</td>
                        <td>@include('components.status-badge', ['status' => $hall->is_active ? 'Active' : 'Inactive'])</td>
                        <td class="action-cell">
                            <a class="button button-small button-light" href="{{ route('halls.show', $hall->id) }}">Manage Racks</a>
                            <a class="button button-small button-light" href="{{ route('halls.edit', $hall->id) }}">Edit</a>
                            <form class="status-action-form" action="{{ route('halls.status', $hall->id) }}" method="POST">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="is_active" value="{{ $hall->is_active ? 0 : 1 }}">
                                <input type="hidden" name="listing_status" value="{{ $listingStatus }}">
                                <button class="button button-small {{ $hall->is_active ? 'button-deactivate' : 'button-activate' }}" type="submit">{{ $hall->is_active ? 'Inactive' : 'Active' }}</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5">@include('components.empty-state', ['icon' => 'icon-layers', 'title' => 'No ' . $listingStatus . ' halls', 'message' => 'Add a Hall to start organizing storage locations.'])</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="filter-empty" data-filter-empty hidden>No {{ $listingStatus }} halls match your search.</div>
        <div class="table-footer"><span class="table-summary" data-table-summary></span><div class="pagination" data-table-pagination></div></div>
    </section>
@endsection
