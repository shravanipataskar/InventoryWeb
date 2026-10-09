@extends('layouts.app')

@section('title', $customer->name)
@section('topbar-title', 'Customer details')

@section('content')
    <div class="page-heading">
        <div><span class="section-kicker">RELATIONSHIPS / CUSTOMERS</span><h1>{{ $customer->name }}</h1><p>{{ $customer->customer_type }} customer details and stock outward history.</p></div>
        <div class="page-heading-actions">
            <a class="button button-light" href="{{ route('customers.index') }}">Back to customers</a>
            <a class="button button-primary" href="{{ route('customers.edit', $customer->id) }}">Edit Customer</a>
        </div>
    </div>
    <section class="workspace-summary-grid">
        <article class="workspace-summary-card"><span class="workspace-summary-icon workspace-blue"><svg><use href="#icon-users"></use></svg></span><div><small>Customer Type</small><strong>{{ $customer->customer_type }}</strong></div></article>
        <article class="workspace-summary-card"><span class="workspace-summary-icon workspace-green"><svg><use href="#icon-tray-out"></use></svg></span><div><small>Stock Issues</small><strong>{{ number_format($stockOutwards->total()) }}</strong></div></article>
        <article class="workspace-summary-card"><span class="workspace-summary-icon workspace-blue"><svg><use href="#icon-users"></use></svg></span><div><small>Status</small><strong>{{ $customer->is_active ? 'Active' : 'Inactive' }}</strong></div></article>
    </section>
    <section class="panel workspace-panel">
        <div class="form-section">
            <div class="form-card-heading"><span class="form-section-icon"><svg><use href="#icon-users"></use></svg></span><div><h2>Contact Details</h2><p>Saved customer master information.</p></div></div>
            <div class="form-grid">
                <div class="field"><label>Contact Person</label><div class="product-identifier-display">{{ $customer->contact_person ?: '—' }}</div></div>
                <div class="field"><label>Phone</label><div class="product-identifier-display">{{ $customer->phone ?: '—' }}</div></div>
                <div class="field"><label>Email</label><div class="product-identifier-display">{{ $customer->email ?: '—' }}</div></div>
                <div class="field"><label>Store / Warehouse Location</label><div class="product-identifier-display">{{ $customer->store_warehouse_location ?: '—' }}</div></div>
                <div class="field"><label>City / State / Pincode</label><div class="product-identifier-display">{{ collect([$customer->city, $customer->state, $customer->pincode])->filter()->implode(', ') ?: '—' }}</div></div>
                <div class="field field-wide"><label>Address</label><div class="product-identifier-display">{{ $customer->address ?: '—' }}</div></div>
                <div class="field"><label>GSTIN</label><div class="product-identifier-display">{{ $customer->gstin ?: '—' }}</div></div>
                <div class="field"><label>PAN</label><div class="product-identifier-display">{{ $customer->pan ?: '—' }}</div></div>
            </div>
        </div>
    </section>
    <section class="panel workspace-panel">
        <div class="form-card-heading"><span class="form-section-icon form-icon-violet"><svg><use href="#icon-tray-out"></use></svg></span><div><h2>Stock Issues / Transaction History</h2><p>Stock outward records for this customer.</p></div></div>
        @if ($stockOutwards->count())
            <div class="table-wrap">
                <table class="data-table workspace-table">
                    <thead><tr><th>DATE</th><th>PRODUCT</th><th>QUANTITY</th><th>LOCATION</th><th>PURPOSE</th><th>REFERENCE NUMBER</th></tr></thead>
                    <tbody>
                    @foreach ($stockOutwards as $stock)
                        @php
                            $product = $stock->product;
                            $location = $product ? collect([
                                optional($product->hall)->name,
                                optional($product->rack)->name,
                                optional($product->shelf)->name,
                            ])->filter()->implode(' / ') : '';
                        @endphp
                        <tr>
                            <td>{{ \Carbon\Carbon::parse($stock->outward_date)->format('d-M-Y') }}</td>
                            <td>{{ $product ? $product->name : '—' }}</td>
                            <td>{{ number_format($stock->quantity, 2) }} {{ optional(optional($product)->unit)->short_name }}</td>
                            <td>{{ $location ?: '—' }}</td>
                            <td>{{ $stock->remarks ?: '—' }}</td>
                            <td>{{ $stock->reference_number ?: '—' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            @if ($stockOutwards->hasPages())
                <footer class="workspace-footer"><span>Showing {{ number_format($stockOutwards->firstItem()) }}–{{ number_format($stockOutwards->lastItem()) }} of {{ number_format($stockOutwards->total()) }} stock issues</span><div class="workspace-pagination">{{ $stockOutwards->links() }}</div></footer>
            @endif
        @else
            <div class="workspace-empty"><h2>No stock issues yet</h2><p>Stock outward transactions for {{ $customer->name }} will appear here.</p><a class="button button-primary" href="{{ route('stock-outwards.create') }}">Record Stock Outward</a></div>
        @endif
    </section>
@endsection
