@extends('layouts.app')

@section('title', 'Valuation Details')
@section('topbar-title', 'Valuation Details')

@section('content')
    @php
        $location = implode(' / ', array_filter([$product->hall_name, $product->rack_name, $product->shelf_name]));
    @endphp
    <div class="page-heading valuation-heading">
        <div><span class="section-kicker">REPORTS / STOCK VALUATION</span><h1>Valuation Details</h1><p>Read-only current valuation at the product purchase cost.</p></div>
        <a class="button button-light" href="{{ route('stock-valuation.index') }}">Back to Stock Valuation</a>
    </div>
    <section class="panel purchase-report-detail-card valuation-detail-card">
        <div class="purchase-report-section-heading"><span class="section-kicker">PRODUCT</span><h2>{{ $product->product_name ?: '—' }}</h2></div>
        <div class="purchase-report-detail-grid">
            <div><span>SKU / Code</span><strong>{{ $product->product_code ?: '—' }}</strong></div>
            <div><span>Barcode</span><strong>{{ $product->barcode ?: '—' }}</strong></div>
            <div><span>Category</span><strong>{{ $product->category_name ?: '—' }}</strong></div>
            <div><span>Company / Brand</span><strong>{{ $product->company_name ?: '—' }}</strong></div>
            <div><span>Assigned Location</span><strong>{{ $location ?: '—' }}</strong></div>
            <div><span>Quantity</span><strong>{{ number_format((float) $product->quantity, 2) }} {{ $product->unit_short_name ?: ($product->unit_name ?: '') }}</strong></div>
            <div><span>Unit Cost</span><strong>₹{{ number_format((float) $product->unit_cost, 2) }}</strong><small>Purchase Cost</small></div>
            <div><span>Stock Value</span><strong>₹{{ number_format((float) $product->stock_value, 2) }}</strong></div>
            <div><span>Status</span><strong>{{ $product->stock_status }}</strong></div>
            <div><span>Last Movement</span><strong>{{ $product->last_movement_date ? \Carbon\Carbon::parse($product->last_movement_date)->format('d M Y') : '—' }}</strong></div>
            <div><span>Valuation Method</span><strong>Purchase Cost</strong></div>
            <div><span>Batch / Serial / Expiry</span><strong>—</strong><small>Not included in current valuation records</small></div>
        </div>
        <div class="valuation-detail-formula"><strong>Valuation</strong><span>{{ number_format((float) $product->quantity, 2) }} × ₹{{ number_format((float) $product->unit_cost, 2) }} = <b>₹{{ number_format((float) $product->stock_value, 2) }}</b></span></div>
    </section>
@endsection
