@extends('layouts.app')

@section('title', 'Inventory Reports')
@section('topbar-title', 'Inventory Reports')

@section('content')
    @php
        $stockTotals = $overview['stockTotals'];
        $totalQuantity = (float) ($stockTotals->quantity_total ?? 0);
        $totalValue = (float) ($stockTotals->value_total ?? 0);
        $maxTrend = max(1, collect($overview['trend'])->map(function ($day) {
            return max(abs($day->balance), $day->quantity_in, $day->quantity_out);
        })->max() ?: 0);
        $totalCategoryValue = max(0, $totalValue);
        $reportTabs = [
            'overview' => 'Overview',
            'stock-movement' => 'Stock Movement',
            'stock-valuation' => 'Stock Valuation',
            'low-reorder' => 'Low / Reorder',
            'purchase-inward' => 'Purchase / Inward',
            'outward' => 'Outward',
            'transfer' => 'Transfer',
            'adjustment' => 'Adjustment',
        ];
    @endphp
    <style>
        .inventory-report{--ir-navy:#10264b;--ir-teal:#079b9c;--ir-blue:#3184ec;--ir-green:#18a879;--ir-amber:#f29c38;--ir-red:#e45c61;--ir-muted:#62748d;--ir-border:#dfe8f1;--ir-bg:#f2f7fb;color:var(--ir-navy)}
        .inventory-report .page-heading{align-items:center;margin-bottom:14px}
        .inventory-report .page-heading h1{margin:2px 0 4px;color:var(--ir-navy);font-size:24px;letter-spacing:-.5px}
        .inventory-report .page-heading p{color:var(--ir-muted);font-size:12px}
        .inventory-report .section-kicker{color:var(--ir-teal);font-size:10px;letter-spacing:.8px}
        .inventory-report .report-actions{display:flex;align-items:center;gap:8px}
        .inventory-report .report-actions .button{min-height:35px;font-size:11px}
        .inventory-report .button-primary{background:var(--ir-teal)}
        .inventory-report .report-filter-card,.inventory-report .report-card{border:1px solid var(--ir-border);border-radius:9px;background:#fff;box-shadow:0 2px 8px rgba(31,62,96,.045)}
        .inventory-report .report-filter-card{margin-bottom:10px;padding:11px 13px}
        .inventory-report .report-filter-heading{display:flex;align-items:center;gap:8px;margin-bottom:9px;font-size:12px;font-weight:700}
        .inventory-report .report-filter-heading svg{width:15px;height:15px;color:var(--ir-blue)}
        .inventory-report .report-filter-grid{display:grid;grid-template-columns:repeat(20,minmax(0,1fr));gap:8px 10px}
        .inventory-report .report-filter-field:nth-child(-n+4){grid-column:span 5}
        .inventory-report .report-filter-field:nth-child(n+5):nth-child(-n+9){grid-column:span 4}
        .inventory-report .report-filter-field{min-width:0}
        .inventory-report .report-filter-field label{display:block;margin-bottom:3px;color:#334b69;font-size:9px;font-weight:600}
        .inventory-report .report-filter-field input,.inventory-report .report-filter-field select,.inventory-report .report-search{width:100%;min-height:30px;padding:5px 7px;border:1px solid #d7e1ed;border-radius:5px;color:#183153;background:#fff;font-size:10px}
        .inventory-report .report-filter-field:focus-within input,.inventory-report .report-filter-field:focus-within select,.inventory-report .report-search:focus{border-color:var(--ir-blue);outline:0;box-shadow:0 0 0 2px rgba(49,132,236,.12)}
        .inventory-report .report-filter-actions{grid-column:1/-1;display:flex;justify-content:flex-end;gap:7px;margin-top:0}
        .inventory-report .report-filter-actions .button{min-height:30px;padding:0 12px;font-size:10px}
        .inventory-report .button-reset{border:1px solid var(--ir-border);color:#425873;background:#fff}
        .inventory-report .report-kpi-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:8px;margin-bottom:10px}
        .inventory-report .report-kpi{display:flex;min-width:0;align-items:center;gap:10px;padding:9px 11px;border:1px solid #e3ebf3;border-radius:8px;background:#fff;box-shadow:0 2px 7px rgba(31,62,96,.035)}
        .inventory-report .report-kpi-icon{display:grid;width:34px;height:34px;flex:0 0 34px;place-items:center;border-radius:8px;font-size:17px;font-weight:700}
        .inventory-report .report-kpi-icon.blue{color:#1672d3;background:#e5f2ff}.inventory-report .report-kpi-icon.green{color:#069c79;background:#e2faf2}.inventory-report .report-kpi-icon.teal{color:#068c8b;background:#e0f7f6}.inventory-report .report-kpi-icon.orange{color:#ea852b;background:#fff1e2}.inventory-report .report-kpi-icon.purple{color:#7959db;background:#f0eaff}.inventory-report .report-kpi-icon.red{color:#dc5159;background:#ffeaec}
        .inventory-report .report-kpi-label{display:block;color:#3e5571;font-size:9px}
        .inventory-report .report-kpi-value{display:block;margin-top:2px;color:var(--ir-navy);font-size:17px;font-weight:750;line-height:1.15;white-space:nowrap}
        .inventory-report .report-kpi-meta{display:block;margin-top:2px;color:#8795a8;font-size:8px}
        .inventory-report .report-tabs{display:flex;gap:0;overflow-x:auto;margin:0 0 10px;border:1px solid var(--ir-border);border-radius:7px;background:#fff}
        .inventory-report .report-tab{flex:0 0 auto;padding:9px 12px;border-right:1px solid #e8eef4;color:#425873;font-size:10px;font-weight:600;text-decoration:none;white-space:nowrap}
        .inventory-report .report-tab:last-child{border-right:0}
        .inventory-report .report-tab:hover{color:var(--ir-teal);background:#f7fbfd}
        .inventory-report .report-tab.is-active{color:#fff;background:var(--ir-teal)}
        .inventory-report .report-overview-grid{display:grid;grid-template-columns:minmax(0,1.05fr) minmax(0,.95fr);gap:9px;margin-bottom:9px}
        .inventory-report .report-overview-grid.bottom{grid-template-columns:minmax(0,1fr) minmax(0,1fr)}
        .inventory-report .report-card{min-width:0;padding:11px 12px}
        .inventory-report .report-card-heading{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:8px}
        .inventory-report .report-card-heading h2{margin:0;color:var(--ir-navy);font-size:12px;font-weight:700}
        .inventory-report .report-card-heading p{margin:3px 0 0;color:#8492a6;font-size:9px}
        .inventory-report .report-link{color:var(--ir-teal);font-size:9px;font-weight:600;text-decoration:none;white-space:nowrap}
        .inventory-report .report-table-wrap{overflow-x:auto}
        .inventory-report .report-table{width:100%;border-collapse:collapse;text-align:left}
        .inventory-report .report-table th{padding:6px 7px;border-bottom:1px solid #e4ebf2;color:#61738a;background:#f7fafc;font-size:8px;font-weight:700;white-space:nowrap}
        .inventory-report .report-table td{padding:6px 7px;border-bottom:1px solid #edf1f5;color:#314965;font-size:9px;vertical-align:middle}
        .inventory-report .report-table tbody tr:last-child td{border-bottom:0}
        .inventory-report .report-table td small{display:block;margin-top:2px;color:#8997a9;font-size:8px}
        .inventory-report .report-table .numeric{text-align:right;font-variant-numeric:tabular-nums;white-space:nowrap}
        .inventory-report .report-table .currency{white-space:nowrap;font-weight:600}
        .inventory-report .report-empty{padding:19px 10px;color:#8391a4;font-size:10px;text-align:center}
        .inventory-report .report-badge{display:inline-block;padding:3px 6px;border-radius:10px;color:#0d946e;background:#e4f8f0;font-size:8px;font-weight:700;white-space:nowrap}
        .inventory-report .report-badge.warning{color:#bb741b;background:#fff3dc}
        .inventory-report .report-badge.danger{color:#c4424b;background:#ffeaec}
        .inventory-report .report-progress{height:4px;overflow:hidden;margin-top:4px;border-radius:4px;background:#edf2f7}
        .inventory-report .report-progress span{display:block;height:100%;border-radius:4px;background:linear-gradient(90deg,#19c69a,#38a8ed)}
        .inventory-report .report-chart{display:flex;min-height:116px;align-items:flex-end;gap:7px;overflow-x:auto;padding:7px 2px 0;border-bottom:1px solid #e5edf4}
        .inventory-report .report-chart-day{display:flex;min-width:24px;height:100px;flex:1;flex-direction:column;align-items:center;justify-content:flex-end;gap:3px}
        .inventory-report .report-chart-bar{display:flex;width:100%;max-width:24px;height:78px;flex-direction:row;align-items:flex-end;justify-content:center;gap:1px}
        .inventory-report .report-chart-in,.inventory-report .report-chart-out,.inventory-report .report-chart-balance{display:block;min-width:3px;min-height:1px;flex:1;border-radius:2px 2px 0 0}
        .inventory-report .report-chart-in{background:#25d3bd}.inventory-report .report-chart-out{background:#ffae48}.inventory-report .report-chart-balance{background:#328af1}
        .inventory-report .report-chart-date{color:#8492a6;font-size:7px;white-space:nowrap}
        .inventory-report .report-chart-legend{display:flex;gap:11px;margin-top:7px;color:#61738a;font-size:8px}
        .inventory-report .report-chart-legend span:before{display:inline-block;width:6px;height:6px;margin-right:4px;border-radius:50%;background:#328af1;content:""}
        .inventory-report .report-chart-legend .legend-in:before{background:#25d3bd}.inventory-report .report-chart-legend .legend-out:before{background:#ffae48}
        .inventory-report .report-search-row{display:flex;justify-content:space-between;gap:10px;margin-bottom:9px}
        .inventory-report .report-search{max-width:260px}
        .inventory-report .report-pager{margin-top:10px}
        .inventory-report .report-note{margin-top:5px;color:#8896a8;font-size:8px}
        .inventory-report .report-create-po{display:inline-block;padding:4px 7px;border:1px solid #a8dbdf;border-radius:5px;color:#087e84;background:#f3ffff;font-size:8px;font-weight:600;text-decoration:none;white-space:nowrap}
        .inventory-report .report-export{position:relative}
        .inventory-report .report-export summary{display:flex;min-height:35px;align-items:center;gap:7px;padding:0 12px;border-radius:6px;color:#fff;background:#079b9c;font-size:11px;font-weight:600;cursor:pointer;list-style:none}
        .inventory-report .report-export summary::-webkit-details-marker{display:none}
        .inventory-report .report-export-menu{position:absolute;z-index:20;top:40px;right:0;width:225px;padding:8px;border:1px solid var(--ir-border);border-radius:8px;background:#fff;box-shadow:0 8px 22px rgba(19,43,71,.15)}
        .inventory-report .report-export-menu label{display:block;padding:4px 5px;color:#64758a;font-size:9px;font-weight:600}
        .inventory-report .report-export-menu select{width:100%;min-height:30px;padding:5px;border:1px solid var(--ir-border);border-radius:5px;color:#29415e;background:#fff;font-size:9px}
        .inventory-report .report-export-menu .export-form-actions{display:flex;gap:5px;margin-top:7px}
        .inventory-report .report-export-menu button{flex:1;min-height:29px;padding:5px;border:0;border-radius:4px;color:#fff;background:var(--ir-teal);font-size:9px;text-align:center;cursor:pointer}
        .inventory-report .report-export-menu button:hover{background:#087f83}
        .inventory-report .report-export-menu .export-print{width:100%;margin-top:5px;border:1px solid var(--ir-border);color:#29415e;background:#fff}
        .inventory-report .print-only{display:none}
        @media(max-width:1050px){.inventory-report .report-filter-grid{grid-template-columns:repeat(10,minmax(0,1fr))}.inventory-report .report-filter-field:nth-child(-n+4){grid-column:span 2}.inventory-report .report-filter-field:nth-child(n+5):nth-child(-n+9){grid-column:span 2}.inventory-report .report-overview-grid,.inventory-report .report-overview-grid.bottom{grid-template-columns:1fr 1fr}}
        @media(max-width:720px){.inventory-report .page-heading{align-items:flex-start;flex-direction:column;gap:9px}.inventory-report .report-kpi-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.inventory-report .report-overview-grid,.inventory-report .report-overview-grid.bottom{grid-template-columns:1fr}.inventory-report .report-filter-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.inventory-report .report-filter-field:nth-child(n){grid-column:span 1}.inventory-report .report-filter-actions{grid-column:1/-1}}
        @media(max-width:430px){.inventory-report .report-filter-grid{grid-template-columns:1fr}.inventory-report .report-filter-field:nth-child(n){grid-column:auto}.inventory-report .report-actions{width:100%}.inventory-report .report-actions>*{flex:1}.inventory-report .report-kpi{gap:7px;padding:8px}.inventory-report .report-kpi-icon{width:29px;height:29px;flex-basis:29px}.inventory-report .report-kpi-value{font-size:14px}.inventory-report .report-tabs{margin-right:-4px}}
        @media print{.inventory-report .page-heading,.inventory-report .report-filter-card,.inventory-report .report-tabs,.inventory-report .report-actions,.inventory-report .report-link,.inventory-report .report-pager,.inventory-report .report-create-po{display:none!important}.inventory-report .report-card,.inventory-report .report-kpi{box-shadow:none;break-inside:avoid}.inventory-report .report-overview-grid,.inventory-report .report-overview-grid.bottom{grid-template-columns:1fr 1fr}.inventory-report .report-table-wrap{overflow:visible}.inventory-report .report-table{font-size:8px}}
    </style>

    <main class="inventory-report">
        <div class="page-heading workspace-page-heading">
            <div><span class="section-kicker">ANALYTICS</span><h1>Inventory Reports</h1><p>Track stock, valuation, locations, movements and inventory health.</p></div>
            <div class="report-actions">
                <a class="button button-light" href="{{ route('current-stock.index') }}">View Current Stock</a>
                <details class="report-export">
                    <summary><span aria-hidden="true">⇩</span> Export <span aria-hidden="true">⌄</span></summary>
                    <form class="report-export-menu" method="GET" action="{{ route('reports.download') }}">
                        @foreach (request()->except(['page', 'type', 'format']) as $key => $value)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endforeach
                        <label for="report-export-type">Export data</label>
                        <select id="report-export-type" name="type">
                            <option value="current-report">Current Report</option>
                            <option value="stock-details">Stock Details</option>
                            <option value="stock-movement">Stock Movement</option>
                            <option value="reorder">Reorder Report</option>
                            <option value="stock-valuation">Stock Valuation</option>
                        </select>
                        <div class="export-form-actions"><button type="submit" name="format" value="excel">Excel</button><button type="submit" name="format" value="csv">CSV</button></div>
                        <button class="export-print" type="button" onclick="window.print()">Print current report</button>
                    </form>
                </details>
            </div>
        </div>

        <section class="report-filter-card" aria-labelledby="report-filter-title">
            <div class="report-filter-heading" id="report-filter-title"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 5h16l-6 7v6l-4 2v-8L4 5z"/></svg>Report Filters</div>
            <form method="GET" action="{{ route('reports.index') }}" id="inventory-report-filters">
                <input type="hidden" name="tab" value="{{ $tab }}">
                <div class="report-filter-grid">
                    <div class="report-filter-field"><label for="date_from">Date From</label><input id="date_from" name="date_from" type="date" value="{{ $filters['date_from'] }}"></div>
                    <div class="report-filter-field"><label for="date_to">Date To</label><input id="date_to" name="date_to" type="date" value="{{ $filters['date_to'] }}"></div>
                    <div class="report-filter-field"><label for="as_of_date">As of Date</label><input id="as_of_date" name="as_of_date" type="date" value="{{ $filters['as_of_date'] }}"></div>
                    <div class="report-filter-field"><label for="location_id">Location / Store</label><select id="location_id" name="location_id"><option value="">All Locations</option>@foreach ($options['locations'] as $location)<option value="{{ $location->id }}" {{ $filters['location_id'] == $location->id ? 'selected' : '' }}>{{ $location->name }}</option>@endforeach</select></div>
                    <div class="report-filter-field"><label for="category_id">Category</label><select id="category_id" name="category_id"><option value="">All Categories</option>@foreach ($options['categories'] as $category)<option value="{{ $category->id }}" {{ $filters['category_id'] == $category->id ? 'selected' : '' }}>{{ $category->name }}</option>@endforeach</select></div>
                    <div class="report-filter-field"><label for="company_id">Company / Brand</label><select id="company_id" name="company_id"><option value="">All Companies</option>@foreach ($options['companies'] as $company)<option value="{{ $company->id }}" {{ $filters['company_id'] == $company->id ? 'selected' : '' }}>{{ $company->name }}</option>@endforeach</select></div>
                    <div class="report-filter-field"><label for="product_id">Product</label><select id="product_id" name="product_id"><option value="">All Products</option>@foreach ($options['products'] as $product)<option value="{{ $product->id }}" data-category="{{ $product->category_id }}" data-company="{{ $product->company_id }}" {{ $filters['product_id'] == $product->id ? 'selected' : '' }}>{{ $product->name }} ({{ $product->product_code }})</option>@endforeach</select></div>
                    <div class="report-filter-field"><label for="stock_status">Stock Status</label><select id="stock_status" name="stock_status"><option value="all" {{ $filters['stock_status'] === 'all' ? 'selected' : '' }}>All Statuses</option><option value="healthy" {{ $filters['stock_status'] === 'healthy' ? 'selected' : '' }}>Healthy</option><option value="low" {{ $filters['stock_status'] === 'low' ? 'selected' : '' }}>Low Stock</option><option value="out" {{ $filters['stock_status'] === 'out' ? 'selected' : '' }}>Out of Stock</option></select></div>
                    <div class="report-filter-field"><label for="movement_type">Movement Type</label><select id="movement_type" name="movement_type"><option value="">All Types</option>@foreach (['opening' => 'Opening', 'purchase_in' => 'Purchase / Inward', 'sales_out' => 'Outward', 'issue_out' => 'Issue Out', 'transfer_in' => 'Transfer In', 'transfer_out' => 'Transfer Out', 'adjustment_in' => 'Adjustment In', 'adjustment_out' => 'Adjustment Out'] as $movementValue => $movementLabel)<option value="{{ $movementValue }}" {{ $filters['movement_type'] === $movementValue ? 'selected' : '' }}>{{ $movementLabel }}</option>@endforeach</select></div>
                    <div class="report-filter-actions"><button class="button button-primary" type="submit">Apply Filters</button><a class="button button-reset" href="{{ route('reports.index', ['tab' => $tab]) }}">Reset</a></div>
                </div>
            </form>
        </section>

        <section class="report-kpi-grid" aria-label="Inventory summary">
            <article class="report-kpi"><span class="report-kpi-icon blue">□</span><div><span class="report-kpi-label">Total Stock</span><strong class="report-kpi-value">{{ number_format($totalQuantity, 2) }}</strong><span class="report-kpi-meta">Units on hand as of {{ \Illuminate\Support\Carbon::parse($filters['as_of_date'])->format('d M Y') }}</span></div></article>
            <article class="report-kpi"><span class="report-kpi-icon green">₹</span><div><span class="report-kpi-label">Current Stock Value</span><strong class="report-kpi-value">₹{{ number_format($totalValue, 2) }}</strong><span class="report-kpi-meta">On-hand quantity × purchase cost</span></div></article>
            <article class="report-kpi"><span class="report-kpi-icon blue">↓</span><div><span class="report-kpi-label">Stock Received</span><strong class="report-kpi-value">{{ number_format((float) $overview['received'], 2) }}</strong><span class="report-kpi-meta">Active inward quantity in selected dates</span></div></article>
            <article class="report-kpi"><span class="report-kpi-icon orange">↑</span><div><span class="report-kpi-label">Stock Issued</span><strong class="report-kpi-value">{{ number_format((float) $overview['issued'], 2) }}</strong><span class="report-kpi-meta">Active outward quantity in selected dates</span></div></article>
            <article class="report-kpi"><span class="report-kpi-icon teal">◇</span><div><span class="report-kpi-label">Available Stock</span><strong class="report-kpi-value">{{ number_format($totalQuantity, 2) }}</strong><span class="report-kpi-meta">No reservation records are configured</span></div></article>
            <article class="report-kpi"><span class="report-kpi-icon purple">▣</span><div><span class="report-kpi-label">Reserved Stock</span><strong class="report-kpi-value">0.00</strong><span class="report-kpi-meta">No supported reservation workflow</span></div></article>
            <article class="report-kpi"><span class="report-kpi-icon orange">!</span><div><span class="report-kpi-label">Low Stock Products</span><strong class="report-kpi-value">{{ number_format((int) ($stockTotals->low_count ?? 0)) }}</strong><span class="report-kpi-meta">At or below configured reorder level</span></div></article>
            <article class="report-kpi"><span class="report-kpi-icon red">⊘</span><div><span class="report-kpi-label">Out of Stock Products</span><strong class="report-kpi-value">{{ number_format((int) ($stockTotals->out_count ?? 0)) }}</strong><span class="report-kpi-meta">On-hand quantity is zero or below</span></div></article>
        </section>

        <nav class="report-tabs" aria-label="Inventory report sections">
            @foreach ($reportTabs as $tabKey => $tabLabel)
                <a class="report-tab {{ $tab === $tabKey ? 'is-active' : '' }}" href="{{ route('reports.index', array_merge(request()->except(['page', 'tab']), ['tab' => $tabKey])) }}">{{ $tabLabel }}</a>
            @endforeach
        </nav>

        @if ($tab === 'overview')
            <div class="report-overview-grid">
                <section class="report-card">
                    <div class="report-card-heading"><div><h2>Stock Trend</h2><p>Daily inflow, outflow and closing on-hand balance</p></div><span class="report-badge">Selected Period</span></div>
                    @if (count($overview['trend']))
                        <div class="report-chart" role="img" aria-label="Daily stock trend based on selected filters">
                            @foreach ($overview['trend'] as $day)
                                <div class="report-chart-day" title="{{ $day->date }}: {{ number_format($day->quantity_in, 2) }} in, {{ number_format($day->quantity_out, 2) }} out, {{ number_format($day->balance, 2) }} balance">
                                    <div class="report-chart-bar">
                                        <i class="report-chart-balance" style="height:{{ min(100, max(1, 78 * $day->balance / $maxTrend)) }}%"></i>
                                        <i class="report-chart-in" style="height:{{ min(100, max(1, 78 * $day->quantity_in / $maxTrend)) }}%"></i>
                                        <i class="report-chart-out" style="height:{{ min(100, max(1, 78 * $day->quantity_out / $maxTrend)) }}%"></i>
                                    </div>
                                    <span class="report-chart-date">{{ \Illuminate\Support\Carbon::parse($day->date)->format('d M') }}</span>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="report-empty">No inventory data found. Try changing your filters.</div>
                    @endif
                    <div class="report-chart-legend"><span>On-hand Stock</span><span class="legend-in">Stock In</span><span class="legend-out">Stock Out</span></div>
                </section>
                <section class="report-card">
                    <div class="report-card-heading"><div><h2>Stock Value by Category</h2><p>Inventory cost at the selected as-of date</p></div><a class="report-link" href="{{ route('reports.index', array_merge(request()->except(['page', 'tab']), ['tab' => 'stock-valuation'])) }}">View All</a></div>
                    <div class="report-table-wrap"><table class="report-table"><thead><tr><th>Category</th><th class="numeric">Products</th><th class="numeric">Quantity</th><th class="numeric">Stock Value</th></tr></thead><tbody>
                        @forelse ($overview['categoryRows'] as $categoryRow)
                            @php($categoryPercent = $totalCategoryValue > 0 ? min(100, ($categoryRow->value_total / $totalCategoryValue) * 100) : 0)
                            <tr><td>{{ $categoryRow->name ?: 'Uncategorized' }}<div class="report-progress"><span style="width:{{ number_format($categoryPercent, 2, '.', '') }}%"></span></div></td><td class="numeric">{{ number_format($categoryRow->products_count) }}</td><td class="numeric">{{ number_format($categoryRow->quantity_total, 2) }}</td><td class="numeric currency">₹{{ number_format($categoryRow->value_total, 2) }}<small>{{ number_format($categoryPercent, 1) }}% of total value</small></td></tr>
                        @empty<tr><td colspan="4" class="report-empty">No inventory data found. Try changing your filters.</td></tr>@endforelse
                    </tbody></table></div>
                </section>
            </div>

            <div class="report-overview-grid bottom">
                <section class="report-card">
                    <div class="report-card-heading"><div><h2>Stock by Location</h2><p>Location-ledger balance through the selected as-of date</p></div><a class="report-link" href="{{ route('current-stock.index') }}">View Current Stock</a></div>
                    <div class="report-table-wrap"><table class="report-table"><thead><tr><th>Location</th><th class="numeric">Products</th><th class="numeric">Quantity</th><th class="numeric">Stock Value</th></tr></thead><tbody>
                        @forelse ($overview['locationRows'] as $locationRow)
                            <tr><td><a href="{{ route('reports.index', array_merge(request()->except(['page', 'tab', 'location_id']), ['tab' => 'stock-valuation', 'location_id' => $locationRow->id])) }}">{{ $locationRow->name }}</a></td><td class="numeric">{{ number_format($locationRow->products_count) }}</td><td class="numeric">{{ number_format($locationRow->quantity_total, 2) }}</td><td class="numeric currency">₹{{ number_format($locationRow->value_total, 2) }}</td></tr>
                        @empty<tr><td colspan="4" class="report-empty">No location-tagged inventory ledger entries match these filters.</td></tr>@endforelse
                    </tbody></table></div>
                    <p class="report-note">Location totals are based on store-tagged stock transactions; legacy inward/outward entries without a store remain unassigned.</p>
                </section>
                <section class="report-card">
                    <div class="report-card-heading"><div><h2>Low / Reorder Products</h2><p>Products at or below the configured reorder level</p></div><a class="report-link" href="{{ route('reports.index', array_merge(request()->except(['page', 'tab']), ['tab' => 'low-reorder'])) }}">View All</a></div>
                    <div class="report-table-wrap"><table class="report-table"><thead><tr><th>Product</th><th>SKU</th><th>On Hand</th><th>Reorder</th><th>Status / Action</th></tr></thead><tbody>
                        @forelse ($overview['reorderRows'] as $productRow)
                        @php($productReorderLevel = (float) $productRow->reorder_level > 0 ? $productRow->reorder_level : $productRow->minimum_stock)
                        @php($productReorderQty = (float) $productRow->reorder_quantity > 0 ? $productRow->reorder_quantity : max(0, (float) $productReorderLevel - (float) $productRow->report_quantity))
                            <tr><td>{{ $productRow->name }}</td><td>{{ $productRow->product_code }}</td><td>{{ number_format($productRow->report_quantity, 2) }}</td><td>{{ number_format($productReorderQty, 2) }}</td><td><span class="report-badge {{ $productRow->report_quantity <= 0 ? 'danger' : 'warning' }}">{{ $productRow->report_quantity <= 0 ? 'Out of Stock' : 'Low Stock' }}</span><small><a class="report-create-po" href="{{ route('purchase-orders.create', array_filter(['product_id' => $productRow->id, 'ordered_quantity' => $productReorderQty, 'store_id' => $filters['location_id'] ?: null])) }}">Create PO</a></small></td></tr>
                        @empty<tr><td colspan="5" class="report-empty">No products need reordering.</td></tr>@endforelse
                    </tbody></table></div>
                </section>
            </div>

            <section class="report-card">
                <div class="report-card-heading"><div><h2>Recent Stock Movement</h2><p>Latest matching stock ledger and inward/outward activity</p></div><a class="report-link" href="{{ route('reports.index', array_merge(request()->except(['page', 'tab']), ['tab' => 'stock-movement'])) }}">View All</a></div>
                <div class="report-table-wrap"><table class="report-table"><thead><tr><th>Date</th><th>Type</th><th>Reference</th><th>Product</th><th>Location</th><th class="numeric">In</th><th class="numeric">Out</th><th class="numeric">Balance</th></tr></thead><tbody>
                    @forelse ($overview['recentMovements'] as $movement)
                        <tr><td>{{ \Illuminate\Support\Carbon::parse($movement->movement_date)->format('d M Y') }}</td><td>{{ ucwords(str_replace('_', ' ', $movement->movement_type)) }}</td><td>{{ $movement->reference_number ?: '—' }}</td><td>{{ $movement->product_name }}<small>{{ $movement->product_code }}</small></td><td>{{ $movement->location_name ?: 'Unassigned' }}</td><td class="numeric">{{ $movement->quantity_in > 0 ? number_format($movement->quantity_in, 2) : '—' }}</td><td class="numeric">{{ $movement->quantity_out > 0 ? number_format($movement->quantity_out, 2) : '—' }}</td><td class="numeric">{{ $movement->balance_quantity !== null ? number_format($movement->balance_quantity, 2) : '—' }}</td></tr>
                    @empty<tr><td colspan="8" class="report-empty">No stock movements found. Try changing your filters.</td></tr>@endforelse
                </tbody></table></div>
            </section>
        @else
            <section class="report-card">
                <div class="report-card-heading"><div><h2>{{ $reportTabs[$tab] }}</h2><p>Detailed inventory records for the selected filters.</p></div></div>
                <div class="report-search-row">
                    <form method="GET" action="{{ route('reports.index') }}">
                        @foreach (request()->except(['search', 'page']) as $key => $value)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endforeach
                        <input class="report-search" type="search" name="search" value="{{ $filters['search'] }}" placeholder="Search product, SKU, barcode, reference or location">
                        <button class="button button-light" type="submit">Search</button>
                    </form>
                    <button class="button button-light" type="button" onclick="window.print()">Print</button>
                </div>
                <div class="report-table-wrap">
                    @if ($tab === 'stock-movement')
                        <table class="report-table"><thead><tr><th>Date</th><th>Type</th><th>Reference</th><th>Product / SKU</th><th>Category</th><th>Company</th><th>Location</th><th>Hall / Rack / Shelf</th><th class="numeric">In</th><th class="numeric">Out</th><th class="numeric">Balance</th><th class="numeric">Unit Cost</th><th>Reason</th><th>Created By</th></tr></thead><tbody>
                            @forelse ($rows as $row)<tr><td>{{ \Illuminate\Support\Carbon::parse($row->movement_date)->format('d M Y') }}</td><td>{{ ucwords(str_replace('_', ' ', $row->movement_type)) }}</td><td>{{ $row->reference_number ?: '—' }}</td><td>{{ $row->product_name }}<small>{{ $row->product_code }}</small></td><td>{{ $row->category_name ?: '—' }}</td><td>{{ $row->company_name ?: '—' }}</td><td>{{ $row->location_name ?: 'Unassigned' }}</td><td>{{ collect([$row->hall_name, $row->rack_name, $row->shelf_name])->filter()->implode(' / ') ?: '—' }}</td><td class="numeric">{{ $row->quantity_in ? number_format($row->quantity_in, 2) : '—' }}</td><td class="numeric">{{ $row->quantity_out ? number_format($row->quantity_out, 2) : '—' }}</td><td class="numeric">{{ $row->balance_quantity !== null ? number_format($row->balance_quantity, 2) : '—' }}</td><td class="numeric">₹{{ number_format($row->unit_cost, 2) }}</td><td>{{ $row->remarks ?: '—' }}</td><td>{{ $row->created_by_name ?: '—' }}</td></tr>
                            @empty<tr><td colspan="14" class="report-empty">No stock movements found. Try changing your filters.</td></tr>@endforelse
                        </tbody></table>
                    @elseif ($tab === 'stock-valuation')
                        <table class="report-table"><thead><tr><th>Product</th><th>SKU</th><th>Category</th><th>Company</th><th>Location</th><th>Hall / Rack / Shelf</th><th class="numeric">Quantity</th><th>Unit</th><th class="numeric">Unit Cost</th><th class="numeric">Stock Value</th><th>Status</th></tr></thead><tbody>
                            @forelse ($rows as $row)<tr><td>{{ $row->name }}</td><td>{{ $row->product_code }}</td><td>{{ $row->category_name ?: '—' }}</td><td>{{ $row->company_name ?: '—' }}</td><td>{{ $row->location_name ?: 'All / unallocated' }}</td><td>{{ collect([$row->hall_name, $row->rack_name, $row->shelf_name])->filter()->implode(' / ') ?: '—' }}</td><td class="numeric">{{ number_format($row->report_quantity, 2) }}</td><td>{{ $row->unit_name ?: '—' }}</td><td class="numeric">₹{{ number_format($row->purchase_price, 2) }}</td><td class="numeric currency">₹{{ number_format($row->report_quantity * $row->purchase_price, 2) }}</td><td><span class="report-badge {{ $row->report_quantity <= 0 ? 'danger' : ($row->report_quantity <= ($row->reorder_level ?: $row->minimum_stock) ? 'warning' : '') }}">{{ app(\App\Services\InventoryReportsService::class)->stockStatus($row) }}</span></td></tr>
                            @empty<tr><td colspan="11" class="report-empty">No inventory data found. Try changing your filters.</td></tr>@endforelse
                        </tbody></table>
                    @elseif ($tab === 'low-reorder')
                        <table class="report-table"><thead><tr><th>Product</th><th>SKU</th><th>Category</th><th>Company</th><th>Location</th><th class="numeric">On Hand</th><th class="numeric">Reserved</th><th class="numeric">Available</th><th class="numeric">Minimum</th><th class="numeric">Reorder Level</th><th class="numeric">Reorder Qty</th><th class="numeric">Unit Cost</th><th class="numeric">Estimated Value</th><th>Status / Action</th></tr></thead><tbody>
                            @forelse ($rows as $row)@php($rowReorderLevel = (float) $row->reorder_level > 0 ? $row->reorder_level : $row->minimum_stock)@php($reorderQty = (float) $row->reorder_quantity > 0 ? $row->reorder_quantity : max(0, (float) $rowReorderLevel - (float) $row->report_quantity))<tr><td>{{ $row->name }}</td><td>{{ $row->product_code }}</td><td>{{ $row->category_name ?: '—' }}</td><td>{{ $row->company_name ?: '—' }}</td><td>{{ $row->location_name ?: 'All / unallocated' }}</td><td class="numeric">{{ number_format($row->report_quantity, 2) }}</td><td class="numeric">0.00</td><td class="numeric">{{ number_format($row->report_quantity, 2) }}</td><td class="numeric">{{ number_format($row->minimum_stock, 2) }}</td><td class="numeric">{{ number_format($row->reorder_level, 2) }}</td><td class="numeric">{{ number_format($reorderQty, 2) }}</td><td class="numeric">₹{{ number_format($row->purchase_price, 2) }}</td><td class="numeric">₹{{ number_format($reorderQty * $row->purchase_price, 2) }}</td><td><span class="report-badge {{ $row->report_quantity <= 0 ? 'danger' : 'warning' }}">{{ $row->report_quantity <= 0 ? 'Out of Stock' : 'Low Stock' }}</span><small><a class="report-create-po" href="{{ route('purchase-orders.create', array_filter(['product_id' => $row->id, 'ordered_quantity' => $reorderQty, 'store_id' => $filters['location_id'] ?: null])) }}">Create PO</a></small></td></tr>@empty<tr><td colspan="14" class="report-empty">No products need reordering.</td></tr>@endforelse
                        </tbody></table>
                    @elseif ($tab === 'purchase-inward')
                        <table class="report-table"><thead><tr><th>GRN / Inward No.</th><th>Date</th><th>Supplier</th><th>Product</th><th>Location</th><th class="numeric">Quantity</th><th class="numeric">Received</th><th class="numeric">Rejected</th><th class="numeric">Unit Cost</th><th class="numeric">Purchase Value</th><th>Status</th></tr></thead><tbody>
                            @forelse ($rows as $row)<tr><td>{{ $row->inward_number ?: $row->invoice_number ?: '—' }}</td><td>{{ \Illuminate\Support\Carbon::parse($row->inward_date)->format('d M Y') }}</td><td>{{ $row->supplier_name ?: '—' }}</td><td>{{ $row->product_name }}<small>{{ $row->product_code }}</small></td><td>{{ $row->location_name ?: 'Unassigned' }}</td><td class="numeric">{{ number_format($row->quantity, 2) }}</td><td class="numeric">{{ number_format($row->received_quantity ?: $row->quantity, 2) }}</td><td class="numeric">{{ number_format($row->rejected_quantity ?: 0, 2) }}</td><td class="numeric">₹{{ number_format($row->purchase_price, 2) }}</td><td class="numeric">₹{{ number_format($row->total_amount, 2) }}</td><td><span class="report-badge">{{ ucfirst($row->status ?: 'posted') }}</span></td></tr>@empty<tr><td colspan="11" class="report-empty">No inward records found. Try changing your filters.</td></tr>@endforelse
                        </tbody></table>
                    @elseif ($tab === 'outward')
                        <table class="report-table"><thead><tr><th>Outward Number</th><th>Date</th><th>Customer / Department</th><th>Product</th><th>Location</th><th class="numeric">Quantity</th><th class="numeric">Unit Cost</th><th class="numeric">Stock Value</th><th>Reference</th><th>Status</th></tr></thead><tbody>
                            @forelse ($rows as $row)<tr><td>{{ $row->reference_number ?: '—' }}</td><td>{{ \Illuminate\Support\Carbon::parse($row->outward_date)->format('d M Y') }}</td><td>{{ $row->issued_to ?: '—' }}</td><td>{{ $row->product_name }}<small>{{ $row->product_code }}</small></td><td>{{ $row->location_name ?: 'Not recorded' }}</td><td class="numeric">{{ number_format($row->quantity, 2) }}</td><td class="numeric">₹{{ number_format($row->product_cost, 2) }}</td><td class="numeric">₹{{ number_format($row->quantity * $row->product_cost, 2) }}</td><td>{{ $row->reference_number ?: '—' }}</td><td><span class="report-badge">Active</span></td></tr>@empty<tr><td colspan="10" class="report-empty">No outward records found. Try changing your filters.</td></tr>@endforelse
                        </tbody></table>
                    @elseif ($tab === 'transfer')
                        <table class="report-table"><thead><tr><th>Transfer Number</th><th>Date</th><th>From</th><th>To</th><th>Product</th><th class="numeric">Quantity</th><th class="numeric">Unit Cost</th><th class="numeric">Transfer Value</th><th>Status</th><th>Requested By</th></tr></thead><tbody>
                            @forelse ($rows as $row)<tr><td>{{ $row->transfer_number }}</td><td>{{ \Illuminate\Support\Carbon::parse($row->transfer_date)->format('d M Y') }}</td><td>{{ $row->source_name ?: '—' }}</td><td>{{ $row->destination_name ?: '—' }}</td><td>{{ $row->product_name }}<small>{{ $row->product_code }}</small></td><td class="numeric">{{ number_format($row->quantity_out, 2) }}</td><td class="numeric">₹{{ number_format($row->unit_price, 2) }}</td><td class="numeric">₹{{ number_format($row->quantity_out * $row->unit_price, 2) }}</td><td><span class="report-badge">{{ ucfirst($row->status) }}</span></td><td>{{ $row->requested_by_name ?: '—' }}</td></tr>@empty<tr><td colspan="10" class="report-empty">No transfers found. Try changing your filters.</td></tr>@endforelse
                        </tbody></table>
                    @elseif ($tab === 'adjustment')
                        <table class="report-table"><thead><tr><th>Date</th><th>Adjustment Reference</th><th>Product</th><th>Location</th><th>Type</th><th class="numeric">Before / Current Balance</th><th class="numeric">Quantity</th><th class="numeric">Unit Cost</th><th>Reason</th><th>Created By</th></tr></thead><tbody>
                            @forelse ($rows as $row)<tr><td>{{ \Illuminate\Support\Carbon::parse($row->transaction_date)->format('d M Y') }}</td><td>{{ $row->reference_number ?: '—' }}</td><td>{{ $row->product_name }}<small>{{ $row->product_code }}</small></td><td>{{ $row->location_name ?: '—' }}</td><td>{{ ucwords(str_replace('_', ' ', $row->transaction_type)) }}</td><td class="numeric">{{ number_format($row->balance_quantity, 2) }}</td><td class="numeric">{{ number_format($row->quantity_in ?: $row->quantity_out, 2) }}</td><td class="numeric">₹{{ number_format($row->purchase_price, 2) }}</td><td>{{ $row->remarks ?: '—' }}</td><td>{{ $row->created_by_name ?: '—' }}</td></tr>@empty<tr><td colspan="10" class="report-empty">No adjustments found. Try changing your filters.</td></tr>@endforelse
                        </tbody></table>
                    @endif
                </div>
                @if ($rows && method_exists($rows, 'links'))
                    <div class="report-pager">{{ $rows->links() }}</div>
                @endif
            </section>
        @endif
    </main>
    <script>
        (function () {
            var category = document.getElementById('category_id');
            var company = document.getElementById('company_id');
            var product = document.getElementById('product_id');
            var options = Array.prototype.slice.call(product.options).slice(1).map(function (option) {
                return option.cloneNode(true);
            });
            function filterProducts() {
                var selected = product.value;
                product.innerHTML = '<option value="">All Products</option>';
                options.forEach(function (option) {
                    var categoryMatches = !category.value || option.dataset.category === category.value;
                    var companyMatches = !company.value || option.dataset.company === company.value;
                    if (categoryMatches && companyMatches) {
                        product.appendChild(option.cloneNode(true));
                    }
                });
                product.value = Array.prototype.some.call(product.options, function (option) { return option.value === selected; }) ? selected : '';
            }
            category.addEventListener('change', filterProducts);
            company.addEventListener('change', filterProducts);
            filterProducts();
        }());
    </script>
@endsection
