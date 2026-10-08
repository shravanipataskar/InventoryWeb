@extends('layouts.app')

@section('title', 'Purchase Reports')
@section('topbar-title', 'Purchase Reports')

@section('content')
    @php
        $exportQuery = array_filter($filters, function ($value) { return $value !== null && $value !== ''; });
        $statusClass = function ($status) {
            return [
                'Received' => 'purchase-status-received',
                'Partial' => 'purchase-status-partial',
                'Pending' => 'purchase-status-pending',
                'Cancelled' => 'purchase-status-cancelled',
                'Rejected' => 'purchase-status-rejected',
            ][$status] ?? 'purchase-status-cancelled';
        };
    @endphp
    <div class="page-heading purchase-report-heading">
        <div><span class="section-kicker">REPORTS / PROCUREMENT</span><h1>Purchase Reports</h1><p>Track purchases, suppliers, quantities and purchase costs.</p></div>
        <details class="purchase-export-menu">
            <summary class="button button-primary"><svg><use href="#icon-tray-in"></use></svg> Export <span aria-hidden="true">⌄</span></summary>
            <div class="purchase-export-options">
                <a href="{{ route('purchase-reports.export', array_merge(['format' => 'excel', 'report_type' => 'transactions'], $exportQuery)) }}">Purchase Transactions · Excel</a>
                <a href="{{ route('purchase-reports.export', array_merge(['format' => 'csv', 'report_type' => 'transactions'], $exportQuery)) }}">Purchase Transactions · CSV</a>
                <a href="{{ route('purchase-reports.export', array_merge(['format' => 'excel', 'report_type' => 'summary'], $exportQuery)) }}">Purchase Summary · Excel</a>
                <a href="{{ route('purchase-reports.export', array_merge(['format' => 'csv', 'report_type' => 'summary'], $exportQuery)) }}">Purchase Summary · CSV</a>
                <a href="{{ route('purchase-reports.export', array_merge(['format' => 'excel', 'report_type' => 'details'], $exportQuery)) }}">Purchase Details · Excel</a>
                <a href="{{ route('purchase-reports.export', array_merge(['format' => 'csv', 'report_type' => 'suppliers'], $exportQuery)) }}">Supplier Purchase Report · CSV</a>
                <a href="{{ route('purchase-reports.export', array_merge(['format' => 'print'], $exportQuery)) }}" target="_blank" rel="noopener">Print / Save as PDF</a>
            </div>
        </details>
    </div>

    <section class="panel purchase-report-filters">
        <div class="purchase-report-section-heading"><span class="section-kicker">FILTER DATA</span><h2>Report Filters</h2></div>
        <form method="GET" action="{{ route('purchase-reports.index') }}" class="purchase-report-filter-grid">
            <label class="field"><span>Date From</span><input class="field-control" type="date" name="date_from" value="{{ $filters['date_from'] }}"></label>
            <label class="field"><span>Date To</span><input class="field-control" type="date" name="date_to" value="{{ $filters['date_to'] }}"></label>
            <label class="field"><span>Supplier</span><select class="field-control" name="supplier_id"><option value="">All Suppliers</option>@foreach ($suppliers as $supplier)<option value="{{ $supplier->id }}" {{ (string) data_get($filters, 'supplier_id') === (string) $supplier->id ? 'selected' : '' }}>{{ $supplier->name }}</option>@endforeach</select></label>
            <label class="field"><span>Product</span><select class="field-control" name="product_id"><option value="">All Products</option>@foreach ($products as $product)<option value="{{ $product->id }}" {{ (string) data_get($filters, 'product_id') === (string) $product->id ? 'selected' : '' }}>{{ $product->name }} ({{ $product->product_code }})</option>@endforeach</select></label>
            <label class="field"><span>Category</span><select class="field-control" name="category_id"><option value="">All Categories</option>@foreach ($categories as $category)<option value="{{ $category->id }}" {{ (string) data_get($filters, 'category_id') === (string) $category->id ? 'selected' : '' }}>{{ $category->name }}</option>@endforeach</select></label>
            <label class="field"><span>Location</span><select class="field-control" name="store_id"><option value="">All Locations</option>@foreach ($locations as $location)<option value="{{ $location->id }}" {{ (string) data_get($filters, 'store_id') === (string) $location->id ? 'selected' : '' }}>{{ $location->name }}</option>@endforeach</select></label>
            <label class="field"><span>Purchase Status</span><select class="field-control" name="status"><option value="">All Statuses</option>@foreach (['Received', 'Partial', 'Pending', 'Cancelled', 'Rejected'] as $status)<option value="{{ $status }}" {{ data_get($filters, 'status') === $status ? 'selected' : '' }}>{{ $status }}</option>@endforeach</select></label>
            <label class="field"><span>PO / GRN / Invoice</span><input class="field-control" type="search" name="search" value="{{ data_get($filters, 'search') }}" placeholder="Search reference, product or supplier"></label>
            <div class="purchase-report-filter-actions"><button class="button button-primary" type="submit">Apply Filters</button><a class="button button-light" href="{{ route('purchase-reports.index') }}">Reset</a></div>
        </form>
    </section>

    <section class="purchase-report-kpis" aria-label="Purchase report summary">
        <article class="panel purchase-report-kpi"><span class="purchase-report-kpi-icon purchase-kpi-blue"><svg><use href="#icon-tray-in"></use></svg></span><div><small>Purchase Transactions</small><strong>{{ number_format((int) $aggregate->transaction_count) }}</strong><span>POs and GRNs within this report</span></div></article>
        <article class="panel purchase-report-kpi"><span class="purchase-report-kpi-icon purchase-kpi-green">₹</span><div><small>Total Purchase Value</small><strong>₹{{ number_format((float) $aggregate->total_value, 2) }}</strong><span>From purchase and receipt records</span></div></article>
        <article class="panel purchase-report-kpi"><span class="purchase-report-kpi-icon purchase-kpi-purple"><svg><use href="#icon-box"></use></svg></span><div><small>Items Purchased</small><strong>{{ number_format((float) $aggregate->item_quantity, 2) }} <small>Units</small></strong><span>Accepted / received quantity</span></div></article>
        <article class="panel purchase-report-kpi"><span class="purchase-report-kpi-icon purchase-kpi-amber">₹</span><div><small>Average Purchase Cost</small><strong>{{ $averageCost === null ? '—' : '₹' . number_format($averageCost, 2) }}</strong><span>Total value ÷ accepted quantity</span></div></article>
    </section>

    @if ($supplierSummary->count())
        <section class="panel purchase-report-supplier-summary">
            <div class="purchase-report-section-heading"><span class="section-kicker">SUPPLIER SPEND</span><h2>Supplier Purchase Summary</h2></div>
            <div class="table-wrap"><table class="data-table purchase-report-table purchase-supplier-table"><thead><tr><th>SUPPLIER</th><th>TRANSACTIONS</th><th>ACCEPTED QTY</th><th>TOTAL PURCHASE VALUE</th><th>LAST PURCHASE</th></tr></thead><tbody>
                @foreach ($supplierSummary as $supplier)
                    <tr><td>{{ $supplier->supplier_name ?: '—' }}</td><td>{{ number_format((int) $supplier->transaction_count) }}</td><td>{{ number_format((float) $supplier->item_quantity, 2) }}</td><td>₹{{ number_format((float) $supplier->total_value, 2) }}</td><td>{{ $supplier->last_purchase_date ? \Carbon\Carbon::parse($supplier->last_purchase_date)->format('d M Y') : '—' }}</td></tr>
                @endforeach
            </tbody></table></div>
        </section>
    @endif

    <section class="panel purchase-report-transactions">
        <div class="purchase-report-table-heading">
            <div><span class="section-kicker">PROCUREMENT RECORDS</span><h2>Purchase Transactions</h2><p>{{ number_format($transactions->total()) }} matching line items</p></div>
            <form class="purchase-report-search" method="GET" action="{{ route('purchase-reports.index') }}">
                @foreach ($filters as $name => $value)
                    @if ($name !== 'search' && $value !== null && $value !== '')<input type="hidden" name="{{ $name }}" value="{{ $value }}">@endif
                @endforeach
                <span aria-hidden="true">⌕</span><input type="search" name="search" value="{{ data_get($filters, 'search') }}" placeholder="Search invoice, product, supplier..." aria-label="Search invoice, product, supplier">
                <button type="submit" aria-label="Search">Search</button>
            </form>
        </div>
        @if ($transactions->count())
            <div class="table-wrap purchase-report-table-wrap">
                <table class="data-table purchase-report-table">
                    <thead><tr><th>#</th><th>DATE</th><th>GRN / INVOICE</th><th>SUPPLIER</th><th>PRODUCT</th><th>CATEGORY</th><th>LOCATION</th><th>QTY RECEIVED</th><th>UNIT COST</th><th>TAX</th><th>TOTAL</th><th>STATUS</th><th>ACTION</th></tr></thead>
                    <tbody>
                        @foreach ($transactions as $transaction)
                            @php
                                $reference = $transaction->grn_number ?: ($transaction->invoice_number ?: $transaction->po_number);
                                $kind = $transaction->source_kind;
                            @endphp
                            <tr>
                                <td>{{ $transactions->firstItem() + $loop->index }}</td>
                                <td>{{ $transaction->purchase_date ? \Carbon\Carbon::parse($transaction->purchase_date)->format('d M Y') : '—' }}</td>
                                <td><strong>{{ $reference ?: '—' }}</strong>@if ($transaction->po_number && $transaction->grn_number)<small class="purchase-report-po-ref">{{ $transaction->po_number }}</small>@endif</td>
                                <td>{{ $transaction->supplier_name ?: '—' }}</td>
                                <td><strong>{{ $transaction->product_name ?: '—' }}</strong><small class="purchase-report-po-ref">{{ $transaction->product_code ?: '—' }}</small></td>
                                <td>{{ $transaction->category_name ?: '—' }}</td>
                                <td>{{ $transaction->location_name ?: '—' }}</td>
                                <td>{{ number_format((float) $transaction->received_quantity, 2) }}<small class="purchase-report-po-ref">Accepted {{ number_format((float) $transaction->accepted_quantity, 2) }}</small></td>
                                <td>₹{{ number_format((float) $transaction->unit_cost, 2) }}</td>
                                <td>{{ (float) $transaction->total_tax > 0 ? '₹' . number_format((float) $transaction->total_tax, 2) : '—' }}</td>
                                <td><strong>₹{{ number_format((float) $transaction->total_value, 2) }}</strong></td>
                                <td><span class="purchase-report-status {{ $statusClass($transaction->status) }}">{{ $transaction->status }}</span></td>
                                <td><a class="button button-small button-light purchase-report-view" href="{{ route('purchase-reports.show', [$kind, $transaction->source_id]) }}">View</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <footer class="workspace-footer"><span>Showing {{ number_format($transactions->firstItem()) }}–{{ number_format($transactions->lastItem()) }} of {{ number_format($transactions->total()) }} line items</span>@if ($transactions->hasPages())<div class="workspace-pagination">{{ $transactions->onEachSide(2)->links() }}</div>@endif</footer>
        @else
            <div class="purchase-report-empty"><span class="workspace-empty-icon"><svg><use href="#icon-tray-in"></use></svg></span><h2>No purchase transactions found.</h2><p>Try changing your filters.</p><a class="button button-light" href="{{ route('purchase-reports.index') }}">Reset Filters</a></div>
        @endif
    </section>
@endsection
