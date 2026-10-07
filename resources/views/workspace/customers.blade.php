@extends('layouts.app')

@section('title', 'Customers')
@section('topbar-title', 'Customers')

@section('content')
    <div class="page-heading workspace-page-heading">
        <div><span class="section-kicker">RELATIONSHIPS</span><h1>Customers</h1><p>Customers are gathered from the recipients recorded on stock outward transactions.</p></div>
        <a class="button button-primary" href="{{ route('stock-outwards.create') }}"><span class="button-plus">+</span> Record Stock Outward</a>
    </div>

    <section class="workspace-summary-grid">
        <article class="workspace-summary-card"><span class="workspace-summary-icon workspace-blue"><svg><use href="#icon-users"></use></svg></span><div><small>Customers recorded</small><strong>{{ number_format($customerCount) }}</strong></div></article>
        <article class="workspace-summary-card"><span class="workspace-summary-icon workspace-green"><svg><use href="#icon-tray-out"></use></svg></span><div><small>Sales value to named customers</small><strong>₹{{ number_format($customerSummary->sales_total ?? 0, 2) }}</strong></div></article>
    </section>

    <section class="panel workspace-panel">
        <form class="workspace-toolbar" method="GET" action="{{ route('customers.index') }}">
            <label class="search-field workspace-search"><span class="search-glyph" aria-hidden="true">⌕</span><input type="search" name="search" value="{{ $search }}" placeholder="Search customers..." aria-label="Search customers"></label>
            <button class="button button-primary" type="submit">Search</button>
            @if ($search)
                <a class="button button-light" href="{{ route('customers.index') }}">Reset</a>
            @endif
            <span class="workspace-result-count">{{ number_format($customers->total()) }} {{ \Illuminate\Support\Str::plural('customer', $customers->total()) }} found</span>
        </form>
        @if ($customers->count())
            <div class="table-wrap">
                <table class="data-table workspace-table">
                    <thead><tr><th>#</th><th>CUSTOMER</th><th>ORDERS</th><th>QUANTITY ISSUED</th><th>TOTAL VALUE</th><th>LAST ORDER</th></tr></thead>
                    <tbody>
                    @foreach ($customers as $customer)
                        <tr><td class="muted-cell">{{ $customers->firstItem() + $loop->index }}</td><td><div class="workspace-person"><span>{{ strtoupper(substr($customer->issued_to, 0, 1)) }}</span><strong>{{ $customer->issued_to }}</strong></div></td><td>{{ number_format($customer->orders_count) }}</td><td>{{ number_format($customer->quantity_issued, 2) }}</td><td class="currency-cell">₹{{ number_format($customer->total_value, 2) }}</td><td>{{ $customer->last_order_date ? \Carbon\Carbon::parse($customer->last_order_date)->format('d M Y') : '—' }}</td></tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <footer class="workspace-footer"><span>Showing {{ number_format($customers->firstItem()) }}–{{ number_format($customers->lastItem()) }} of {{ number_format($customers->total()) }} customers</span>@if ($customers->hasPages())<div class="workspace-pagination">{{ $customers->onEachSide(2)->links() }}</div>@endif</footer>
        @else
            <div class="workspace-empty"><span class="workspace-empty-icon"><svg><use href="#icon-users"></use></svg></span><h2>No customers recorded</h2><p>Enter a recipient in the “Issued to” field when recording stock outward. Customers will appear here from those records.</p><a class="button button-primary" href="{{ route('stock-outwards.create') }}">Record Stock Outward</a></div>
        @endif
    </section>
@endsection
