@extends('layouts.app')

@section('title', 'Stock Outward')
@section('topbar-title', 'Stock outward')

@section('content')
    <div class="stock-outward-heading">
        <div>
            <span class="section-kicker">STOCK MOVEMENT</span>
            <h1>Stock Outward</h1>
            <p>Record inventory issued or sold.</p>
        </div>
        <a class="button button-primary" href="{{ route('stock-outwards.create') }}"><span class="button-plus">+</span> Add Stock Outward</a>
    </div>

    @if (session('success'))
        <div class="inventory-notice notice-in" role="status"><span class="notice-symbol">✓</span><span>{{ session('success') }}</span></div>
    @endif
    @if ($errors->has('status'))
        <div class="form-alert" role="alert">{{ $errors->first('status') }}</div>
    @endif

    <div class="stock-outward-summary">
        <article class="stock-outward-summary-card">
            <span class="stock-outward-summary-icon outward-icon-blue" aria-hidden="true">◫</span>
            <div>
                <span>Total Outward Entries</span>
                <strong>{{ number_format((int) ($totals->total_records ?? 0)) }}</strong>
                <small>Total records</small>
            </div>
        </article>
        <article class="stock-outward-summary-card">
            <span class="stock-outward-summary-icon outward-icon-green" aria-hidden="true">▣</span>
            <div>
                <span>Total Quantity Issued</span>
                <strong>{{ number_format((float) ($totals->total_quantity ?? 0), 2) }}</strong>
                <small>Units (outward)</small>
            </div>
        </article>
        <article class="stock-outward-summary-card">
            <span class="stock-outward-summary-icon outward-icon-yellow" aria-hidden="true">₹</span>
            <div>
                <span>Total Sales Value</span>
                <strong>₹{{ number_format((float) ($totals->total_amount ?? 0), 2) }}</strong>
                <small>Total amount (outward)</small>
            </div>
        </article>
        <article class="stock-outward-summary-card">
            <span class="stock-outward-summary-icon outward-icon-pink" aria-hidden="true">◷</span>
            <div>
                <span>Active / Inactive</span>
                <strong>{{ $activeCount }} / {{ $inactiveCount }}</strong>
                <small>Outward records</small>
            </div>
        </article>
    </div>

    <section class="panel stock-outward-list-panel">
        <form class="stock-outward-filters" method="GET" action="{{ route('stock-outwards.index') }}">
            <input type="hidden" name="status" value="{{ $listingStatus }}">
            <div class="stock-outward-filter-row">
                <label class="stock-outward-search">
                    <span class="search-glyph" aria-hidden="true">⌕</span>
                    <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search product, reference or recipient..." aria-label="Search stock outward">
                </label>
                <label class="stock-outward-date"><span>From</span><input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" aria-label="From date"></label>
                <label class="stock-outward-date"><span>To</span><input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" aria-label="To date"></label>
                <label class="stock-outward-select stock-outward-status-select">
                    <span class="sr-only">Customer</span>
                    <select name="customer_id" aria-label="Filter by customer">
                        <option value="">All Issued To</option>
                        @foreach ($customers as $customer)
                            <option value="{{ $customer->id }}" {{ (string) ($filters['customer_id'] ?? '') === (string) $customer->id ? 'selected' : '' }}>{{ $customer->name }}</option>
                        @endforeach
                    </select>
                </label>
            </div>
            <div class="stock-outward-filter-row stock-outward-filter-row-secondary">
                <label class="stock-outward-select">
                    <span class="sr-only">Product</span>
                    <select name="product_id" aria-label="Filter by product">
                        <option value="">All Products</option>
                        @foreach ($products as $product)
                            <option value="{{ $product->id }}" {{ (string) ($filters['product_id'] ?? '') === (string) $product->id ? 'selected' : '' }}>{{ $product->name }}</option>
                        @endforeach
                    </select>
                </label>
                <button class="button button-primary stock-outward-search-button" type="submit"><span class="search-button-glyph" aria-hidden="true">⌕</span> Search</button>
                <a class="button button-light stock-outward-reset-button" href="{{ route('stock-outwards.index', ['status' => $listingStatus]) }}"><span aria-hidden="true">↻</span> Reset</a>
                <nav class="stock-outward-status-tabs" aria-label="Stock outward status">
                    <a class="{{ $listingStatus === 'active' ? 'is-selected' : '' }}" href="{{ route('stock-outwards.index', array_merge(request()->query(), ['status' => 'active'])) }}" {{ $listingStatus === 'active' ? 'aria-current=page' : '' }}>Active</a>
                    <a class="{{ $listingStatus === 'inactive' ? 'is-selected' : '' }}" href="{{ route('stock-outwards.index', array_merge(request()->query(), ['status' => 'inactive'])) }}" {{ $listingStatus === 'inactive' ? 'aria-current=page' : '' }}>Inactive</a>
                </nav>
            </div>
        </form>

        <div class="stock-outward-table-wrap" tabindex="0" role="region" aria-label="Stock outward records. Scroll horizontally to view all columns.">
            <table class="stock-outward-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>OUTWARD NO.</th>
                        <th>DATE</th>
                        <th>PRODUCT</th>
                        <th>REFERENCE</th>
                        <th>ISSUED TO</th>
                        <th>QUANTITY</th>
                        <th>SELLING PRICE</th>
                        <th>TOTAL AMOUNT</th>
                        <th>STATUS</th>
                        <th>ACTIONS</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($stockOutwards as $stock)
                        <tr>
                            <td><span class="stock-outward-row-number">{{ $stockOutwards->firstItem() + $loop->index }}</span></td>
                            <td><span class="stock-outward-code">OUT-{{ str_pad((string) $stock->id, 3, '0', STR_PAD_LEFT) }}</span></td>
                            <td>{{ \Carbon\Carbon::parse($stock->outward_date)->format('d M Y') }}</td>
                            <td>
                                <span class="stock-outward-product">
                                    <span class="stock-outward-product-icon">◧</span>
                                    <strong>{{ optional($stock->product)->name ?: '—' }}</strong>
                                </span>
                            </td>
                            <td><span class="stock-outward-reference">{{ $stock->reference_number ?: '—' }}</span></td>
                            <td>{{ optional($stock->customer)->name ?: ($stock->issued_to ?: '—') }}</td>
                            <td class="stock-outward-quantity">{{ number_format((float) $stock->quantity, 2) }}</td>
                            <td>₹{{ number_format((float) $stock->selling_price, 2) }}</td>
                            <td><strong class="stock-outward-grand-total">₹{{ number_format((float) $stock->total_amount, 2) }}</strong></td>
                            <td><span class="stock-outward-status {{ $stock->is_active ? 'is-active' : 'is-inactive' }}">{{ $stock->is_active ? 'Active' : 'Inactive' }}</span></td>
                            <td>
                                <div class="stock-outward-actions">
                                    <a class="stock-outward-view-button" href="{{ route('stock-outwards.show', $stock->id) }}"><span aria-hidden="true">◉</span> View</a>
                                    <form class="status-action-form" action="{{ route('stock-outwards.status', $stock->id) }}" method="POST">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="is_active" value="{{ $stock->is_active ? 0 : 1 }}">
                                        <input type="hidden" name="listing_status" value="{{ $listingStatus }}">
                                        <button type="submit" class="stock-outward-toggle-button {{ $stock->is_active ? 'button-deactivate' : 'button-activate' }}">{{ $stock->is_active ? 'Inactive' : 'Active' }}</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td class="stock-outward-empty" colspan="11">No {{ $listingStatus }} outward records match these filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <footer class="stock-outward-table-footer">
            <span>
                @if ($stockOutwards->total())
                    Showing {{ $stockOutwards->firstItem() }}–{{ $stockOutwards->lastItem() }} of {{ $stockOutwards->total() }} entries
                @else
                    Showing 0 entries
                @endif
            </span>
            @if ($stockOutwards->hasPages())
                <div class="stock-outward-pagination">{{ $stockOutwards->onEachSide(1)->links() }}</div>
            @endif
        </footer>
    </section>

    <style>
        .stock-outward-heading{display:flex;align-items:center;justify-content:space-between;gap:20px;margin-bottom:20px}
        .stock-outward-heading h1{margin:5px 0;color:#102f40;font-size:28px;letter-spacing:-.5px}
        .stock-outward-heading p{margin:0;color:#647b91;font-size:14px}
        .stock-outward-summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-bottom:18px}
        .stock-outward-summary-card{min-height:82px;padding:13px 14px;display:flex;align-items:center;gap:13px;border:1px solid #e8edf3;border-radius:13px;background:#fff;box-shadow:0 4px 16px rgba(20,42,64,.035)}
        .stock-outward-summary-icon{width:42px;height:42px;display:grid;place-items:center;flex:none;border-radius:12px;font-size:24px;font-weight:700}
        .outward-icon-blue{background:#eaf0ff;color:#315fd6}.outward-icon-green{background:#e5f8ef;color:#16a06b}.outward-icon-yellow{background:#fff7df;color:#efae00}.outward-icon-pink{background:#ffe9ee;color:#f04464}
        .stock-outward-summary-card div{min-width:0;display:grid;gap:3px}
        .stock-outward-summary-card div>span{color:#415a71;font-size:12px;font-weight:700}
        .stock-outward-summary-card strong{overflow:hidden;color:#102f40;font-size:19px;text-overflow:ellipsis;white-space:nowrap}
        .stock-outward-summary-card small{color:#8191a3;font-size:11px}
        .stock-outward-list-panel{padding:0;overflow:visible;border:1px solid #e8edf3;border-radius:14px;box-shadow:0 4px 16px rgba(20,42,64,.035)}
        .stock-outward-filters{padding:14px 12px 10px;display:grid;gap:10px}
        .stock-outward-filter-row{display:flex;align-items:center;gap:9px}
        .stock-outward-search,.stock-outward-date,.stock-outward-select{height:40px;min-width:0;display:flex;align-items:center;gap:9px;border:1px solid #dce4ed;border-radius:8px;background:#fff;color:#71849a}
        .stock-outward-search{flex:1}
        .stock-outward-search>span{padding-left:11px;font-size:21px}
        .stock-outward-search input,.stock-outward-date input,.stock-outward-select select{width:100%;min-width:0;height:100%;padding:0 10px;border:0;outline:0;background:transparent;color:#30465c;font:inherit;font-size:12px}
        .stock-outward-date{width:150px;padding-left:9px;gap:5px;flex:none}
        .stock-outward-date span{font-size:10px}
        .stock-outward-date input{padding:0 6px;font-size:11px}
        .stock-outward-select{flex:1}
        .stock-outward-select select{appearance:none;cursor:pointer}
        .stock-outward-status-select{max-width:180px}
        .stock-outward-search-button,.stock-outward-reset-button{height:40px;display:inline-flex;align-items:center;justify-content:center;gap:7px;white-space:nowrap}
        .stock-outward-search-button{min-width:82px}
        .stock-outward-reset-button{min-width:78px}
        .stock-outward-status-tabs{margin-left:auto;padding:3px;display:flex;gap:3px;border-radius:9px;background:#f1f5f9}
        .stock-outward-status-tabs a{padding:8px 15px;border-radius:7px;color:#586e83;text-decoration:none;font-size:12px;font-weight:700}
        .stock-outward-status-tabs a.is-selected{background:#008b8c;color:#fff;box-shadow:0 2px 5px rgba(0,112,115,.2)}
        .stock-outward-table-wrap{width:100%;overflow-x:auto}
        .stock-outward-table{width:100%;min-width:1200px;border-collapse:collapse}
        .stock-outward-table th{padding:11px 9px;background:#f5f8fb;color:#697e93;text-align:left;font-size:10px;font-weight:700;line-height:1.25;white-space:nowrap}
        .stock-outward-table td{padding:11px 9px;border-top:1px solid #edf1f5;color:#354b61;font-size:11px;white-space:nowrap}
        .stock-outward-row-number{color:#7790a5}
        .stock-outward-code,.stock-outward-reference{padding:4px 6px;border-radius:5px;background:#edf3f8;color:#405a70;font-size:10px;font-weight:700}
        .stock-outward-product{display:inline-flex;align-items:center;gap:7px}
        .stock-outward-product-icon{width:28px;height:28px;display:grid;place-items:center;border-radius:7px;background:#edf3ff;color:#315fd6;font-size:11px;font-weight:800}
        .stock-outward-product strong{max-width:150px;overflow:hidden;color:#263f56;text-overflow:ellipsis}
        .stock-outward-quantity{color:#187655!important;font-weight:700}
        .stock-outward-grand-total{color:#00878a}
        .stock-outward-status{display:inline-flex;align-items:center;padding:5px 10px;border-radius:999px;font-size:10px;font-weight:700}
        .stock-outward-status.is-active{background:#e8f7ef;color:#1a8d65}
        .stock-outward-status.is-inactive{background:#fff3df;color:#b8771d}
        .stock-outward-actions{display:flex;align-items:center;gap:8px}
        .stock-outward-view-button{display:inline-flex;align-items:center;gap:4px;padding:7px 10px;border:1px solid #dfe7ec;border-radius:8px;background:#fff;color:#4c6475;font-size:10px;font-weight:700}
        .stock-outward-toggle-button{border:0;border-radius:8px;padding:7px 10px;font-size:10px;font-weight:700;cursor:pointer}
        .button-deactivate{color:#b16e1b;background:#fff4df}
        .button-activate{color:#188366;background:#e7f6ef}
        .stock-outward-table-footer{display:flex;min-height:54px;align-items:center;justify-content:space-between;gap:12px;padding:9px 18px;border-top:1px solid #f0f2f4;color:#5d6d76;font-size:9px}
        .stock-outward-pagination{display:flex;align-items:center;gap:4px}
        .stock-outward-pagination .page-link,.stock-outward-pagination .page-item.active .page-link{display:grid;min-width:27px;height:27px;padding:0 6px;place-items:center;border:1px solid #e9eef0;border-radius:6px;color:#63747d;background:#fff;font:inherit;font-size:9px;text-decoration:none}
        .stock-outward-pagination .page-item.active .page-link{border-color:#008b8c;color:#fff;background:#008b8c}
        .stock-outward-pagination .disabled .page-link{color:#b3bdc2;background:#f8f9fa;cursor:default}
        .stock-outward-empty{padding:20px 10px;color:#778996;text-align:center;font-size:12px}
        @media(max-width:1100px){.stock-outward-summary{grid-template-columns:repeat(2,minmax(0,1fr))}.stock-outward-filter-row{flex-wrap:wrap}.stock-outward-search{flex-basis:100%}.stock-outward-status-select{flex:1}.stock-outward-select{min-width:150px}.stock-outward-status-tabs{margin-left:0}}
        @media(max-width:650px){.stock-outward-heading{align-items:flex-start;flex-direction:column}.stock-outward-heading h1{font-size:24px}.stock-outward-summary{grid-template-columns:1fr 1fr;gap:8px}.stock-outward-summary-card{padding:10px;gap:8px}.stock-outward-summary-icon{width:34px;height:34px;font-size:19px}.stock-outward-summary-card strong{font-size:15px}.stock-outward-date{flex:1}.stock-outward-filter-row-secondary>.stock-outward-select{flex-basis:100%}.stock-outward-status-tabs{margin-left:0}.stock-outward-table-footer{align-items:flex-start;flex-direction:column}}
    </style>
@endsection
