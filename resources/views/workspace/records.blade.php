@extends('layouts.app')

@section('title', $title)
@section('topbar-title', $title)

@section('content')
    <div class="page-heading workspace-page-heading">
        <div>
            <span class="section-kicker">{{ $eyebrow }}</span>
            <h1>{{ $title }}</h1>
            <p>{{ $description }}</p>
        </div>
        @if ($showOpeningStockAction)
                    <a class="button button-primary" href="{{ route('opening-stock.create') }}"><span class="button-plus">+</span> Add Opening Stock</a>
        @elseif ($createRoute)
            <a class="button button-primary" href="{{ route($createRoute) }}"><span class="button-plus">+</span> {{ $createLabel }}</a>
        @elseif (request()->routeIs('stock-movement.index'))
            <a class="button button-light" href="{{ route('stock-inwards.index') }}">View Stock Inward</a>
        @endif
    </div>

    @if (request()->routeIs('stock-transfers.index'))
        @php
            $fromLocations = $rows->pluck('from_location')->filter()->unique()->values();
            $toLocations = $rows->pluck('to_location')->filter()->unique()->values();
            $products = $rows->pluck('product_name')->filter()->unique()->values();
            $transferCount = $rows->total();
            $transferQty = $rows->sum(function ($row) {
                return is_numeric($row->quantity ?? null) ? (float) $row->quantity : 0;
            });
            $sourceLocationCount = $fromLocations->count();
            $destinationLocationCount = $toLocations->count();
        @endphp

        <div class="stock-transfer-summary">
            <article class="stock-transfer-summary-card">
                <span class="stock-transfer-summary-icon transfer-icon-blue" aria-hidden="true">⇄</span>
                <div>
                    <span>Total Transfers</span>
                    <strong>{{ number_format($transferCount) }}</strong>
                    <small>All recorded transfers</small>
                </div>
            </article>
            <article class="stock-transfer-summary-card">
                <span class="stock-transfer-summary-icon transfer-icon-green" aria-hidden="true">▣</span>
                <div>
                    <span>Total Quantity Transferred</span>
                    <strong>{{ number_format($transferQty, 2) }}</strong>
                    <small>Units transferred</small>
                </div>
            </article>
            <article class="stock-transfer-summary-card">
                <span class="stock-transfer-summary-icon transfer-icon-yellow" aria-hidden="true">◌</span>
                <div>
                    <span>Transfer From Locations</span>
                    <strong>{{ number_format($sourceLocationCount) }}</strong>
                    <small>Unique source locations</small>
                </div>
            </article>
            <article class="stock-transfer-summary-card">
                <span class="stock-transfer-summary-icon transfer-icon-pink" aria-hidden="true">◌</span>
                <div>
                    <span>Transfer To Locations</span>
                    <strong>{{ number_format($destinationLocationCount) }}</strong>
                    <small>Unique destination locations</small>
                </div>
            </article>
        </div>

        <section class="panel stock-transfer-list-panel">
            <form class="stock-transfer-filters" method="GET" action="{{ route('stock-transfers.index') }}">
                <label class="stock-transfer-search">
                    <span class="search-glyph" aria-hidden="true">⌕</span>
                    <input type="search" name="search" value="{{ request('search') }}" placeholder="Search by reference, product, from or to location..." aria-label="Search stock transfers">
                </label>
                <label class="stock-transfer-date"><span>From</span><input type="date" name="date_from" value="{{ request('date_from') }}" aria-label="From date"></label>
                <label class="stock-transfer-date"><span>To</span><input type="date" name="date_to" value="{{ request('date_to') }}" aria-label="To date"></label>
                <label class="stock-transfer-select">
                    <span class="sr-only">Product</span>
                    <select name="product" aria-label="Filter by product">
                        <option value="">All Products</option>
                        @foreach ($products as $product)
                            <option value="{{ $product }}" {{ request('product') === $product ? 'selected' : '' }}>{{ $product }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="stock-transfer-select">
                    <span class="sr-only">From location</span>
                    <select name="from_location" aria-label="Filter by source location">
                        <option value="">All From Locations</option>
                        @foreach ($fromLocations as $location)
                            <option value="{{ $location }}" {{ request('from_location') === $location ? 'selected' : '' }}>{{ $location }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="stock-transfer-select">
                    <span class="sr-only">To location</span>
                    <select name="to_location" aria-label="Filter by destination location">
                        <option value="">All To Locations</option>
                        @foreach ($toLocations as $location)
                            <option value="{{ $location }}" {{ request('to_location') === $location ? 'selected' : '' }}>{{ $location }}</option>
                        @endforeach
                    </select>
                </label>
                <button class="button button-primary stock-transfer-search-button" type="submit"><span class="search-button-glyph" aria-hidden="true">⌕</span> Search</button>
                <a class="button button-light stock-transfer-reset-button" href="{{ route('stock-transfers.index') }}"><span aria-hidden="true">↻</span> Reset</a>
            </form>

            @if ($rows->count())
                <div class="stock-transfer-table-wrap">
                    <table class="stock-transfer-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>REFERENCE</th>
                                <th>PRODUCT</th>
                                <th>FROM</th>
                                <th>TO</th>
                                <th>QUANTITY</th>
                                <th>DATE</th>
                                <th>ACTIONS</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $row)
                                <tr>
                                    <td><span class="stock-transfer-row-number">{{ $rows->firstItem() + $loop->index }}</span></td>
                                    <td><span class="stock-transfer-code">{{ $row->transfer_number ?? '—' }}</span></td>
                                    <td>{{ $row->product_name ?? '—' }}</td>
                                    <td>{{ $row->from_location ?? '—' }}</td>
                                    <td>{{ $row->to_location ?? '—' }}</td>
                                    <td class="stock-transfer-quantity">{{ is_numeric($row->quantity ?? null) ? number_format((float) $row->quantity, 2) : '—' }}</td>
                                    <td>{{ $row->transfer_date ? \Carbon\Carbon::parse($row->transfer_date)->format('d M Y') : '—' }}</td>
                                    <td>
                                        <div class="stock-transfer-actions">
                                            <a class="stock-transfer-view-button" href="{{ route('stock-transfers.show', $row->id) }}">View</a>
                                            <button class="stock-transfer-more" type="button" aria-label="More actions">⋮</button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <footer class="stock-transfer-table-footer">
                    <span>Showing {{ number_format($rows->firstItem()) }}–{{ number_format($rows->lastItem()) }} of {{ number_format($rows->total()) }} records</span>
                    @if ($rows->hasPages())
                        <div class="stock-transfer-pagination">{{ $rows->onEachSide(1)->links() }}</div>
                    @endif
                </footer>
            @else
                <div class="stock-transfer-empty">
                    <p>No stock transfer records match these filters.</p>
                </div>
            @endif
        </section>

        <style>
            .stock-transfer-summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-bottom:18px}
            .stock-transfer-summary-card{min-height:82px;padding:13px 14px;display:flex;align-items:center;gap:13px;border:1px solid #e8edf3;border-radius:13px;background:#fff;box-shadow:0 4px 16px rgba(20,42,64,.035)}
            .stock-transfer-summary-icon{width:42px;height:42px;display:grid;place-items:center;flex:none;border-radius:12px;font-size:24px;font-weight:700}
            .transfer-icon-blue{background:#eaf0ff;color:#315fd6}.transfer-icon-green{background:#e5f8ef;color:#16a06b}.transfer-icon-yellow{background:#fff7df;color:#efae00}.transfer-icon-pink{background:#ffe9ee;color:#f04464}
            .stock-transfer-summary-card div{min-width:0;display:grid;gap:3px}
            .stock-transfer-summary-card div>span{color:#415a71;font-size:12px;font-weight:700}
            .stock-transfer-summary-card strong{overflow:hidden;color:#102f40;font-size:19px;text-overflow:ellipsis;white-space:nowrap}
            .stock-transfer-summary-card small{color:#8191a3;font-size:11px}
            .stock-transfer-list-panel{padding:0;overflow:visible;border:1px solid #e8edf3;border-radius:14px;box-shadow:0 4px 16px rgba(20,42,64,.035)}
            .stock-transfer-filters{padding:14px 12px 10px;display:grid;grid-template-columns:1.2fr 0.9fr 0.9fr 0.9fr 0.9fr 0.9fr auto auto;gap:9px;align-items:center}
            .stock-transfer-search,.stock-transfer-date,.stock-transfer-select{height:40px;display:flex;align-items:center;gap:9px;border:1px solid #dce4ed;border-radius:8px;background:#fff;color:#71849a}
            .stock-transfer-search{min-width:0}
            .stock-transfer-search>span{padding-left:11px;font-size:21px}
            .stock-transfer-search input,.stock-transfer-date input,.stock-transfer-select select{width:100%;min-width:0;height:100%;padding:0 10px;border:0;outline:0;background:transparent;color:#30465c;font:inherit;font-size:12px}
            .stock-transfer-date{padding-left:9px;gap:5px}
            .stock-transfer-date span{font-size:10px}
            .stock-transfer-date input{padding:0 6px}
            .stock-transfer-select select{appearance:none;cursor:pointer}
            .stock-transfer-search-button,.stock-transfer-reset-button{height:40px;display:inline-flex;align-items:center;justify-content:center;gap:7px;white-space:nowrap}
            .stock-transfer-table-wrap{width:100%;overflow-x:auto}
            .stock-transfer-table{width:100%;min-width:1100px;border-collapse:collapse}
            .stock-transfer-table th{padding:11px 9px;background:#f5f8fb;color:#697e93;text-align:left;font-size:10px;font-weight:700;line-height:1.25;white-space:nowrap}
            .stock-transfer-table td{padding:11px 9px;border-top:1px solid #edf1f5;color:#354b61;font-size:11px;white-space:nowrap}
            .stock-transfer-row-number{color:#7790a5}
            .stock-transfer-code{padding:4px 6px;border-radius:5px;background:#edf3f8;color:#405a70;font-size:10px;font-weight:700}
            .stock-transfer-quantity{color:#187655!important;font-weight:700}
            .stock-transfer-actions{display:flex;align-items:center;gap:8px}
            .stock-transfer-view-button{display:inline-flex;align-items:center;justify-content:center;padding:7px 12px;border:1px solid #dfe7ec;border-radius:8px;background:#fff;color:#4c6475;font-size:10px;font-weight:700;text-decoration:none}
            .stock-transfer-more{width:30px;height:30px;border:1px solid #dfe7ec;border-radius:8px;background:#fff;color:#4c6475;cursor:pointer}
            .stock-transfer-table-footer{display:flex;min-height:54px;align-items:center;justify-content:space-between;gap:12px;padding:9px 18px;border-top:1px solid #f0f2f4;color:#5d6d76;font-size:9px}
            .stock-transfer-pagination{display:flex;align-items:center;gap:4px}
            .stock-transfer-pagination .page-link,.stock-transfer-pagination .page-item.active .page-link{display:grid;min-width:27px;height:27px;padding:0 6px;place-items:center;border:1px solid #e9eef0;border-radius:6px;color:#63747d;background:#fff;font:inherit;font-size:9px;text-decoration:none}
            .stock-transfer-pagination .page-item.active .page-link{border-color:#008b8c;color:#fff;background:#008b8c}
            .stock-transfer-pagination .disabled .page-link{color:#b3bdc2;background:#f8f9fa;cursor:default}
            .stock-transfer-empty{padding:24px 18px;text-align:center;color:#778996;font-size:12px}
            @media(max-width:1200px){.stock-transfer-summary{grid-template-columns:repeat(2,minmax(0,1fr))}.stock-transfer-filters{grid-template-columns:repeat(2,minmax(0,1fr))}.stock-transfer-search{grid-column:1/-1}.stock-transfer-search-button,.stock-transfer-reset-button{justify-self:start}}
            @media(max-width:680px){.stock-transfer-summary{grid-template-columns:1fr 1fr;gap:8px}.stock-transfer-summary-card{padding:10px;gap:8px}.stock-transfer-summary-icon{width:34px;height:34px;font-size:19px}.stock-transfer-summary-card strong{font-size:15px}.stock-transfer-filters{grid-template-columns:1fr}.stock-transfer-table-footer{align-items:flex-start;flex-direction:column}}
        </style>
    @else
        @if (count($summary))
            <section class="workspace-summary-grid {{ request()->routeIs('stock-adjustments.index') ? 'stock-adjustment-summary' : (request()->routeIs('stock-movement.index') ? 'stock-movement-summary' : '') }}" aria-label="{{ $title }} summary">
                @foreach ($summary as $card)
                    <article class="workspace-summary-card">
                        <span class="workspace-summary-icon workspace-{{ $card['tone'] }}">
                            <svg><use href="#{{ $card['icon'] }}"></use></svg>
                        </span>
                        <div><small>{{ $card['label'] }}</small><strong>{{ $card['value'] }}</strong></div>
                    </article>
                @endforeach
            </section>
        @endif

        <section class="panel workspace-panel {{ request()->routeIs('stock-adjustments.index') ? 'stock-adjustment-panel' : (request()->routeIs('stock-movement.index') ? 'stock-movement-panel' : '') }}">
        @if (request()->routeIs('stock-movement.index'))
            <form class="workspace-toolbar stock-ledger-filters" method="GET" action="{{ route('stock-movement.index') }}">
                <label class="search-field workspace-search stock-ledger-search">
                    <span class="search-glyph" aria-hidden="true">⌕</span>
                    <input type="search" name="search" value="{{ request('search') }}" placeholder="Search by reference, product, location or type..." aria-label="Search stock movement">
                </label>
                <label class="date-filter"><span>From</span><input type="date" name="date_from" value="{{ request('date_from') }}" aria-label="Movement from date"></label>
                <label class="date-filter"><span>To</span><input type="date" name="date_to" value="{{ request('date_to') }}" aria-label="Movement to date"></label>
                <select class="filter-select" name="type" aria-label="Filter by movement type">
                    <option value="">All Types</option>
                    @foreach ($pageOptions['movementTypes'] ?? [] as $type)
                        <option value="{{ $type }}" {{ request('type') === $type ? 'selected' : '' }}>{{ $type }}</option>
                    @endforeach
                </select>
                <select class="filter-select" name="product" aria-label="Filter by product">
                    <option value="">All Products</option>
                    @foreach ($pageOptions['movementProducts'] ?? [] as $product)
                        <option value="{{ $product }}" {{ request('product') === $product ? 'selected' : '' }}>{{ $product }}</option>
                    @endforeach
                </select>
                <select class="filter-select" name="location" aria-label="Filter by location">
                    <option value="">All Locations</option>
                    @foreach ($pageOptions['movementLocations'] ?? [] as $location)
                        <option value="{{ $location }}" {{ request('location') === $location ? 'selected' : '' }}>{{ $location }}</option>
                    @endforeach
                </select>
                <button class="button button-primary" type="submit"><span class="search-button-glyph" aria-hidden="true">⌕</span> Search</button>
                @if (request()->hasAny(['search', 'date_from', 'date_to', 'type', 'product', 'location']))
                    <a class="button button-light" href="{{ route('stock-movement.index') }}">Reset</a>
                @endif
                <span class="workspace-result-count">{{ number_format($rows->total()) }} records found</span>
            </form>
        @elseif (request()->routeIs('stock-adjustments.index'))
            <form class="workspace-toolbar stock-ledger-filters" method="GET" action="{{ route('stock-adjustments.index') }}">
                <label class="search-field workspace-search stock-ledger-search">
                    <span class="search-glyph" aria-hidden="true">⌕</span>
                    <input type="search" name="search" value="{{ request('search') }}" placeholder="Search by reference, product or reason..." aria-label="Search stock adjustments">
                </label>
                <label class="date-filter"><span>From</span><input type="date" name="date_from" value="{{ request('date_from') }}" aria-label="Adjustment from date"></label>
                <label class="date-filter"><span>To</span><input type="date" name="date_to" value="{{ request('date_to') }}" aria-label="Adjustment to date"></label>
                <select class="filter-select" name="type" aria-label="Filter by adjustment type">
                    <option value="">All Types</option>
                    @foreach ($pageOptions['adjustmentTypes'] ?? [] as $type)
                        <option value="{{ $type }}" {{ request('type') === $type ? 'selected' : '' }}>{{ $type }}</option>
                    @endforeach
                </select>
                <select class="filter-select" name="product" aria-label="Filter by product">
                    <option value="">All Products</option>
                    @foreach ($pageOptions['adjustmentProducts'] ?? [] as $product)
                        <option value="{{ $product }}" {{ request('product') === $product ? 'selected' : '' }}>{{ $product }}</option>
                    @endforeach
                </select>
                <button class="button button-primary" type="submit"><span class="search-button-glyph" aria-hidden="true">⌕</span> Search</button>
                @if (request()->hasAny(['search', 'date_from', 'date_to', 'type', 'product']))
                    <a class="button button-light" href="{{ route('stock-adjustments.index') }}">Reset</a>
                @endif
                <span class="workspace-result-count">{{ number_format($rows->total()) }} adjustments found</span>
            </form>
        @elseif (request()->routeIs('users.index'))
            <form class="workspace-toolbar" method="GET" action="{{ route('users.index') }}">
                <label class="search-field workspace-search">
                    <span class="search-glyph" aria-hidden="true">⌕</span>
                    <input type="search" name="search" value="{{ request('search') }}" placeholder="Search user name or email..." aria-label="Search users">
                </label>
                <select class="filter-select" name="status" aria-label="Filter users by status">
                    <option value="">All account statuses</option>
                    <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                    <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
                </select>
                <button class="button button-primary" type="submit">Apply</button>
                @if (request()->hasAny(['search', 'status']))
                    <a class="button button-light" href="{{ route('users.index') }}">Reset</a>
                @endif
                <span class="workspace-result-count">{{ number_format($rows->total()) }} users found</span>
            </form>
        @elseif (request()->routeIs('activity-log.index'))
            <form class="workspace-toolbar" method="GET" action="{{ route('activity-log.index') }}">
                <label class="search-field workspace-search">
                    <span class="search-glyph" aria-hidden="true">⌕</span>
                    <input type="search" name="search" value="{{ request('search') }}" placeholder="Search user, activity or details..." aria-label="Search activity log">
                </label>
                <select class="filter-select" name="action" aria-label="Filter by activity type">
                    <option value="">All activity types</option>
                    @foreach ($pageOptions['actions'] ?? [] as $action)
                        <option value="{{ $action }}" {{ request('action') === $action ? 'selected' : '' }}>{{ $action }}</option>
                    @endforeach
                </select>
                <label class="date-filter"><span>From</span><input type="date" name="from" value="{{ request('from') }}" aria-label="Activity from date"></label>
                <label class="date-filter"><span>To</span><input type="date" name="to" value="{{ request('to') }}" aria-label="Activity to date"></label>
                <button class="button button-primary" type="submit">Apply</button>
                @if (request()->hasAny(['search', 'action', 'from', 'to']))
                    <a class="button button-light" href="{{ route('activity-log.index') }}">Reset</a>
                @endif
                <span class="workspace-result-count">{{ number_format($rows->total()) }} entries found</span>
            </form>
        @endif

        @if ($rows->count())
            <div class="table-wrap">
                <table class="data-table workspace-table {{ request()->routeIs('stock-adjustments.index') ? 'stock-adjustment-table' : (request()->routeIs('stock-movement.index') ? 'stock-movement-table' : '') }}">
                    <thead>
                        <tr>
                            @foreach ($columns as $column)
                                <th>{{ strtoupper($column['label']) }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr>
                                @foreach ($columns as $column)
                                    @php($value = data_get($row, $column['key']))
                                    <td>
                                        @if (($column['type'] ?? '') === 'active')
                                            @include('components.status-badge', ['status' => $value ? 'Active' : 'Inactive'])
                                        @elseif (($column['type'] ?? '') === 'transfer-link')
                                            <a href="{{ route('stock-transfers.show', $value) }}">{{ $row->transfer_number }}</a>
                                        @elseif (($column['type'] ?? '') === 'date')
                                            {{ $value ? \Carbon\Carbon::parse($value)->format('d M Y') : '—' }}
                                        @elseif (($column['type'] ?? '') === 'datetime')
                                            {{ $value ? \Carbon\Carbon::parse($value)->format('d M Y, h:i A') : '—' }}
                                        @elseif (($column['type'] ?? '') === 'quantity')
                                            {{ is_numeric($value) ? number_format((float) $value, 2) : '—' }}
                                        @elseif (($column['type'] ?? '') === 'currency')
                                            {{ is_numeric($value) ? '₹' . number_format((float) $value, 2) : '—' }}
                                        @elseif (($column['type'] ?? '') === 'movement-type')
                                            <span class="movement-type-badge movement-type-{{ \Illuminate\Support\Str::slug($value ?? 'unknown') }}">{{ $value ?: '—' }}</span>
                                        @elseif (($column['type'] ?? '') === 'adjustment-type')
                                            <span class="adjustment-type-badge adjustment-type-{{ \Illuminate\Support\Str::slug($value ?? 'unknown') }}">{{ $value ?: '—' }}</span>
                                        @else
                                            {{ $value !== null && $value !== '' ? $value : '—' }}
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <footer class="workspace-footer">
                <span>Showing {{ number_format($rows->firstItem()) }}–{{ number_format($rows->lastItem()) }} of {{ number_format($rows->total()) }} records</span>
                @if ($rows->hasPages())
                    <div class="workspace-pagination">{{ $rows->onEachSide(2)->links() }}</div>
                @endif
            </footer>
        @else
            <div class="workspace-empty">
                <span class="workspace-empty-icon"><svg><use href="#icon-box"></use></svg></span>
                <h2>No {{ strtolower($title) }} found</h2>
                <p>{{ $description }}</p>
                @if ($showOpeningStockAction)
                    <a class="button button-primary" href="{{ route('opening-stock.create') }}"><span class="button-plus">+</span> Add Opening Stock</a>
                @elseif ($createRoute)
                    <a class="button button-primary" href="{{ route($createRoute) }}"><span class="button-plus">+</span> {{ $createLabel }}</a>
                @endif
            </div>
        @endif
    </section>
    @if (request()->routeIs('stock-adjustments.index', 'stock-movement.index'))
        <style>
            .stock-adjustment-summary,.stock-movement-summary{grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}
            .stock-movement-summary{grid-template-columns:repeat(5,minmax(0,1fr))}
            .stock-adjustment-summary .workspace-summary-card,.stock-movement-summary .workspace-summary-card{min-height:82px;padding:14px;border:1px solid #e8edf3;border-radius:13px;background:#fff;box-shadow:0 4px 16px rgba(20,42,64,.04)}
            .stock-adjustment-summary .workspace-summary-card small,.stock-movement-summary .workspace-summary-card small{color:#63788c;font-size:11px;font-weight:600}
            .stock-adjustment-summary .workspace-summary-card strong,.stock-movement-summary .workspace-summary-card strong{color:#102f40;font-size:19px}
            .stock-adjustment-panel,.stock-movement-panel{padding:0;overflow:hidden;border:1px solid #e8edf3;border-radius:14px;box-shadow:0 4px 16px rgba(20,42,64,.04)}
            .stock-ledger-filters{display:grid;grid-template-columns:minmax(220px,1.5fr) repeat(2,minmax(135px,.8fr)) repeat(3,minmax(120px,.8fr)) auto auto;gap:9px;align-items:center;padding:14px}
            .stock-ledger-filters .filter-select,.stock-ledger-filters .date-filter{width:100%;min-width:0;min-height:40px}
            .stock-ledger-search{min-width:0}
            .stock-adjustment-table thead th,.stock-movement-table thead th{background:#f5f8fb;color:#63788c;font-size:10px;font-weight:700;white-space:nowrap}
            .stock-adjustment-table tbody td,.stock-movement-table tbody td{padding-top:11px;padding-bottom:11px;border-top:1px solid #edf1f5;color:#354b61;font-size:11px}
            .adjustment-type-badge,.movement-type-badge{display:inline-flex;align-items:center;padding:4px 8px;border-radius:999px;background:#edf3f8;color:#486174;font-size:10px;font-weight:700;white-space:nowrap}
            .adjustment-type-increase,.movement-type-stock-inward,.movement-type-opening-stock,.movement-type-transfer-in{background:#e5f8ef;color:#16845d}
            .adjustment-type-decrease,.movement-type-stock-outward,.movement-type-transfer-out{background:#ffe9ee;color:#d7445b}
            .movement-type-stock-transfer{background:#eaf0ff;color:#315fd6}
            .movement-type-stock-adjustment{background:#f3eaff;color:#8246c5}
            @media(max-width:1200px){.stock-adjustment-summary,.stock-movement-summary{grid-template-columns:repeat(2,minmax(0,1fr))}.stock-ledger-filters{grid-template-columns:repeat(2,minmax(0,1fr))}.stock-ledger-search{grid-column:1/-1}}
            @media(max-width:680px){.stock-adjustment-summary,.stock-movement-summary{grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}.stock-adjustment-summary .workspace-summary-card,.stock-movement-summary .workspace-summary-card{padding:10px}.stock-ledger-filters{grid-template-columns:1fr}.stock-ledger-search{grid-column:auto}}
        </style>
    @endif
    @endif
@endsection
