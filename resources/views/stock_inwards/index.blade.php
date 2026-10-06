@extends('layouts.app')

@section('title', 'Stock Inward')
@section('topbar-title', 'Stock inward')

@section('content')
    <div class="page-heading">
        <div><span class="section-kicker">STOCK MOVEMENT</span><h1>Stock Inward</h1><p>Record goods received from suppliers.</p></div>
        <a class="button button-primary" href="{{ route('stock-inwards.create') }}"><span class="button-plus">+</span> Add Stock Inward</a>
    </div>

    <section class="panel listing-panel" data-enhanced-table data-page-size="10">
        <div class="table-toolbar table-toolbar-wrap">
            <label class="search-field search-field-grow"><span class="search-glyph" aria-hidden="true">⌕</span><input type="search" placeholder="Search product, supplier or invoice..." aria-label="Search stock inward records" data-table-search></label>
            <label class="date-filter"><span>From</span><input type="date" aria-label="From date" data-date-from></label>
            <label class="date-filter"><span>To</span><input type="date" aria-label="To date" data-date-to></label>
            <button class="button button-light" type="button" data-filter-reset>Reset</button>
        </div>
        <div class="table-wrap">
            <table class="data-table listing-table">
                <thead><tr><th>#</th><th>DATE</th><th>PRODUCT</th><th>SUPPLIER</th><th>INVOICE</th><th>QUANTITY</th><th>PURCHASE PRICE</th><th>TOTAL AMOUNT</th></tr></thead>
                <tbody>
                @forelse ($stockInwards as $stock)
                    <tr data-table-row data-date="{{ \Carbon\Carbon::parse($stock->inward_date)->format('Y-m-d') }}">
                        <td class="muted-cell">{{ $loop->iteration }}</td>
                        <td>{{ \Carbon\Carbon::parse($stock->inward_date)->format('d M Y') }}</td>
                        <td><strong class="table-primary-text">{{ optional($stock->product)->name ?: '—' }}</strong></td>
                        <td>{{ optional($stock->supplier)->name ?: '—' }}</td>
                        <td><span class="unit-code">{{ $stock->invoice_number ?: '—' }}</span></td>
                        <td class="number-cell quantity-in">+{{ number_format($stock->quantity, 2) }}</td>
                        <td class="currency-cell">₹{{ number_format($stock->purchase_price, 2) }}</td>
                        <td><strong class="total-cell">₹{{ number_format($stock->total_amount, 2) }}</strong></td>
                    </tr>
                @empty
                    <tr><td colspan="8">@include('components.empty-state', ['icon' => 'icon-tray-in', 'title' => 'No inward records', 'message' => 'Stock received from suppliers will appear here.'])</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="filter-empty" data-filter-empty hidden>No inward records match your search or date range.</div>
        <div class="table-footer"><span class="table-summary" data-table-summary></span><div class="pagination" data-table-pagination></div></div>
    </section>
@endsection
