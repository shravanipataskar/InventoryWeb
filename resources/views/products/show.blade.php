@extends('layouts.app')

@section('title', $product->name . ' · Product')
@section('topbar-title', 'Product details')

@section('content')
    @php
        $stockStatus = $product->current_stock <= 0 ? 'Out of stock' : ($product->current_stock <= $product->minimum_stock ? 'Low stock' : 'In stock');
    @endphp
    <div class="page-heading">
        <div><span class="section-kicker">CATALOGUE / PRODUCTS</span><h1>{{ $product->name }}</h1><p>Read-only product profile and inventory information.</p></div>
        <a class="button button-light" href="{{ route('products.index') }}">Back to Products</a>
    </div>
    <section class="panel workspace-panel product-details-panel">
        <div class="form-section">
            <div class="form-card-heading"><span class="form-section-icon"><svg><use href="#icon-box"></use></svg></span><div><h2>Product Information</h2><p>Product identity, image, category, and storage location.</p></div></div>
            <div class="product-details-hero">
                @if ($product->image && Storage::disk('public')->exists($product->image))
                    <img class="product-details-image" src="{{ asset('storage/' . $product->image) }}" alt="{{ $product->name }}">
                @else
                    <span class="product-details-image-placeholder" aria-label="No product image">{{ strtoupper(substr($product->name, 0, 1)) }}</span>
                @endif
                <div class="form-grid">
                    <div class="field"><label>Product Name</label><div class="product-identifier-display">{{ $product->name }}</div></div>
                    <div class="field"><label>Product Code / SKU</label><div class="product-identifier-display">{{ $product->product_code ?: '—' }}</div></div>
                    <div class="field"><label>Barcode</label><div class="product-identifier-display">{{ $product->barcode ?: '—' }}</div></div>
                    <div class="field"><label>Category</label><div class="product-identifier-display">{{ optional($product->category)->name ?: '—' }}</div></div>
                    <div class="field"><label>Unit</label><div class="product-identifier-display">{{ optional($product->unit)->name ?: '—' }}</div></div>
                    <div class="field"><label>Hall</label><div class="product-identifier-display">{{ optional($product->hall)->name ?: '—' }}</div></div>
                    <div class="field"><label>Rack</label><div class="product-identifier-display">{{ optional($product->rack)->name ?: '—' }}</div></div>
                    <div class="field"><label>Shelf</label><div class="product-identifier-display">{{ optional($product->shelf)->name ?: '—' }}</div></div>
                </div>
            </div>
        </div>
        <div class="form-section">
            <div class="form-card-heading"><span class="form-section-icon form-icon-gold"><svg><use href="#icon-chart"></use></svg></span><div><h2>Pricing and Stock</h2><p>Current prices, stock levels, and replenishment status.</p></div></div>
            <div class="form-grid">
                <div class="field"><label>Purchase Price</label><div class="product-identifier-display">₹{{ number_format((float) $product->purchase_price, 2) }}</div></div>
                <div class="field"><label>Selling Price</label><div class="product-identifier-display">₹{{ number_format((float) $product->selling_price, 2) }}</div></div>
                <div class="field"><label>Current Stock</label><div class="product-identifier-display">{{ number_format((float) $product->current_stock, 2) }}</div></div>
                <div class="field"><label>Minimum Stock</label><div class="product-identifier-display">{{ number_format((float) $product->minimum_stock, 2) }}</div></div>
                <div class="field"><label>Stock Status</label><div class="product-identifier-display">{{ $stockStatus }}</div></div>
                <div class="field"><label>Product Status</label><div class="product-identifier-display">{{ $product->is_active ? 'Active' : 'Inactive' }}</div></div>
            </div>
        </div>
        <div class="form-section">
            <div class="form-grid">
                <div class="field"><label>Created Date</label><div class="product-identifier-display">{{ optional($product->created_at)->format('d M Y, h:i A') ?: '—' }}</div></div>
                <div class="field"><label>Updated Date</label><div class="product-identifier-display">{{ optional($product->updated_at)->format('d M Y, h:i A') ?: '—' }}</div></div>
            </div>
        </div>
    </section>
    <style>
        .product-details-hero{display:grid;grid-template-columns:180px minmax(0,1fr);gap:22px;align-items:start}
        .product-details-image,.product-details-image-placeholder{width:180px;height:180px;border:1px solid #e7ecef;border-radius:14px;object-fit:cover}
        .product-details-image-placeholder{display:grid;place-items:center;background:#e7f5f2;color:#0d898a;font-size:48px;font-weight:700}
        @media(max-width:700px){.product-details-hero{grid-template-columns:1fr}.product-details-image,.product-details-image-placeholder{width:140px;height:140px}}
    </style>
@endsection
