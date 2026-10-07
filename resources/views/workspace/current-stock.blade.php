@extends('layouts.app')

@section('title', 'Current Stock')
@section('topbar-title', 'Current Stock')

@section('content')
    <div class="page-heading workspace-page-heading">
        <div><span class="section-kicker">INVENTORY</span><h1>Current Stock</h1><p>Live on-hand quantities and purchase value for active products.</p></div>
        <a class="button button-primary" href="{{ route('stock-inwards.create') }}"><span class="button-plus">+</span> Record Stock Inward</a>
    </div>

    <section class="workspace-summary-grid workspace-summary-five">
        <article class="workspace-summary-card"><span class="workspace-summary-icon workspace-blue"><svg><use href="#icon-box"></use></svg></span><div><small>Active Products</small><strong>{{ number_format($summary['products']) }}</strong></div></article>
        <article class="workspace-summary-card"><span class="workspace-summary-icon workspace-green"><svg><use href="#icon-tray-in"></use></svg></span><div><small>Units on Hand</small><strong>{{ number_format($summary['quantity'], 2) }}</strong></div></article>
        <article class="workspace-summary-card"><span class="workspace-summary-icon workspace-purple"><svg><use href="#icon-arrow-up"></use></svg></span><div><small>Stock Value</small><strong>₹{{ number_format($summary['value'], 2) }}</strong></div></article>
        <article class="workspace-summary-card"><span class="workspace-summary-icon workspace-orange"><svg><use href="#icon-alert"></use></svg></span><div><small>Low Stock</small><strong>{{ number_format($summary['low_stock']) }}</strong></div></article>
        <article class="workspace-summary-card"><span class="workspace-summary-icon workspace-red"><svg><use href="#icon-tray-out"></use></svg></span><div><small>Out of Stock</small><strong>{{ number_format($summary['out_of_stock']) }}</strong></div></article>
    </section>

    <section class="panel workspace-panel">
        <form class="workspace-toolbar" method="GET" action="{{ route('current-stock.index') }}">
            <label class="search-field workspace-search"><span class="search-glyph" aria-hidden="true">⌕</span><input type="search" name="search" value="{{ request('search') }}" placeholder="Search product, code or barcode..." aria-label="Search current stock"></label>
            <select class="filter-select" name="stock" aria-label="Filter stock level"><option value="">All stock levels</option><option value="available" {{ request('stock') === 'available' ? 'selected' : '' }}>Available</option><option value="low" {{ request('stock') === 'low' ? 'selected' : '' }}>Low stock</option><option value="out" {{ request('stock') === 'out' ? 'selected' : '' }}>Out of stock</option></select>
            <button class="button button-primary" type="submit">Apply</button>
            @if (request()->hasAny(['search', 'stock']))<a class="button button-light" href="{{ route('current-stock.index') }}">Reset</a>@endif
        </form>
        @if ($products->count())
            <div class="table-wrap">
                <table class="data-table workspace-table">
                    <thead><tr><th>PRODUCT</th><th>COMPANY</th><th>CATEGORY</th><th>LOCATION</th><th>ON HAND</th><th>MINIMUM</th><th>VALUE</th><th>STATUS</th></tr></thead>
                    <tbody>
                    @foreach ($products as $product)
                        @php($stockLabel = $product->current_stock <= 0 ? 'Out of stock' : ($product->current_stock <= $product->minimum_stock ? 'Low stock' : 'Available'))
                        <tr><td><div class="product-cell"><span class="product-avatar">{{ strtoupper(substr($product->name, 0, 1)) }}</span><span><strong>{{ $product->name }}</strong><small>{{ $product->product_code }}</small></span></div></td><td>{{ optional($product->company)->name ?: '—' }}</td><td>{{ optional($product->category)->name ?: '—' }}</td><td>{{ optional($product->hallLocation)->name ?: '—' }} / {{ optional($product->rackLocation)->name ?: '—' }} / {{ optional($product->shelfLocation)->name ?: '—' }}</td><td class="number-cell">{{ number_format($product->current_stock, 2) }} <span>{{ optional($product->unit)->short_name }}</span></td><td class="number-cell">{{ number_format($product->minimum_stock, 2) }}</td><td class="currency-cell">₹{{ number_format($product->current_stock * $product->purchase_price, 2) }}</td><td>@include('components.status-badge', ['status' => $stockLabel])</td></tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <footer class="workspace-footer"><span>Showing {{ number_format($products->firstItem()) }}–{{ number_format($products->lastItem()) }} of {{ number_format($products->total()) }} products</span>@if ($products->hasPages())<div class="workspace-pagination">{{ $products->onEachSide(2)->links() }}</div>@endif</footer>
        @else
            <div class="workspace-empty"><span class="workspace-empty-icon"><svg><use href="#icon-box"></use></svg></span><h2>No stock records found</h2><p>Add products or adjust your filters to see on-hand inventory.</p><a class="button button-primary" href="{{ route('products.create') }}">Add Product</a></div>
        @endif
    </section>
@endsection
