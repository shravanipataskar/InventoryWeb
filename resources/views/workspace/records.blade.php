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
                    <a class="button button-primary" href="{{ route('opening-stock.create') }}"><span class="button-plus">+</span> Add Initial Stock</a>
        @elseif ($createRoute)
            <a class="button button-primary" href="{{ route($createRoute) }}"><span class="button-plus">+</span> {{ $createLabel }}</a>
        @elseif (request()->routeIs('stock-movement.index'))
            <a class="button button-light" href="{{ route('stock-inwards.index') }}">View Stock Inward</a>
        @endif
    </div>

    @if (count($summary))
        <section class="workspace-summary-grid" aria-label="{{ $title }} summary">
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

    <section class="panel workspace-panel">
        @if (request()->routeIs('stock-movement.index'))
            <form class="workspace-toolbar" method="GET" action="{{ route('stock-movement.index') }}">
                <label class="search-field workspace-search">
                    <span class="search-glyph" aria-hidden="true">⌕</span>
                    <input type="search" name="search" value="{{ request('search') }}" placeholder="Search type, reference or product..." aria-label="Search stock movement">
                </label>
                <button class="button button-primary" type="submit">Search</button>
                @if (request()->filled('search'))
                    <a class="button button-light" href="{{ route('stock-movement.index') }}">Reset</a>
                @endif
                <span class="workspace-result-count">{{ number_format($rows->total()) }} records found</span>
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
                <table class="data-table workspace-table">
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
                                        @elseif (($column['type'] ?? '') === 'date')
                                            {{ $value ? \Carbon\Carbon::parse($value)->format('d M Y') : '—' }}
                                        @elseif (($column['type'] ?? '') === 'quantity')
                                            {{ is_numeric($value) ? number_format((float) $value, 2) : '—' }}
                                        @elseif (($column['type'] ?? '') === 'currency')
                                            {{ is_numeric($value) ? '₹' . number_format((float) $value, 2) : '—' }}
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
                    <a class="button button-primary" href="{{ route('opening-stock.create') }}"><span class="button-plus">+</span> Add Initial Stock</a>
                @elseif ($createRoute)
                    <a class="button button-primary" href="{{ route($createRoute) }}"><span class="button-plus">+</span> {{ $createLabel }}</a>
                @endif
            </div>
        @endif
    </section>
@endsection
