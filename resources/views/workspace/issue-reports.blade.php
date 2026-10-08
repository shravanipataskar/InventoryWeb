@extends('layouts.app')

@section('title', 'Issue Reports')
@section('topbar-title', 'Issue Reports')

@section('content')
    @php
        $exportQuery = array_filter($filters, function ($value) { return $value !== null && $value !== ''; });
    @endphp
    <div class="page-heading issue-report-heading">
        <div><span class="section-kicker">REPORTS / STOCK MOVEMENT</span><h1>Issue Reports</h1><p>Issues and recipients recorded through active stock outward transactions.</p></div>
        <details class="issue-export-menu">
            <summary class="button button-primary"><svg><use href="#icon-tray-out"></use></svg> Export <span aria-hidden="true">⌄</span></summary>
            <div class="issue-export-options">
                <strong>Export Current Report</strong>
                <a href="{{ route('issue-reports.export', array_merge(['format' => 'excel', 'report_type' => 'current'], $exportQuery)) }}">Current report · Excel</a>
                <a href="{{ route('issue-reports.export', array_merge(['format' => 'csv', 'report_type' => 'current'], $exportQuery)) }}">Current report · CSV</a>
                <a href="{{ route('issue-reports.export', array_merge(['format' => 'excel', 'report_type' => 'details'], $exportQuery)) }}">Issue details · Excel</a>
                <a href="{{ route('issue-reports.export', array_merge(['format' => 'csv', 'report_type' => 'details'], $exportQuery)) }}">Issue details · CSV</a>
                <a href="{{ route('issue-reports.export', array_merge(['format' => 'excel', 'report_type' => 'recipients'], $exportQuery)) }}">Recipient summary · Excel</a>
                <a href="{{ route('issue-reports.export', array_merge(['format' => 'csv', 'report_type' => 'recipients'], $exportQuery)) }}">Recipient summary · CSV</a>
                <a href="{{ route('issue-reports.export', array_merge(['format' => 'excel', 'report_type' => 'products'], $exportQuery)) }}">Product summary · Excel</a>
                <a href="{{ route('issue-reports.export', array_merge(['format' => 'csv', 'report_type' => 'products'], $exportQuery)) }}">Product summary · CSV</a>
                <a href="{{ route('issue-reports.export', array_merge(['format' => 'print'], $exportQuery)) }}" target="_blank" rel="noopener">Print / Save as PDF</a>
            </div>
        </details>
    </div>

    <section class="panel issue-report-filters">
        <div class="purchase-report-section-heading"><span class="section-kicker">FILTER DATA</span><h2>Report Filters</h2></div>
        <form method="GET" action="{{ route('issue-reports.index') }}" class="issue-report-filter-grid">
            <label class="field"><span>Date From</span><input class="field-control" type="date" name="date_from" value="{{ $filters['date_from'] }}"></label>
            <label class="field"><span>Date To</span><input class="field-control" type="date" name="date_to" value="{{ $filters['date_to'] }}"></label>
            <label class="field"><span>Issue Type</span><select class="field-control" disabled aria-describedby="issue-report-data-note"><option>Not recorded</option></select></label>
            <label class="field"><span>Recipient / Department</span><select class="field-control" name="recipient"><option value="">All Recipients</option>@foreach ($recipients as $recipient)<option value="{{ $recipient }}" {{ $filters['recipient'] === $recipient ? 'selected' : '' }}>{{ $recipient }}</option>@endforeach</select></label>
            <label class="field"><span>Product</span><select class="field-control" name="product_id"><option value="">All Products</option>@foreach ($products as $product)<option value="{{ $product->id }}" {{ (string) $filters['product_id'] === (string) $product->id ? 'selected' : '' }}>{{ $product->name }} ({{ $product->product_code }})</option>@endforeach</select></label>
            <label class="field"><span>Category</span><select class="field-control" name="category_id"><option value="">All Categories</option>@foreach ($categories as $category)<option value="{{ $category->id }}" {{ (string) $filters['category_id'] === (string) $category->id ? 'selected' : '' }}>{{ $category->name }}</option>@endforeach</select></label>
            <label class="field"><span>Location / Store</span><select class="field-control" name="location_id"><option value="">All Product Locations</option>@foreach ($locations as $location)<option value="{{ $location->id }}" {{ (string) $filters['location_id'] === (string) $location->id ? 'selected' : '' }}>{{ $location->name }}</option>@endforeach</select></label>
            <label class="field"><span>Issued By</span><select class="field-control" disabled aria-describedby="issue-report-data-note"><option>Not recorded</option></select></label>
            <div class="issue-report-filter-actions"><button class="button button-primary" type="submit">Apply Filters</button><a class="button button-light" href="{{ route('issue-reports.index') }}">Reset</a></div>
        </form>
        <p class="issue-report-data-note" id="issue-report-data-note">The existing Stock Outward records do not store issue type, source store, issuing user, or transaction-time inventory cost. Location shows each product’s assigned hall/rack/shelf, and value uses its saved purchase price; unavailable transaction details are shown as —.</p>
    </section>

    <section class="purchase-report-kpis issue-report-kpis" aria-label="Issue report summary">
        <article class="panel purchase-report-kpi"><span class="purchase-report-kpi-icon purchase-kpi-blue"><svg><use href="#icon-tray-out"></use></svg></span><div><small>Issue Transactions</small><strong>{{ number_format((int) $aggregate->transaction_count) }}</strong><span>Active issue references matching filters</span></div></article>
        <article class="panel purchase-report-kpi"><span class="purchase-report-kpi-icon purchase-kpi-green">₹</span><div><small>Total Issued Value</small><strong>₹{{ number_format((float) $aggregate->total_value, 2) }}</strong><span>Quantity × current product purchase cost</span></div></article>
        <article class="panel purchase-report-kpi"><span class="purchase-report-kpi-icon purchase-kpi-purple"><svg><use href="#icon-box"></use></svg></span><div><small>Total Quantity Issued</small><strong>{{ number_format((float) $aggregate->total_quantity, 2) }} <small>Units</small></strong><span>Active stock outward quantity</span></div></article>
        <article class="panel purchase-report-kpi"><span class="purchase-report-kpi-icon purchase-kpi-amber"><svg><use href="#icon-users"></use></svg></span><div><small>Total Recipients</small><strong>{{ number_format((int) $aggregate->recipient_count) }}</strong><span>Distinct named recipients</span></div></article>
    </section>

    <section class="panel issue-report-transactions">
        <div class="purchase-report-table-heading">
            <div><span class="section-kicker">STOCK OUTWARD RECORDS</span><h2>Issue Transactions</h2><p>{{ number_format($issues->total()) }} matching product lines</p></div>
            <form method="GET" action="{{ route('issue-reports.index') }}" class="purchase-report-search">
                @foreach ($filters as $key => $value)
                    @if ($key !== 'search' && $value !== null && $value !== '')<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endif
                @endforeach
                <span aria-hidden="true">⌕</span><input type="search" name="search" value="{{ $filters['search'] }}" placeholder="Search reference, product, recipient..." aria-label="Search issue reports">
                <button type="submit" aria-label="Search">Search</button>
            </form>
        </div>
        @if ($issues->count())
            <div class="table-wrap purchase-report-table-wrap">
                <table class="data-table purchase-report-table issue-report-table">
                    <thead><tr><th>#</th><th>DATE</th><th>REFERENCE</th><th>ISSUE TYPE</th><th>PRODUCT</th><th>CATEGORY</th><th>RECIPIENT</th><th>LOCATION</th><th>QTY</th><th>UNIT COST</th><th>TOTAL</th><th>ISSUED BY</th><th>ACTION</th></tr></thead>
                    <tbody>
                    @foreach ($issues as $issue)
                        <tr>
                            <td>{{ $issues->firstItem() + $loop->index }}</td>
                            <td>{{ $issue->issue_date ? \Carbon\Carbon::parse($issue->issue_date)->format('d M Y') : '—' }}</td>
                            <td><strong>{{ $issue->reference_number ?: '—' }}</strong></td>
                            <td><span class="issue-report-type-badge">—</span></td>
                            <td><strong>{{ $issue->product_name ?: '—' }}</strong><small class="purchase-report-po-ref">{{ $issue->product_code ?: '—' }}</small></td>
                            <td>{{ $issue->category_name ?: '—' }}</td>
                            <td>{{ $issue->recipient_name ?: '—' }}</td>
                            <td>{{ $issue->location_name ?: '—' }}</td>
                            <td>{{ number_format((float) $issue->quantity, 2) }}</td>
                            <td>₹{{ number_format((float) $issue->unit_cost, 2) }}</td>
                            <td><strong>₹{{ number_format((float) $issue->total_value, 2) }}</strong></td>
                            <td>—</td>
                            <td><a class="button button-small button-light purchase-report-view" href="{{ route('issue-reports.show', $issue->issue_id) }}">View</a></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <footer class="workspace-footer"><span>Showing {{ number_format($issues->firstItem()) }}–{{ number_format($issues->lastItem()) }} of {{ number_format($issues->total()) }} product lines</span>@if ($issues->hasPages())<div class="workspace-pagination">{{ $issues->links() }}</div>@endif</footer>
        @else
            <div class="purchase-report-empty"><span class="workspace-empty-icon"><svg><use href="#icon-tray-out"></use></svg></span><h2>No issue reports found.</h2><p>Issues and recipients recorded through active stock outward transactions.</p><a class="button button-light" href="{{ route('issue-reports.index') }}">Reset Filters</a></div>
        @endif
    </section>
@endsection
