@extends('layouts.app')

@section('title', 'Stock Valuation')
@section('topbar-title', 'Stock Valuation')

@section('content')
    @php
        $exportQuery = array_filter($filters, function ($value) { return $value !== null && $value !== ''; });
        $totalValue = (float) $aggregate->total_value;
    @endphp
    <div class="page-heading valuation-heading">
        <div><span class="section-kicker">REPORTS / INVENTORY VALUE</span><h1>Stock Valuation</h1><p>Current inventory quantities valued at each product purchase price.</p></div>
        <details class="valuation-export-menu">
            <summary class="button button-primary"><svg><use href="#icon-tray-out"></use></svg> Export <span aria-hidden="true">⌄</span></summary>
            <div class="valuation-export-options">
                <strong>Stock Valuation</strong>
                @foreach (['valuation' => 'Stock Valuation', 'details' => 'Detailed Valuation', 'category' => 'Category Valuation', 'location' => 'Location Valuation'] as $type => $label)
                    <a href="{{ route('stock-valuation.export', array_merge(['format' => 'excel', 'report_type' => $type], $exportQuery)) }}">{{ $label }} · Excel</a>
                    <a href="{{ route('stock-valuation.export', array_merge(['format' => 'csv', 'report_type' => $type], $exportQuery)) }}">{{ $label }} · CSV</a>
                @endforeach
                <a href="{{ route('stock-valuation.export', array_merge(['format' => 'print'], $exportQuery)) }}" target="_blank" rel="noopener">Print / Save as PDF</a>
            </div>
        </details>
    </div>

    <section class="panel valuation-filters">
        <div class="purchase-report-section-heading"><span class="section-kicker">FILTER DATA</span><h2>Report Filters</h2></div>
        <form method="GET" action="{{ route('stock-valuation.index') }}" class="valuation-filter-grid">
            <label class="field"><span>As of Date</span><input class="field-control" type="date" name="as_of" value="{{ $filters['as_of'] }}" max="{{ now()->toDateString() }}"></label>
            <label class="field"><span>Assigned Location / Hall</span><select class="field-control" name="location_id"><option value="">All Assigned Locations</option>@foreach ($locations as $location)<option value="{{ $location->id }}" {{ (string) $filters['location_id'] === (string) $location->id ? 'selected' : '' }}>{{ $location->name }}</option>@endforeach</select></label>
            <label class="field"><span>Category</span><select class="field-control" name="category_id"><option value="">All Categories</option>@foreach ($categories as $category)<option value="{{ $category->id }}" {{ (string) $filters['category_id'] === (string) $category->id ? 'selected' : '' }}>{{ $category->name }}</option>@endforeach</select></label>
            <label class="field"><span>Company / Brand</span><select class="field-control" name="company_id"><option value="">All Companies</option>@foreach ($companies as $company)<option value="{{ $company->id }}" {{ (string) $filters['company_id'] === (string) $company->id ? 'selected' : '' }}>{{ $company->name }}</option>@endforeach</select></label>
            <label class="field"><span>Product</span><select class="field-control" name="product_id"><option value="">All Products</option>@foreach ($productOptions as $product)<option value="{{ $product->id }}" {{ (string) $filters['product_id'] === (string) $product->id ? 'selected' : '' }}>{{ $product->name }} ({{ $product->product_code }})</option>@endforeach</select></label>
            <label class="field"><span>Stock Status</span><select class="field-control" name="stock_status"><option value="">All Statuses</option><option value="in_stock" {{ $filters['stock_status'] === 'in_stock' ? 'selected' : '' }}>In Stock</option><option value="low_stock" {{ $filters['stock_status'] === 'low_stock' ? 'selected' : '' }}>Low Stock</option><option value="out_of_stock" {{ $filters['stock_status'] === 'out_of_stock' ? 'selected' : '' }}>Out of Stock</option></select></label>
            <label class="field"><span>Valuation Method</span><select class="field-control" disabled><option>Purchase Cost</option></select></label>
            <label class="field"><span>Include Zero Stock</span><select class="field-control" name="include_zero"><option value="0" {{ $filters['include_zero'] !== '1' ? 'selected' : '' }}>No</option><option value="1" {{ $filters['include_zero'] === '1' ? 'selected' : '' }}>Yes</option></select></label>
            <label class="field valuation-filter-search"><span>Search</span><input class="field-control" type="search" name="search" value="{{ $filters['search'] }}" placeholder="Product, SKU, barcode..."></label>
            <div class="valuation-filter-actions"><button class="button button-primary" type="submit">Apply Filters</button><a class="button button-light" href="{{ route('stock-valuation.index') }}">Reset</a></div>
        </form>
        <p class="valuation-data-note">Historical quantities cannot be calculated reliably: legacy outward and adjustment records are not all represented in the stock ledger. Select today for a current valuation. Location filtering uses each product’s assigned hall; inventory quantities are maintained at product level, not by store. Purchase Cost is the only valuation method currently recorded.</p>
    </section>

    <section class="purchase-report-kpis valuation-kpis" aria-label="Stock valuation summary">
        <article class="panel purchase-report-kpi"><span class="purchase-report-kpi-icon purchase-kpi-blue"><svg><use href="#icon-box"></use></svg></span><div><small>Products Valued</small><strong>{{ number_format((int) $aggregate->product_count) }}</strong><span>Active products in this report</span></div></article>
        <article class="panel purchase-report-kpi"><span class="purchase-report-kpi-icon purchase-kpi-green">₹</span><div><small>Total Stock Value</small><strong>₹{{ number_format($totalValue, 2) }}</strong><span>Quantity × purchase cost</span></div></article>
        <article class="panel purchase-report-kpi"><span class="purchase-report-kpi-icon purchase-kpi-purple"><svg><use href="#icon-chart"></use></svg></span><div><small>Total Quantity</small><strong>{{ number_format((float) $aggregate->total_quantity, 2) }} <small>Units</small></strong><span>On-hand product quantity</span></div></article>
        <article class="panel purchase-report-kpi"><span class="purchase-report-kpi-icon purchase-kpi-amber">₹</span><div><small>Average Unit Cost</small><strong>{{ $averageUnitCost === null ? '—' : '₹' . number_format($averageUnitCost, 2) }}</strong><span>Total value ÷ total quantity</span></div></article>
    </section>

    <section class="panel valuation-products">
        <div class="purchase-report-table-heading valuation-table-heading">
            <div><span class="section-kicker">INVENTORY VALUE</span><h2>Product Stock Valuation</h2><p>{{ number_format($products->total()) }} matching products · Purchase Cost</p></div>
            <div class="valuation-table-tools">
                <form method="GET" action="{{ route('stock-valuation.index') }}" class="purchase-report-search">
                    @foreach ($filters as $key => $value)
                        @if ($key !== 'search' && $value !== null && $value !== '')<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endif
                    @endforeach
                    <span aria-hidden="true">⌕</span><input type="search" name="search" value="{{ $filters['search'] }}" placeholder="Search product, SKU, barcode..." aria-label="Search stock valuation">
                    <button type="submit">Search</button>
                </form>
                <details class="valuation-columns-menu"><summary class="button button-light">Columns⌄</summary><div>
                    @foreach (['company' => 'Company', 'location' => 'Location', 'unit' => 'Unit', 'last-movement' => 'Last Movement'] as $column => $label)
                        <label><input type="checkbox" checked data-valuation-column="{{ $column }}"> {{ $label }}</label>
                    @endforeach
                </div></details>
            </div>
        </div>
        @if ($products->count())
            <div class="table-wrap purchase-report-table-wrap">
                <table class="data-table purchase-report-table valuation-table">
                    <thead><tr><th>#</th><th>PRODUCT</th><th>SKU / CODE</th><th>CATEGORY</th><th data-column="company">COMPANY</th><th data-column="location">LOCATION</th><th>QUANTITY</th><th data-column="unit">UNIT</th><th>UNIT COST</th><th>STOCK VALUE</th><th>STATUS</th><th data-column="last-movement">LAST MOVEMENT</th><th>ACTION</th></tr></thead>
                    <tbody>
                    @foreach ($products as $product)
                        @php
                            $statusClass = $product->stock_status === 'In Stock' ? 'valuation-status-in' : ($product->stock_status === 'Low Stock' ? 'valuation-status-low' : 'valuation-status-out');
                            $productLocation = implode(' / ', array_filter([$product->hall_name, $product->rack_name, $product->shelf_name]));
                        @endphp
                        <tr>
                            <td>{{ $products->firstItem() + $loop->index }}</td>
                            <td><strong>{{ $product->product_name ?: '—' }}</strong></td>
                            <td>{{ $product->product_code ?: '—' }}<small class="purchase-report-po-ref">{{ $product->barcode ?: '—' }}</small></td>
                            <td>{{ $product->category_name ?: '—' }}</td>
                            <td data-column="company">{{ $product->company_name ?: '—' }}</td>
                            <td data-column="location">{{ $productLocation ?: '—' }}</td>
                            <td>{{ number_format((float) $product->quantity, 2) }}</td>
                            <td data-column="unit">{{ $product->unit_short_name ?: ($product->unit_name ?: '—') }}</td>
                            <td>₹{{ number_format((float) $product->unit_cost, 2) }}</td>
                            <td><strong>₹{{ number_format((float) $product->stock_value, 2) }}</strong></td>
                            <td><span class="valuation-status {{ $statusClass }}">{{ $product->stock_status }}</span></td>
                            <td data-column="last-movement">{{ $product->last_movement_date ? \Carbon\Carbon::parse($product->last_movement_date)->format('d M Y') : '—' }}</td>
                            <td><a class="button button-small button-light purchase-report-view" href="{{ route('stock-valuation.show', $product->id) }}">View</a></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <footer class="workspace-footer"><span>Showing {{ number_format($products->firstItem()) }}–{{ number_format($products->lastItem()) }} of {{ number_format($products->total()) }} products</span>@if ($products->hasPages())<div class="workspace-pagination">{{ $products->onEachSide(2)->links() }}</div>@endif</footer>
        @else
            <div class="purchase-report-empty"><span class="workspace-empty-icon"><svg><use href="#icon-box"></use></svg></span><h2>No stock valuation data found.</h2><p>Try changing your filters.</p><a class="button button-light" href="{{ route('stock-valuation.index') }}">Reset Filters</a></div>
        @endif
    </section>

    <div class="valuation-summary-grid">
        <section class="panel valuation-summary-card">
            <div class="purchase-report-section-heading"><span class="section-kicker">VALUE BREAKDOWN</span><h2>Stock Value by Category</h2></div>
            @if ($categorySummary->count())
                <div class="table-wrap"><table class="data-table valuation-summary-table"><thead><tr><th>CATEGORY</th><th>PRODUCTS</th><th>QUANTITY</th><th>STOCK VALUE</th><th>%</th></tr></thead><tbody>
                    @foreach ($categorySummary as $row)
                        @php($percent = $totalValue > 0 ? (float) $row->total_value / $totalValue * 100 : 0)
                        <tr><td>{{ $row->category_name ?: '—' }}</td><td>{{ number_format((int) $row->product_count) }}</td><td>{{ number_format((float) $row->total_quantity, 2) }}</td><td>₹{{ number_format((float) $row->total_value, 2) }}</td><td><span class="valuation-percent">{{ number_format($percent, 1) }}%</span><span class="valuation-bar"><i style="width: {{ min(100, max(0, $percent)) }}%"></i></span></td></tr>
                    @endforeach
                </tbody></table></div>
            @else
                <p class="valuation-summary-empty">No category valuation data for these filters.</p>
            @endif
        </section>
        <section class="panel valuation-summary-card">
            <div class="purchase-report-section-heading"><span class="section-kicker">VALUE BREAKDOWN</span><h2>Stock Value by Location</h2></div>
            @if ($locationSummary->count())
                <div class="table-wrap"><table class="data-table valuation-summary-table"><thead><tr><th>ASSIGNED LOCATION</th><th>PRODUCTS</th><th>QUANTITY</th><th>STOCK VALUE</th><th>%</th></tr></thead><tbody>
                    @foreach ($locationSummary as $row)
                        @php($percent = $totalValue > 0 ? (float) $row->total_value / $totalValue * 100 : 0)
                        <tr><td>{{ $row->hall_name ?: '—' }}</td><td>{{ number_format((int) $row->product_count) }}</td><td>{{ number_format((float) $row->total_quantity, 2) }}</td><td>₹{{ number_format((float) $row->total_value, 2) }}</td><td><span class="valuation-percent">{{ number_format($percent, 1) }}%</span><span class="valuation-bar"><i style="width: {{ min(100, max(0, $percent)) }}%"></i></span></td></tr>
                    @endforeach
                </tbody></table></div>
            @else
                <p class="valuation-summary-empty">No assigned location valuation data for these filters.</p>
            @endif
        </section>
    </div>

    <script>
        document.querySelectorAll('[data-valuation-column]').forEach(function (toggle) {
            toggle.addEventListener('change', function () {
                var column = toggle.getAttribute('data-valuation-column');
                document.querySelectorAll('[data-column="' + column + '"]').forEach(function (cell) {
                    cell.hidden = !toggle.checked;
                });
            });
        });
    </script>
@endsection
