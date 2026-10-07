@extends('layouts.app')

@section('title', 'Reports')
@section('topbar-title', 'Reports')

@section('content')
    <div class="page-heading workspace-page-heading">
        <div><span class="section-kicker">ANALYTICS</span><h1>Inventory Reports</h1><p>Live summaries calculated from current products and stock movement records.</p></div>
        <div class="workspace-heading-actions">
            <a class="button button-light" href="{{ route('current-stock.index') }}">View Current Stock</a>
            <a class="button button-primary" href="{{ route('reports.download') }}">Download CSV</a>
        </div>
    </div>

    <section class="workspace-summary-grid">
        <article class="workspace-summary-card"><span class="workspace-summary-icon workspace-blue"><svg><use href="#icon-tray-in"></use></svg></span><div><small>Stock Received</small><strong>{{ number_format($movement['inward_quantity'], 2) }}</strong><small>₹{{ number_format($movement['inward_value'], 2) }} purchase value</small></div></article>
        <article class="workspace-summary-card"><span class="workspace-summary-icon workspace-orange"><svg><use href="#icon-tray-out"></use></svg></span><div><small>Stock Issued</small><strong>{{ number_format($movement['outward_quantity'], 2) }}</strong><small>₹{{ number_format($movement['outward_value'], 2) }} sale value</small></div></article>
        <article class="workspace-summary-card"><span class="workspace-summary-icon workspace-green"><svg><use href="#icon-box"></use></svg></span><div><small>On-hand Quantity</small><strong>{{ number_format($stock['quantity'], 2) }}</strong></div></article>
        <article class="workspace-summary-card"><span class="workspace-summary-icon workspace-purple"><svg><use href="#icon-arrow-up"></use></svg></span><div><small>Current Stock Value</small><strong>₹{{ number_format($stock['value'], 2) }}</strong></div></article>
        <article class="workspace-summary-card"><span class="workspace-summary-icon workspace-red"><svg><use href="#icon-alert"></use></svg></span><div><small>Low / Out of Stock</small><strong>{{ number_format($stock['low_stock']) }} / {{ number_format($stock['out_of_stock']) }}</strong></div></article>
    </section>

    <div class="workspace-report-grid">
        <section class="panel workspace-panel">
            <div class="panel-heading"><div><h2>Stock Value by Category</h2><p>Live value at product purchase price</p></div><a class="text-link" href="{{ route('categories.index') }}">Categories →</a></div>
            @if ($categoryReport->count())
                <div class="table-wrap"><table class="data-table workspace-table"><thead><tr><th>CATEGORY</th><th>PRODUCTS</th><th>QUANTITY</th><th>STOCK VALUE</th></tr></thead><tbody>@foreach ($categoryReport as $category)<tr><td>{{ $category->name }}</td><td>{{ number_format($category->products_count) }}</td><td>{{ number_format($category->quantity_total, 2) }}</td><td class="currency-cell">₹{{ number_format($category->value_total, 2) }}</td></tr>@endforeach</tbody></table></div>
            @else
                <div class="workspace-empty"><p>No categories available for reporting.</p></div>
            @endif
        </section>

        <section class="panel workspace-panel">
            <div class="panel-heading"><div><h2>Stock Value by Company</h2><p>Assigned company inventory</p></div><a class="text-link" href="{{ route('companies.index') }}">Companies →</a></div>
            @if ($companyReport->count())
                <div class="table-wrap"><table class="data-table workspace-table"><thead><tr><th>COMPANY</th><th>CODE</th><th>PRODUCTS</th><th>STOCK VALUE</th></tr></thead><tbody>@foreach ($companyReport as $company)<tr><td><a href="{{ route('companies.show', $company->id) }}">{{ $company->name }}</a></td><td>{{ $company->code }}</td><td>{{ number_format($company->products_count) }}</td><td class="currency-cell">₹{{ number_format($company->value_total, 2) }}</td></tr>@endforeach</tbody></table></div>
            @else
                <div class="workspace-empty"><p>No companies available for reporting.</p></div>
            @endif
        </section>
    </div>
@endsection
