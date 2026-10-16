@extends('layouts.app')

@section('title', 'Locations')
@section('topbar-title', 'Inventory locations')

@section('content')
    <div class="page-heading workspace-page-heading">
        <div>
            <span class="section-kicker">MASTER DATA</span>
            <h1>Inventory Locations</h1>
            <p>Manage warehouse and storage locations for your inventory system.</p>
        </div>
        @can('locations.create')
            <a class="button button-primary" href="{{ route('locations.create') }}">
                <span class="button-plus">+</span> Add Location
            </a>
        @endcan
    </div>

    @if (session('success'))
        <div class="alert alert-success" role="alert">
            {{ session('success') }}
        </div>
    @endif

    @if (session('error'))
        <div class="alert alert-error" role="alert">
            {{ session('error') }}
        </div>
    @endif

    <div class="summary-cards">
        <article class="summary-card">
            <div class="summary-icon icon-blue">
                <svg><use href="#icon-building"></use></svg>
            </div>
            <div>
                <span>Total Locations</span>
                <strong>{{ number_format($totalCount) }}</strong>
            </div>
        </article>
        <article class="summary-card">
            <div class="summary-icon icon-green">
                <svg><use href="#icon-check"></use></svg>
            </div>
            <div>
                <span>Active Locations</span>
                <strong>{{ number_format($activeCount) }}</strong>
            </div>
        </article>
    </div>

    <section class="panel">
        <form class="filters-toolbar" method="GET" action="{{ route('locations.index') }}">
            <div class="filter-group">
                <label class="search-field">
                    <span class="search-icon" aria-hidden="true">⌕</span>
                    <input type="search" 
                           name="search" 
                           value="{{ request('search') }}" 
                           placeholder="Search location name, code, or address..."
                           aria-label="Search locations">
                </label>

                <label class="select-field">
                    <span class="sr-only">Status</span>
                    <select name="status" aria-label="Filter by status">
                        <option value="">All Statuses</option>
                        <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active Only</option>
                        <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactive Only</option>
                    </select>
                </label>
            </div>

            <div class="filter-actions">
                <button type="submit" class="button button-light">Apply Filters</button>
                <a href="{{ route('locations.index') }}" class="button button-light">Reset</a>
            </div>
        </form>

        @if ($locations->count() > 0)
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Location Name</th>
                            <th>Code</th>
                            <th>Company / Brand</th>
                            <th>Address / Area</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th class="actions-column">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($locations as $location)
                            <tr>
                                <td>{{ ($locations->currentPage() - 1) * $locations->perPage() + $loop->iteration }}</td>
                                <td><strong>{{ $location->name }}</strong></td>
                                <td><code>{{ $location->code }}</code></td>
                                <td>{{ $location->company_name ?? 'N/A' }}</td>
                                <td>{{ $location->location ?? '—' }}</td>
                                <td>
                                    @if ($location->is_active)
                                        <span class="badge badge-success">Active</span>
                                    @else
                                        <span class="badge badge-inactive">Inactive</span>
                                    @endif
                                </td>
                                <td>{{ \Carbon\Carbon::parse($location->created_at)->format('d M Y') }}</td>
                                <td class="actions-cell">
                                    <div class="action-buttons">
                                        @can('locations.edit')
                                            <a href="{{ route('locations.edit', $location->id) }}" 
                                               class="action-btn action-btn-edit" 
                                               title="Edit location">
                                                <svg><use href="#icon-edit"></use></svg>
                                            </a>
                                        @endcan

                                        @can('locations.edit')
                                            <form action="{{ route('locations.toggleStatus', $location->id) }}" 
                                                  method="POST" 
                                                  style="display: inline;"
                                                  onsubmit="return confirm('Are you sure you want to {{ $location->is_active ? 'deactivate' : 'activate' }} this location?');">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" 
                                                        class="action-btn {{ $location->is_active ? 'action-btn-deactivate' : 'action-btn-activate' }}"
                                                        title="{{ $location->is_active ? 'Deactivate' : 'Activate' }} location">
                                                    @if ($location->is_active)
                                                        <svg><use href="#icon-x"></use></svg>
                                                    @else
                                                        <svg><use href="#icon-check"></use></svg>
                                                    @endif
                                                </button>
                                            </form>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="pagination-container">
                {{ $locations->links() }}
            </div>
        @else
            <div class="empty-state">
                <svg class="empty-icon"><use href="#icon-building"></use></svg>
                <h3>No locations found</h3>
                <p>
                    @if (request()->has('search') || request()->has('status'))
                        No locations match your filter criteria. Try adjusting your filters.
                    @else
                        Get started by creating your first inventory location.
                    @endif
                </p>
                @can('locations.create')
                    @if (!request()->has('search') && !request()->has('status'))
                        <a href="{{ route('locations.create') }}" class="button button-primary">
                            <span class="button-plus">+</span> Add Location
                        </a>
                    @endif
                @endcan
            </div>
        @endif
    </section>

    <style>
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 24px;
        }

        .summary-card {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 20px;
            background: white;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .summary-icon {
            width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
        }

        .summary-icon svg {
            width: 24px;
            height: 24px;
        }

        .icon-blue {
            background: #eff6ff;
            color: #2563eb;
        }

        .icon-green {
            background: #ecfdf5;
            color: #16a34a;
        }

        .summary-card > div {
            flex: 1;
        }

        .summary-card span {
            display: block;
            font-size: 13px;
            color: #64748b;
            margin-bottom: 4px;
        }

        .summary-card strong {
            display: block;
            font-size: 24px;
            font-weight: 700;
            color: #0f172a;
        }

        .filters-toolbar {
            display: flex;
            gap: 16px;
            padding: 20px;
            background: #f8f9fa;
            border-bottom: 1px solid #e2e8f0;
            flex-wrap: wrap;
        }

        .filter-group {
            display: flex;
            gap: 12px;
            flex: 1;
            min-width: 300px;
        }

        .filter-actions {
            display: flex;
            gap: 8px;
        }

        .search-field {
            position: relative;
            flex: 1;
        }

        .search-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 18px;
        }

        .search-field input {
            width: 100%;
            padding: 8px 12px 8px 36px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 14px;
        }

        .select-field select {
            padding: 8px 32px 8px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 14px;
            background: white;
            min-width: 150px;
        }

        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-success {
            background: #ecfdf5;
            color: #16a34a;
        }

        .badge-inactive {
            background: #f1f5f9;
            color: #64748b;
        }

        .actions-column {
            width: 120px;
            text-align: center;
        }

        .actions-cell {
            text-align: center;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
            justify-content: center;
        }

        .action-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            background: white;
            cursor: pointer;
            transition: all 0.15s;
        }

        .action-btn svg {
            width: 16px;
            height: 16px;
        }

        .action-btn-edit {
            color: #2563eb;
        }

        .action-btn-edit:hover {
            background: #eff6ff;
            border-color: #2563eb;
        }

        .action-btn-deactivate {
            color: #dc2626;
        }

        .action-btn-deactivate:hover {
            background: #fef2f2;
            border-color: #dc2626;
        }

        .action-btn-activate {
            color: #16a34a;
        }

        .action-btn-activate:hover {
            background: #ecfdf5;
            border-color: #16a34a;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }

        .empty-icon {
            width: 64px;
            height: 64px;
            color: #cbd5e1;
            margin-bottom: 16px;
        }

        .empty-state h3 {
            margin: 0 0 8px;
            font-size: 18px;
            color: #334155;
        }

        .empty-state p {
            color: #64748b;
            margin-bottom: 24px;
        }

        .alert {
            padding: 16px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .alert-success {
            background: #ecfdf5;
            border: 1px solid #16a34a;
            color: #166534;
        }

        .alert-error {
            background: #fef2f2;
            border: 1px solid #dc2626;
            color: #991b1b;
        }
    </style>
@endsection
