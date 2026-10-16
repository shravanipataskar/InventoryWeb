@extends('layouts.app')

@section('title', 'Rack Management - Hall ' . $hall->name)
@section('topbar-title', 'Rack Management')

@section('content')
    <div class="breadcrumb-nav">
        <a href="{{ route('locations.index') }}">Master Data</a>
        <span class="breadcrumb-separator">/</span>
        <a href="{{ route('locations.index') }}">Locations</a>
        <span class="breadcrumb-separator">/</span>
        <a href="{{ route('locations.halls', $hall->store_id) }}">{{ $hall->store->name }}</a>
        <span class="breadcrumb-separator">/</span>
        <a href="{{ route('locations.halls', $hall->store_id) }}">Halls</a>
        <span class="breadcrumb-separator">/</span>
        <span class="breadcrumb-current">{{ $hall->name }}</span>
        <span class="breadcrumb-separator">/</span>
        <span class="breadcrumb-current">Racks</span>
    </div>

    <div class="location-hierarchy-card">
        <div class="hierarchy-item">
            <span class="hierarchy-label">Location</span>
            <span class="hierarchy-value">{{ $hall->store->name }}</span>
        </div>
        <div class="hierarchy-arrow">→</div>
        <div class="hierarchy-item">
            <span class="hierarchy-label">Hall</span>
            <span class="hierarchy-value">{{ $hall->name }}</span>
        </div>
    </div>

    <div class="page-heading">
        <div><span class="section-kicker">LOCATION: {{ strtoupper($hall->store->name) }} / HALL: {{ strtoupper($hall->name) }}</span><h1>Rack Management</h1><p>Manage racks and shelves stored in Hall {{ $hall->name }}.</p></div>
        <a class="button button-primary" href="{{ route('racks.create', $hall->id) }}"><span class="button-plus">+</span> Add Rack</a>
    </div>
    <div class="page-heading">
        <div>@include('components.status-badge', ['status' => $hall->is_active ? 'Active' : 'Inactive'])</div>
        <a class="button button-light" href="{{ route('locations.halls', $hall->store_id) }}">Back to Halls</a>
    </div>

    <style>
        .breadcrumb-nav {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 12px 0;
            font-size: 14px;
            color: #64748b;
            flex-wrap: wrap;
        }

        .breadcrumb-nav a {
            color: #2563eb;
            text-decoration: none;
            transition: color 0.15s;
        }

        .breadcrumb-nav a:hover {
            color: #1d4ed8;
            text-decoration: underline;
        }

        .breadcrumb-separator {
            color: #cbd5e1;
        }

        .breadcrumb-current {
            color: #0f172a;
            font-weight: 600;
        }

        .location-hierarchy-card {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 16px 20px;
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            border-radius: 8px;
            border: 1px solid #fbbf24;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .hierarchy-item {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .hierarchy-label {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            color: #78350f;
            letter-spacing: 0.5px;
        }

        .hierarchy-value {
            font-size: 16px;
            font-weight: 700;
            color: #0f172a;
        }

        .hierarchy-arrow {
            font-size: 20px;
            color: #78350f;
            font-weight: 700;
        }
    </style>
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
