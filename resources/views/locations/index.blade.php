@extends('layouts.app')

@section('title', 'Location Management')
@section('topbar-title', 'Inventory Locations')

@section('content')
    <div class="page-heading workspace-page-heading">
        <div>
            <span class="section-kicker">MASTER DATA</span>
            <h1>Location Management</h1>
            <p>Manage warehouse locations and their storage hierarchy.</p>
        </div>
        @can('locations.create')
            <a class="button button-primary" href="{{ route('locations.setup') }}">
                <span class="button-plus">+</span> Add Location
            </a>
        @endcan
    </div>

    @if (session('success'))
        <div class="alert alert-success" role="alert">
            <svg class="alert-icon"><use href="#icon-check"></use></svg>
            {{ session('success') }}
        </div>
    @endif

    @if (session('error'))
        <div class="alert alert-error" role="alert">
            <svg class="alert-icon"><use href="#icon-x"></use></svg>
            {{ session('error') }}
        </div>
    @endif

    <div class="summary-cards">
        <article class="summary-card">
            <div class="summary-icon icon-blue">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <polyline points="9 22 9 12 15 12 15 22" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
            <div>
                <span>Total Locations</span>
                <strong>{{ number_format($totalCount) }}</strong>
            </div>
        </article>
        <article class="summary-card">
            <div class="summary-icon icon-green">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                    <polyline points="20 6 9 17 4 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
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
                           placeholder="Search location name, code..."
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
            <div class="locations-grid">
                @foreach ($locations as $location)
                    <article class="location-card {{ !$location->is_active ? 'location-inactive' : '' }}">
                        <div class="location-header">
                            <div>
                                <h3 class="location-name">{{ $location->name }}</h3>
                                <div class="location-meta">
                                    <span class="location-code">{{ $location->code }}</span>
                                    @if ($location->is_active)
                                        <span class="badge badge-success">Active</span>
                                    @else
                                        <span class="badge badge-inactive">Inactive</span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="location-details">
                            @if ($location->company)
                                <div class="detail-row">
                                    <svg class="detail-icon" width="16" height="16" viewBox="0 0 24 24" fill="none">
                                        <rect x="3" y="3" width="18" height="18" rx="2" stroke="currentColor" stroke-width="2"/>
                                        <line x1="3" y1="9" x2="21" y2="9" stroke="currentColor" stroke-width="2"/>
                                        <line x1="9" y1="21" x2="9" y2="9" stroke="currentColor" stroke-width="2"/>
                                    </svg>
                                    <span>{{ $location->company->name }}</span>
                                </div>
                            @endif

                            <div class="detail-row">
                                <svg class="detail-icon" width="16" height="16" viewBox="0 0 24 24" fill="none">
                                    <path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0z" stroke="currentColor" stroke-width="2"/>
                                    <circle cx="12" cy="10" r="3" stroke="currentColor" stroke-width="2"/>
                                </svg>
                                <span>{{ $location->location ?? 'No address specified' }}</span>
                            </div>

                            <div class="detail-row">
                                <svg class="detail-icon" width="16" height="16" viewBox="0 0 24 24" fill="none">
                                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2" stroke="currentColor" stroke-width="2"/>
                                    <line x1="16" y1="2" x2="16" y2="6" stroke="currentColor" stroke-width="2"/>
                                    <line x1="8" y1="2" x2="8" y2="6" stroke="currentColor" stroke-width="2"/>
                                    <line x1="3" y1="10" x2="21" y2="10" stroke="currentColor" stroke-width="2"/>
                                </svg>
                                <span>{{ $location->halls_count }} Hall(s)</span>
                            </div>
                        </div>

                        <div class="location-actions">
                            @can('locations.view')
                                <a href="{{ route('locations.halls', $location->id) }}" 
                                   class="button button-primary button-sm button-block">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                                        <rect x="3" y="3" width="7" height="7" stroke="currentColor" stroke-width="2"/>
                                        <rect x="14" y="3" width="7" height="7" stroke="currentColor" stroke-width="2"/>
                                        <rect x="3" y="14" width="7" height="7" stroke="currentColor" stroke-width="2"/>
                                        <rect x="14" y="14" width="7" height="7" stroke="currentColor" stroke-width="2"/>
                                    </svg>
                                    Manage Halls
                                </a>
                            @endcan

                            <div class="action-buttons-row">
                                @can('locations.edit')
                                    <a href="{{ route('locations.edit', $location->id) }}" 
                                       class="button button-light button-sm"
                                       title="Edit location">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" stroke="currentColor" stroke-width="2"/>
                                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" stroke="currentColor" stroke-width="2"/>
                                        </svg>
                                        Edit
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
                                                class="button button-sm {{ $location->is_active ? 'button-danger' : 'button-success' }}"
                                                title="{{ $location->is_active ? 'Deactivate' : 'Activate' }} location">
                                            @if ($location->is_active)
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                                                    <line x1="18" y1="6" x2="6" y2="18" stroke="currentColor" stroke-width="2"/>
                                                    <line x1="6" y1="6" x2="18" y2="18" stroke="currentColor" stroke-width="2"/>
                                                </svg>
                                                Deactivate
                                            @else
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                                                    <polyline points="20 6 9 17 4 12" stroke="currentColor" stroke-width="2"/>
                                                </svg>
                                                Activate
                                            @endif
                                        </button>
                                    </form>
                                @endcan
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>

            <div class="pagination-container">
                {{ $locations->links() }}
            </div>
        @else
            <div class="empty-state">
                <svg class="empty-icon" width="64" height="64" viewBox="0 0 24 24" fill="none">
                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" stroke="currentColor" stroke-width="2"/>
                    <polyline points="9 22 9 12 15 12 15 22" stroke="currentColor" stroke-width="2"/>
                </svg>
                <h3>No locations found</h3>
                <p>
                    @if (request()->has('search') || request()->has('status'))
                        No locations match your filter criteria. Try adjusting your filters.
                    @else
                        Get started by creating your first warehouse location.
                    @endif
                </p>
                @can('locations.create')
                    @if (!request()->has('search') && !request()->has('status'))
                        <a href="{{ route('locations.setup') }}" class="button button-primary">
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
            flex-shrink: 0;
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

        .locations-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 20px;
            padding: 24px;
        }

        .location-card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 20px;
            transition: all 0.2s;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .location-card:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }

        .location-inactive {
            opacity: 0.6;
            background: #f8f9fa;
        }

        .location-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .location-name {
            margin: 0 0 8px;
            font-size: 18px;
            font-weight: 700;
            color: #0f172a;
        }

        .location-meta {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .location-code {
            font-family: monospace;
            font-size: 13px;
            color: #64748b;
            background: #f1f5f9;
            padding: 2px 8px;
            border-radius: 4px;
        }

        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
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

        .location-details {
            display: flex;
            flex-direction: column;
            gap: 8px;
            padding: 12px 0;
            border-top: 1px solid #e2e8f0;
            border-bottom: 1px solid #e2e8f0;
        }

        .detail-row {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
            color: #475569;
        }

        .detail-icon {
            color: #94a3b8;
            flex-shrink: 0;
        }

        .location-actions {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .action-buttons-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
        }

        .button {
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.15s;
            border: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            text-decoration: none;
        }

        .button-sm {
            padding: 6px 12px;
            font-size: 13px;
        }

        .button-block {
            width: 100%;
        }

        .button-primary {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: white;
        }

        .button-primary:hover {
            background: linear-gradient(135deg, #1d4ed8, #1e40af);
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(37, 99, 235, 0.3);
        }

        .button-light {
            background: white;
            color: #475569;
            border: 1px solid #cbd5e1;
        }

        .button-light:hover {
            background: #f8f9fa;
            border-color: #94a3b8;
        }

        .button-danger {
            background: white;
            color: #dc2626;
            border: 1px solid #fca5a5;
        }

        .button-danger:hover {
            background: #fef2f2;
            border-color: #dc2626;
        }

        .button-success {
            background: white;
            color: #16a34a;
            border: 1px solid #86efac;
        }

        .button-success:hover {
            background: #ecfdf5;
            border-color: #16a34a;
        }

        .button-plus {
            font-size: 20px;
            line-height: 1;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }

        .empty-icon {
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

        .alert-icon {
            width: 20px;
            height: 20px;
            flex-shrink: 0;
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

        .sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border-width: 0;
        }

        .pagination-container {
            padding: 20px;
            border-top: 1px solid #e2e8f0;
        }
    </style>
@endsection
