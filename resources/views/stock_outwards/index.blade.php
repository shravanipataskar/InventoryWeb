@extends('layouts.app')

@section('title', 'Stock Outward')
@section('topbar-title', 'Stock outward')

@section('content')
    <div class="page-heading">
        <div><span class="section-kicker">STOCK MOVEMENT</span><h1>Stock Outward</h1><p>Record inventory issued or sold.</p></div>
        <a class="button button-primary" href="{{ route('stock-outwards.create') }}"><span class="button-plus">+</span> Add Stock Outward</a>
    </div>

    <section class="panel listing-panel" data-enhanced-table data-page-size="10">
        <div class="table-toolbar table-toolbar-wrap">
            <label class="search-field search-field-grow"><span class="search-glyph" aria-hidden="true">⌕</span><input type="search" placeholder="Search product, reference or recipient..." aria-label="Search stock outward records" data-table-search></label>
            <label class="date-filter"><span>From</span><input type="date" aria-label="From date" data-date-from></label>
            <label class="date-filter"><span>To</span><input type="date" aria-label="To date" data-date-to></label>
            <button class="button button-light" type="button" data-filter-reset>Reset</button>
        </div>
        <div class="table-wrap">
            <table class="data-table listing-table">
                <thead><tr><th>#</th><th>DATE</th><th>PRODUCT</th><th>REFERENCE</th><th>ISSUED TO</th><th>QUANTITY</th><th>SELLING PRICE</th><th>TOTAL AMOUNT</th></tr></thead>
                <tbody>
                @forelse ($stockOutwards as $stock)
                    <tr data-table-row data-date="{{ \Carbon\Carbon::parse($stock->outward_date)->format('Y-m-d') }}">
                        <td class="muted-cell">{{ $loop->iteration }}</td>
                        <td>{{ \Carbon\Carbon::parse($stock->outward_date)->format('d M Y') }}</td>
                        <td><strong class="table-primary-text">{{ optional($stock->product)->name ?: '—' }}</strong></td>
                        <td><span class="unit-code">{{ $stock->reference_number ?: '—' }}</span></td>
                        <td>{{ $stock->issued_to ?: '—' }}</td>
                        <td class="number-cell quantity-out">−{{ number_format($stock->quantity, 2) }}</td>
                        <td class="currency-cell">₹{{ number_format($stock->selling_price, 2) }}</td>
                        <td><strong class="total-cell">₹{{ number_format($stock->total_amount, 2) }}</strong></td>
                    </tr>
                @empty
                    <tr><td colspan="8">@include('components.empty-state', ['icon' => 'icon-tray-out', 'title' => 'No outward records', 'message' => 'Stock issued or sold will appear here.'])</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="filter-empty" data-filter-empty hidden>No outward records match your search or date range.</div>
        <div class="table-footer"><span class="table-summary" data-table-summary></span><div class="pagination" data-table-pagination></div></div>
    </section>
@endsection
