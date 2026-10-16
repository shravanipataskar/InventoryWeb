@extends('layouts.app')

@section('title', 'Hall Management')
@section('topbar-title', 'Hall Management')

@section('content')
    <div class="breadcrumb-nav">
        <a href="{{ route('locations.index') }}">Master Data</a>
        <span class="breadcrumb-separator">/</span>
        <a href="{{ route('locations.index') }}">Locations</a>
        <span class="breadcrumb-separator">/</span>
        <span class="breadcrumb-current">{{ $location->name }}</span>
        <span class="breadcrumb-separator">/</span>
        <span class="breadcrumb-current">Halls</span>
    </div>

    <div class="page-heading workspace-page-heading">
        <div>
            <span class="section-kicker">LOCATION: {{ strtoupper($location->name) }}</span>
            <h1>Hall Management</h1>
            <p>Manage halls and their storage locations for {{ $location->name }}.</p>
        </div>
        @can('locations.create')
            <a class="button button-primary" href="{{ route('halls.create', ['store_id' => $location->id]) }}">
                <span class="button-plus">+</span> Add Hall
            </a>
        @endcan
    </div>

    @if (session('success'))
        <div class="alert alert-success" role="alert">
            <svg class="alert-icon" width="20" height="20" viewBox="0 0 24 24" fill="none">
                <polyline points="20 6 9 17 4 12" stroke="currentColor" stroke-width="2"/>
            </svg>
            {{ session('success') }}
        </div>
    @endif

    @if (session('error'))
        <div class="alert alert-error" role="alert">
            <svg class="alert-icon" width="20" height="20" viewBox="0 0 24 24" fill="none">
                <line x1="18" y1="6" x2="6" y2="18" stroke="currentColor" stroke-width="2"/>
                <line x1="6" y1="6" x2="18" y2="18" stroke="currentColor" stroke-width="2"/>
            </svg>
            {{ session('error') }}
        </div>
    @endif

    <div class="location-info-card">
        <div class="info-item">
            <span class="info-label">Location Name</span>
            <span class="info-value">{{ $location->name }}</span>
        </div>
        <div class="info-item">
            <span class="info-label">Location Code</span>
            <span class="info-value">{{ $location->code }}</span>
        </div>
        @if ($location->company)
            <div class="info-item">
                <span class="info-label">Company</span>
                <span class="info-value">{{ $location->company->name }}</span>
            </div>
        @endif
        <div class="info-item">
            <span class="info-label">Total Halls</span>
            <span class="info-value">{{ $halls->count() }}</span>
        </div>
    </div>

    <section class="panel">
        <div class="panel-toolbar">
            <div class="status-tabs">
                <a href="{{ route('locations.halls', ['location' => $location->id, 'status' => 'active']) }}" 
                   class="status-tab {{ $listingStatus === 'active' ? 'active' : '' }}">
                    Active
                </a>
                <a href="{{ route('locations.halls', ['location' => $location->id, 'status' => 'inactive']) }}" 
                   class="status-tab {{ $listingStatus === 'inactive' ? 'active' : '' }}">
                    Inactive
                </a>
            </div>
        </div>

        @if ($halls->count() > 0)
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Hall Name</th>
                            <th>Number of Racks</th>
                            <th>Status</th>
                            <th class="actions-column">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($halls as $hall)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td><strong>{{ $hall->name }}</strong></td>
                                <td>{{ $hall->racks_count }}</td>
                                <td>
                                    @if ($hall->is_active)
                                        <span class="badge badge-success">Active</span>
                                    @else
                                        <span class="badge badge-inactive">Inactive</span>
                                    @endif
                                </td>
                                <td class="actions-cell">
                                    <div class="action-buttons">
                                        <a href="{{ route('halls.show', $hall->id) }}" 
                                           class="action-btn action-btn-view" 
                                           title="Manage racks">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                                                <rect x="3" y="3" width="7" height="7" stroke="currentColor" stroke-width="2"/>
                                                <rect x="14" y="3" width="7" height="7" stroke="currentColor" stroke-width="2"/>
                                                <rect x="3" y="14" width="7" height="7" stroke="currentColor" stroke-width="2"/>
                                                <rect x="14" y="14" width="7" height="7" stroke="currentColor" stroke-width="2"/>
                                            </svg>
                                        </a>

                                        @can('locations.edit')
                                            <a href="{{ route('halls.edit', $hall->id) }}" 
                                               class="action-btn action-btn-edit" 
                                               title="Edit hall">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" stroke="currentColor" stroke-width="2"/>
                                                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" stroke="currentColor" stroke-width="2"/>
                                                </svg>
                                            </a>
                                        @endcan

                                        @can('locations.edit')
                                            <form action="{{ route('halls.status', $hall->id) }}" 
                                                  method="POST" 
                                                  style="display: inline;"
                                                  onsubmit="return confirm('Are you sure you want to {{ $hall->is_active ? 'deactivate' : 'activate' }} this hall?');">
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="is_active" value="{{ $hall->is_active ? '0' : '1' }}">
                                                <input type="hidden" name="listing_status" value="{{ $listingStatus }}">
                                                <button type="submit" 
                                                        class="action-btn {{ $hall->is_active ? 'action-btn-deactivate' : 'action-btn-activate' }}"
                                                        title="{{ $hall->is_active ? 'Deactivate' : 'Activate' }} hall">
                                                    @if ($hall->is_active)
                                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                                                            <line x1="18" y1="6" x2="6" y2="18" stroke="currentColor" stroke-width="2"/>
                                                            <line x1="6" y1="6" x2="18" y2="18" stroke="currentColor" stroke-width="2"/>
                                                        </svg>
                                                    @else
                                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                                                            <polyline points="20 6 9 17 4 12" stroke="currentColor" stroke-width="2"/>
                                                        </svg>
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
        @else
            <div class="empty-state">
                <svg class="empty-icon" width="64" height="64" viewBox="0 0 24 24" fill="none">
                    <rect x="3" y="3" width="7" height="7" stroke="currentColor" stroke-width="2"/>
                    <rect x="14" y="3" width="7" height="7" stroke="currentColor" stroke-width="2"/>
                    <rect x="3" y="14" width="7" height="7" stroke="currentColor" stroke-width="2"/>
                    <rect x="14" y="14" width="7" height="7" stroke="currentColor" stroke-width="2"/>
                </svg>
                <h3>No halls found</h3>
                <p>
                    @if ($listingStatus === 'inactive')
                        No inactive halls in this location.
                    @else
                        Get started by creating your first hall for {{ $location->name }}.
                    @endif
                </p>
                @can('locations.create')
                    @if ($listingStatus === 'active')
                        <a href="{{ route('halls.create', ['store_id' => $location->id]) }}" class="button button-primary">
                            <span class="button-plus">+</span> Add Hall
                        </a>
                    @endif
                @endcan
            </div>
        @endif
    </section>

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

        .location-info-card {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            padding: 20px;
            background: linear-gradient(135deg, #eff6ff 0%, #e0f2fe 100%);
            border-radius: 8px;
            border: 1px solid #bfdbfe;
            margin-bottom: 24px;
        }

        .info-item {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .info-label {
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            color: #475569;
            letter-spacing: 0.5px;
        }

        .info-value {
            font-size: 16px;
            font-weight: 700;
            color: #0f172a;
        }

        .panel-toolbar {
            padding: 16px 20px;
            border-bottom: 1px solid #e2e8f0;
            background: #f8f9fa;
        }

        .status-tabs {
            display: flex;
            gap: 8px;
        }

        .status-tab {
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            color: #64748b;
            text-decoration: none;
            transition: all 0.15s;
            border: 1px solid transparent;
        }

        .status-tab:hover {
            background: white;
            color: #334155;
        }

        .status-tab.active {
            background: white;
            color: #2563eb;
            border-color: #2563eb;
        }

        .table-container {
            overflow-x: auto;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table thead {
            background: #f8f9fa;
        }

        .data-table th,
        .data-table td {
            padding: 12px 16px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }

        .data-table th {
            font-size: 13px;
            font-weight: 700;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .data-table td {
            font-size: 14px;
            color: #334155;
        }

        .data-table tbody tr:hover {
            background: #f8f9fa;
        }

        .actions-column {
            width: 150px;
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

        .action-btn-view {
            color: #2563eb;
        }

        .action-btn-view:hover {
            background: #eff6ff;
            border-color: #2563eb;
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
            gap: 6px;
            text-decoration: none;
        }

        .button-primary {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: white;
        }

        .button-primary:hover {
            background: linear-gradient(135deg, #1d4ed8, #1e40af);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
        }

        .button-plus {
            font-size: 20px;
            line-height: 1;
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
    </style>
@endsection
