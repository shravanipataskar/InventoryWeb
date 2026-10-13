@extends('layouts.app')

@section('title', 'Stock Inward')
@section('topbar-title', 'Stock inward')

@section('content')
    <div class="stock-inward-heading">
        <div>
            <span class="section-kicker">STOCK MOVEMENT</span>
            <h1>Stock Inward</h1>
            <p>Posted goods receipts appear here automatically as the stock ledger record.</p>
        </div>
        <div class="stock-inward-heading-actions">
            <a class="button button-light" href="{{ route('goods-receipts.index') }}"><span aria-hidden="true">▣</span> Goods Received</a>
            <a class="button button-primary" href="{{ route('quotations.index') }}"><span aria-hidden="true">＋</span> New Quotation</a>
        </div>
    </div>

    @if (session('success'))<div class="inventory-notice notice-in" role="status"><span class="notice-symbol">✓</span><span>{{ session('success') }}</span></div>@endif
    @if ($errors->any())
        <div class="form-alert stock-inward-filter-errors" role="alert">
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="stock-inward-summary">
        <article class="stock-inward-summary-card">
            <span class="stock-inward-summary-icon inward-icon-blue" aria-hidden="true">◇</span>
            <div><span>Total Inward</span><strong>{{ number_format((int) $totals->inward_count) }}</strong><small>Entries matching filters</small></div>
        </article>
        <article class="stock-inward-summary-card">
            <span class="stock-inward-summary-icon inward-icon-green" aria-hidden="true">▤</span>
            <div><span>Total Quantity In</span><strong>{{ number_format((float) $totals->total_quantity, 2) }}</strong><small>Units received</small></div>
        </article>
        <article class="stock-inward-summary-card">
            <span class="stock-inward-summary-icon inward-icon-yellow" aria-hidden="true">₹</span>
            <div><span>Total Purchase Value</span><strong>₹{{ number_format((float) $totals->purchase_value, 2) }}</strong><small>Subtotal (before tax)</small></div>
        </article>
        <article class="stock-inward-summary-card">
            <span class="stock-inward-summary-icon inward-icon-pink" aria-hidden="true">◷</span>
            <div><span>Total Tax Amount</span><strong>₹{{ number_format((float) $totals->tax_amount, 2) }}</strong><small>SGST + CGST</small></div>
        </article>
    </div>

    <section class="panel stock-inward-list-panel">
        <form class="stock-inward-filters" method="GET" action="{{ route('stock-inwards.index') }}">
            <input type="hidden" name="status" value="{{ $listingStatus }}">
            <div class="stock-inward-filter-row">
                <label class="stock-inward-search">
                    <span aria-hidden="true">⌕</span>
                    <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search product, supplier, invoice or inward no..." aria-label="Search stock inward">
                </label>
                <label class="stock-inward-date"><span>From</span><input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" aria-label="From date"></label>
                <label class="stock-inward-date"><span>To</span><input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" aria-label="To date"></label>
                <label class="stock-inward-select stock-inward-status-select">
                    <span class="sr-only">Receipt status</span>
                    <select name="record_status" aria-label="Filter by receipt status">
                        <option value="all" {{ ($filters['record_status'] ?? 'all') === 'all' ? 'selected' : '' }}>All Status</option>
                        <option value="posted" {{ ($filters['record_status'] ?? '') === 'posted' ? 'selected' : '' }}>Posted</option>
                        <option value="active" {{ ($filters['record_status'] ?? '') === 'active' ? 'selected' : '' }}>Active</option>
                    </select>
                </label>
            </div>
            <div class="stock-inward-filter-row stock-inward-filter-row-secondary">
                <label class="stock-inward-select">
                    <span class="sr-only">Supplier</span>
                    <select name="supplier_id" aria-label="Filter by supplier">
                        <option value="">All Suppliers</option>
                        @foreach ($suppliers as $supplier)
                            <option value="{{ $supplier->id }}" {{ (string) ($filters['supplier_id'] ?? '') === (string) $supplier->id ? 'selected' : '' }}>{{ $supplier->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="stock-inward-select">
                    <span class="sr-only">Product</span>
                    <select name="product_id" aria-label="Filter by product">
                        <option value="">All Products</option>
                        @foreach ($products as $product)
                            <option value="{{ $product->id }}" {{ (string) ($filters['product_id'] ?? '') === (string) $product->id ? 'selected' : '' }}>{{ $product->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="stock-inward-select">
                    <span class="sr-only">Location</span>
                    <select name="store_id" aria-label="Filter by location">
                        <option value="">All Locations</option>
                        @foreach ($stores as $store)
                            <option value="{{ $store->id }}" {{ (string) ($filters['store_id'] ?? '') === (string) $store->id ? 'selected' : '' }}>{{ $store->name }}</option>
                        @endforeach
                    </select>
                </label>
                <button class="button button-primary stock-inward-search-button" type="submit"><span aria-hidden="true">⌕</span> Search</button>
                <a class="button button-light stock-inward-reset-button" href="{{ route('stock-inwards.index', ['status' => $listingStatus]) }}"><span aria-hidden="true">↻</span> Reset</a>
                <nav class="stock-inward-status-tabs" aria-label="Active status">
                    <a class="{{ $listingStatus === 'active' ? 'is-selected' : '' }}" href="{{ route('stock-inwards.index', array_merge(request()->query(), ['status' => 'active'])) }}" {{ $listingStatus === 'active' ? 'aria-current=page' : '' }}>Active</a>
                    <a class="{{ $listingStatus === 'inactive' ? 'is-selected' : '' }}" href="{{ route('stock-inwards.index', array_merge(request()->query(), ['status' => 'inactive'])) }}" {{ $listingStatus === 'inactive' ? 'aria-current=page' : '' }}>Inactive</a>
                </nav>
            </div>
        </form>

        <div class="stock-inward-table-wrap" tabindex="0" role="region" aria-label="Stock inward records. Scroll horizontally to view all columns.">
            <table class="stock-inward-table">
                <thead>
                    <tr>
                        <th>#</th><th>INWARD NO.</th><th>DATE</th><th>GRN</th><th>PO</th><th>PRODUCT</th><th>SUPPLIER</th><th>LOCATION</th><th>INVOICE</th><th>QTY IN</th><th>PURCHASE PRICE</th><th>SUBTOTAL</th><th>TAX TOTAL</th><th>GRAND TOTAL</th><th>STATUS</th><th>ACTIONS</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($stockInwards as $stock)
                        <tr>
                            <td><span class="stock-inward-row-number">{{ $stockInwards->firstItem() + $loop->index }}</span></td>
                            <td><span class="stock-inward-code">{{ $stock->inward_number ?: '—' }}</span></td>
                            <td>{{ \Carbon\Carbon::parse($stock->inward_date)->format('d M Y') }}</td>
                            <td>{{ optional(optional($stock->goodsReceiptItem)->goodsReceipt)->grn_number ?: '—' }}</td>
                            <td>{{ optional(optional(optional($stock->goodsReceiptItem)->goodsReceipt)->purchaseOrder)->po_number ?: '—' }}</td>
                            <td><span class="stock-inward-product"><span class="stock-inward-product-icon" aria-hidden="true">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr(optional($stock->product)->name ?: 'P', 0, 1)) }}</span><strong>{{ optional($stock->product)->name ?: '—' }}</strong></span></td>
                            <td>{{ optional($stock->supplier)->name ?: '—' }}</td>
                            <td>{{ optional($stock->store)->name ?: '—' }}</td>
                            <td><span class="stock-inward-invoice">{{ $stock->invoice_number ?: '—' }}</span></td>
                            <td class="stock-inward-quantity">+{{ number_format((float) $stock->quantity, 2) }}</td>
                            <td>₹{{ number_format((float) $stock->purchase_price, 2) }}</td>
                            <td>₹{{ number_format((float) $stock->subtotal, 2) }}</td>
                            <td>₹{{ number_format((float) $stock->tax_total, 2) }}</td>
                            <td><strong class="stock-inward-grand-total">₹{{ number_format((float) $stock->grand_total, 2) }}</strong></td>
                            <td><span class="stock-inward-status {{ $stock->isGoodsReceiptGenerated() ? 'is-posted' : ($stock->is_active ? 'is-active' : 'is-inactive') }}">{{ $stock->isGoodsReceiptGenerated() ? 'Posted' : ($stock->is_active ? 'Active' : 'Inactive') }}</span></td>
                            <td>
                                <div class="stock-inward-actions">
                                    <a class="stock-inward-view-button" href="{{ route('stock-inwards.show', $stock->id) }}"><span aria-hidden="true">◉</span> View</a>
                                    <details class="stock-inward-action-menu">
                                        <summary aria-label="More actions for {{ $stock->inward_number ?: 'stock inward ' . $stock->id }}">⋮</summary>
                                        <div>
                                            @if (!$stock->isGoodsReceiptGenerated() && auth()->user()->role === 'admin')
                                                <a href="{{ route('stock-inwards.edit', $stock->id) }}">Edit legacy inward</a>
                                                <form action="{{ route('stock-inwards.status', $stock->id) }}" method="POST">
                                                    @csrf
                                                    @method('PATCH')
                                                    <input type="hidden" name="is_active" value="{{ $stock->is_active ? 0 : 1 }}">
                                                    <input type="hidden" name="listing_status" value="{{ $listingStatus }}">
                                                    <button type="submit">{{ $stock->is_active ? 'Set Inactive' : 'Set Active' }}</button>
                                                </form>
                                            @else
                                                <a href="{{ route('stock-inwards.show', $stock->id) }}">View details</a>
                                            @endif
                                        </div>
                                    </details>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td class="stock-inward-empty" colspan="16">No {{ $listingStatus }} inward records match these filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <footer class="stock-inward-table-footer">
            <span>
                @if ($stockInwards->total())
                    Showing {{ $stockInwards->firstItem() }}–{{ $stockInwards->lastItem() }} of {{ $stockInwards->total() }} entries
                @else
                    Showing 0 entries
                @endif
            </span>
            @if ($stockInwards->hasPages())
                <div class="stock-inward-pagination">{{ $stockInwards->onEachSide(1)->links() }}</div>
            @endif
        </footer>
    </section>

    <style>
        .stock-inward-heading{display:flex;align-items:center;justify-content:space-between;gap:20px;margin-bottom:20px}
        .stock-inward-heading h1{margin:5px 0;color:#102f40;font-size:28px;letter-spacing:-.5px}
        .stock-inward-heading p{margin:0;color:#647b91;font-size:14px}
        .stock-inward-heading-actions{display:flex;gap:9px;flex:none}
        .stock-inward-heading-actions .button{height:44px;display:inline-flex;align-items:center;gap:8px}
        .stock-inward-summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-bottom:18px}
        .stock-inward-summary-card{min-height:82px;padding:13px 14px;display:flex;align-items:center;gap:13px;border:1px solid #e8edf3;border-radius:13px;background:#fff;box-shadow:0 4px 16px rgba(20,42,64,.035)}
        .stock-inward-summary-icon{width:42px;height:42px;display:grid;place-items:center;flex:none;border-radius:12px;font-size:24px;font-weight:700}
        .inward-icon-blue{background:#eaf0ff;color:#315fd6}.inward-icon-green{background:#e5f8ef;color:#16a06b}.inward-icon-yellow{background:#fff7df;color:#efae00}.inward-icon-pink{background:#ffe9ee;color:#f04464}
        .stock-inward-summary-card div{min-width:0;display:grid;gap:3px}
        .stock-inward-summary-card div>span{color:#415a71;font-size:12px;font-weight:700}
        .stock-inward-summary-card strong{overflow:hidden;color:#102f40;font-size:19px;text-overflow:ellipsis;white-space:nowrap}
        .stock-inward-summary-card small{color:#8191a3;font-size:11px}
        .stock-inward-list-panel{padding:0;overflow:visible;border:1px solid #e8edf3;border-radius:14px;box-shadow:0 4px 16px rgba(20,42,64,.035)}
        .stock-inward-filters{padding:14px 12px 10px;display:grid;gap:10px}
        .stock-inward-filter-row{display:flex;align-items:center;gap:9px}
        .stock-inward-search,.stock-inward-date,.stock-inward-select{height:40px;min-width:0;display:flex;align-items:center;gap:9px;border:1px solid #dce4ed;border-radius:8px;background:#fff;color:#71849a}
        .stock-inward-search{flex:1}
        .stock-inward-search>span{padding-left:11px;font-size:21px}
        .stock-inward-search input,.stock-inward-date input,.stock-inward-select select{width:100%;min-width:0;height:100%;padding:0 10px;border:0;outline:0;background:transparent;color:#30465c;font:inherit;font-size:12px}
        .stock-inward-date{width:150px;padding-left:9px;gap:5px;flex:none}
        .stock-inward-date span{font-size:10px}
        .stock-inward-date input{padding:0 6px;font-size:11px}
        .stock-inward-select{flex:1}
        .stock-inward-select select{appearance:none;cursor:pointer}
        .stock-inward-status-select{max-width:150px}
        .stock-inward-search-button,.stock-inward-reset-button{height:40px;display:inline-flex;align-items:center;justify-content:center;gap:7px;white-space:nowrap}
        .stock-inward-search-button{min-width:82px}
        .stock-inward-reset-button{min-width:78px}
        .stock-inward-status-tabs{margin-left:auto;padding:3px;display:flex;gap:3px;border-radius:9px;background:#f1f5f9}
        .stock-inward-status-tabs a{padding:8px 15px;border-radius:7px;color:#586e83;text-decoration:none;font-size:12px;font-weight:700}
        .stock-inward-status-tabs a.is-selected{background:#008b8c;color:#fff;box-shadow:0 2px 5px rgba(0,112,115,.2)}
        .stock-inward-table-wrap{width:100%;overflow-x:auto}
        .stock-inward-table{width:100%;min-width:1250px;border-collapse:collapse}
        .stock-inward-table th{padding:11px 9px;background:#f5f8fb;color:#697e93;text-align:left;font-size:10px;font-weight:700;line-height:1.25;white-space:nowrap}
        .stock-inward-table td{padding:11px 9px;border-top:1px solid #edf1f5;color:#354b61;font-size:11px;white-space:nowrap}
        .stock-inward-row-number{color:#7790a5}
        .stock-inward-code,.stock-inward-invoice{padding:4px 6px;border-radius:5px;background:#edf3f8;color:#405a70;font-size:10px;font-weight:700}
        .stock-inward-invoice{color:#405a70}
        .stock-inward-product{display:inline-flex;align-items:center;gap:7px}
        .stock-inward-product-icon{width:28px;height:28px;display:grid;place-items:center;border-radius:7px;background:#edf3ff;color:#315fd6;font-size:11px;font-weight:800}
        .stock-inward-product strong{max-width:120px;overflow:hidden;color:#263f56;text-overflow:ellipsis}
        .stock-inward-quantity{color:#187655!important;font-weight:700}
        .stock-inward-grand-total{color:#00878a}
        .stock-inward-status{padding:5px 10px;display:inline-block;border-radius:20px;font-size:10px;font-weight:700}
        .stock-inward-status.is-posted{background:#e5f1ff;color:#1971c2}
        .stock-inward-status.is-active{background:#def7ec;color:#17815c}
        .stock-inward-status.is-inactive{background:#fff0e5;color:#c36724}
        .stock-inward-actions{display:flex;align-items:center;gap:6px}
        .stock-inward-view-button{height:30px;padding:0 9px;display:inline-flex;align-items:center;gap:5px;border:1px solid #dce4ed;border-radius:7px;color:#304960;text-decoration:none;font-size:10px;font-weight:700}
        .stock-inward-action-menu{position:relative}
        .stock-inward-action-menu summary{width:30px;height:30px;display:grid;place-items:center;border:1px solid #dce4ed;border-radius:7px;color:#304960;font-size:18px;line-height:1;cursor:pointer;list-style:none}
        .stock-inward-action-menu summary::-webkit-details-marker{display:none}
        .stock-inward-action-menu>div{position:absolute;z-index:5;top:34px;right:0;min-width:160px;padding:5px;border:1px solid #e2e8ef;border-radius:8px;background:#fff;box-shadow:0 8px 22px rgba(23,43,61,.13)}
        .stock-inward-action-menu a,.stock-inward-action-menu button{width:100%;padding:8px;display:block;border:0;border-radius:5px;background:transparent;color:#304960;text-align:left;text-decoration:none;font:inherit;font-size:11px;cursor:pointer}
        .stock-inward-action-menu a:hover,.stock-inward-action-menu button:hover{background:#f2f6fa}
        .stock-inward-empty{padding:34px!important;text-align:center;color:#71849a!important}
        .stock-inward-table-footer{padding:13px 16px;display:flex;align-items:center;justify-content:space-between;gap:12px;color:#647b91;font-size:12px}
        .stock-inward-pagination nav{display:flex;align-items:center;gap:5px}
        .stock-inward-pagination nav>div:first-child{display:none}
        .stock-inward-pagination nav span,.stock-inward-pagination nav a{min-width:31px;height:31px;padding:0 8px;display:inline-flex;align-items:center;justify-content:center;border:1px solid #e0e7ef;border-radius:7px;color:#405a70;text-decoration:none}
        .stock-inward-pagination nav span[aria-current=page]{border-color:#008b8c;background:#008b8c;color:#fff}
        .stock-inward-filter-errors{margin-bottom:12px}
        .stock-inward-filter-errors ul{margin:0;padding-left:20px}
        .sr-only{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}
        @media(max-width:1100px){.stock-inward-summary{grid-template-columns:repeat(2,minmax(0,1fr))}.stock-inward-filter-row{flex-wrap:wrap}.stock-inward-search{flex-basis:100%}.stock-inward-status-select{flex:1}.stock-inward-select{min-width:150px}}
        @media(max-width:650px){.stock-inward-heading{align-items:flex-start;flex-direction:column}.stock-inward-heading h1{font-size:24px}.stock-inward-heading-actions{width:100%}.stock-inward-heading-actions .button{flex:1;justify-content:center}.stock-inward-summary{grid-template-columns:1fr 1fr;gap:8px}.stock-inward-summary-card{padding:10px;gap:8px}.stock-inward-summary-icon{width:34px;height:34px;font-size:19px}.stock-inward-summary-card strong{font-size:15px}.stock-inward-date{flex:1}.stock-inward-filter-row-secondary>.stock-inward-select{flex-basis:100%}.stock-inward-status-tabs{margin-left:0}.stock-inward-table-footer{align-items:flex-start;flex-direction:column}}
    </style>
@endsection
