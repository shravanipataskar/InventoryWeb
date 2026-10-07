@extends('layouts.app')

@section('title', 'Products')
@section('topbar-title', 'Products')

@section('content')
    <div class="page-heading">
        <div><span class="section-kicker">CATALOGUE</span><h1>Products</h1><p>Manage inventory products and monitor stock levels.</p></div>
        <a class="button button-primary" href="{{ route('products.create') }}"><span class="button-plus">+</span> Add Product</a>
    </div>

    <section class="panel listing-panel" data-enhanced-table data-page-size="10">
        <div class="table-toolbar table-toolbar-wrap">
            <label class="search-field search-field-grow"><span class="search-glyph" aria-hidden="true">⌕</span><input type="search" placeholder="Search by name, SKU or barcode..." aria-label="Search products" data-table-search></label>
            <select class="filter-select" aria-label="Filter by category" data-filter-key="category"><option value="">All categories</option>@foreach ($products->pluck('category.name', 'category_id')->filter()->unique() as $categoryId => $categoryName)<option value="{{ $categoryId }}">{{ $categoryName }}</option>@endforeach</select>
            <select class="filter-select" aria-label="Filter by unit" data-filter-key="unit"><option value="">All units</option>@foreach ($units as $unit)<option value="{{ $unit->id }}">{{ $unit->name }}</option>@endforeach</select>
            <select class="filter-select" aria-label="Filter by stock status" data-filter-key="stock-status"><option value="">All stock levels</option><option value="in-stock">In stock</option><option value="low-stock">Low stock</option><option value="out-of-stock">Out of stock</option></select>
            <div class="status-tabs" aria-label="Product status">
                <a class="status-tab {{ $listingStatus === 'active' ? 'is-selected' : '' }}" href="{{ route('products.index', ['status' => 'active']) }}" {{ $listingStatus === 'active' ? 'aria-current=page' : '' }}>Active</a>
                <a class="status-tab {{ $listingStatus === 'inactive' ? 'is-selected' : '' }}" href="{{ route('products.index', ['status' => 'inactive']) }}" {{ $listingStatus === 'inactive' ? 'aria-current=page' : '' }}>Inactive</a>
            </div>
        </div>
        <div class="table-wrap">
            <table class="data-table listing-table product-list-table">
                <thead><tr><th>#</th><th>IMAGE</th><th>PRODUCT</th><th>HALL</th><th>RACK</th><th>SHELL</th><th>CATEGORY</th><th>UNIT</th><th>PURCHASE</th><th>SELLING</th><th>CURRENT</th><th>MINIMUM</th><th>STOCK STATUS</th><th>STATUS</th><th>ACTIONS</th></tr></thead>
                <tbody>
                @forelse ($products as $product)
                    @php
                        $stockStatus = $product->current_stock <= 0 ? 'out-of-stock' : ($product->current_stock <= $product->minimum_stock ? 'low-stock' : 'in-stock');
                        $stockLabel = $stockStatus === 'out-of-stock' ? 'Out of stock' : ($stockStatus === 'low-stock' ? 'Low stock' : 'In stock');
                    @endphp
                    <tr data-table-row data-category="{{ $product->category_id }}" data-unit="{{ $product->unit_id }}" data-stock-status="{{ $stockStatus }}">
                        <td class="muted-cell">{{ $loop->iteration }}</td>
                        <td>
                            @if ($product->image && Storage::disk('public')->exists($product->image))
                                <img class="product-thumbnail" src="{{ asset('storage/' . $product->image) }}" alt="{{ $product->name }}">
                            @else
                                <span class="product-thumbnail-placeholder" aria-label="No product image">{{ strtoupper(substr($product->name, 0, 1)) }}</span>
                            @endif
                        </td>
                        <td><div class="product-cell"><span class="product-avatar">{{ strtoupper(substr($product->name, 0, 1)) }}</span><span><strong>{{ $product->name }}</strong><small>{{ $product->product_code }}@if ($product->barcode) · {{ $product->barcode }}@endif</small></span></div></td>
                        <td><span class="unit-code">{{ optional($product->hall)->name ?: '—' }}</span></td>
                        <td><span class="unit-code">{{ optional($product->rack)->name ?: '—' }}</span></td>
                        <td><span class="unit-code">{{ optional($product->shelf)->name ?: '—' }}</span></td>
                        <td>{{ optional($product->category)->name ?: '—' }}</td>
                        <td>{{ optional($product->unit)->short_name ?: '—' }}</td>
                        <td class="currency-cell">₹{{ number_format($product->purchase_price, 2) }}</td>
                        <td class="currency-cell">₹{{ number_format($product->selling_price, 2) }}</td>
                        <td class="number-cell">{{ number_format($product->current_stock, 2) }}</td>
                        <td class="number-cell">{{ number_format($product->minimum_stock, 2) }}</td>
                        <td>@include('components.status-badge', ['status' => $stockLabel])</td>
                        <td>@include('components.status-badge', ['status' => $product->is_active ? 'Active' : 'Inactive'])</td>
                        <td class="action-cell">
                            <a class="button button-small button-light" href="{{ route('products.edit', $product->id) }}">Edit</a>
                            <form class="status-action-form" action="{{ route('products.status', $product->id) }}" method="POST">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="is_active" value="{{ $product->is_active ? 0 : 1 }}">
                                <input type="hidden" name="listing_status" value="{{ $listingStatus }}">
                                <button class="button button-small {{ $product->is_active ? 'button-deactivate' : 'button-activate' }}" type="submit">{{ $product->is_active ? 'Inactive' : 'Active' }}</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="15">@include('components.empty-state', ['icon' => 'icon-box', 'title' => 'No products yet', 'message' => 'Add your first product to start managing inventory.'])</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="filter-empty" data-filter-empty hidden>No {{ $listingStatus }} products match your search and filters.</div>
        <div class="table-footer"><span class="table-summary" data-table-summary></span><div class="pagination" data-table-pagination></div></div>
    </section>
@endsection
