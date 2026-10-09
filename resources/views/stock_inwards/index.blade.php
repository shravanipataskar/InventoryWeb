@extends('layouts.app')

@section('title', 'Stock Inward')
@section('topbar-title', 'Stock inward')

@section('content')
    <div class="page-heading">
        <div><span class="section-kicker">STOCK MOVEMENT</span><h1>Stock Inward</h1><p>Posted goods receipts appear here automatically as the stock ledger record.</p></div>
        <div class="page-heading-actions">
            <a class="button button-light" href="{{ route('goods-receipts.index') }}">Goods Received</a>
            <a class="button button-primary" href="{{ route('quotations.index') }}">Quotation</a>
        </div>
    </div>

    @if (session('success'))<div class="inventory-notice notice-in" role="status"><span class="notice-symbol">✓</span><span>{{ session('success') }}</span></div>@endif
    @if ($errors->has('status'))
        <div class="form-alert" role="alert">{{ $errors->first('status') }}</div>
    @endif

    <section class="panel listing-panel" data-enhanced-table data-page-size="10">
        <div class="table-toolbar table-toolbar-wrap">
            <label class="search-field search-field-grow"><span class="search-glyph" aria-hidden="true">⌕</span><input type="search" placeholder="Search product, supplier or invoice..." aria-label="Search stock inward records" data-table-search></label>
            <label class="date-filter"><span>From</span><input type="date" aria-label="From date" data-date-from></label>
            <label class="date-filter"><span>To</span><input type="date" aria-label="To date" data-date-to></label>
            <div class="status-tabs" aria-label="Stock inward status">
                <a class="status-tab {{ $listingStatus === 'active' ? 'is-selected' : '' }}" href="{{ route('stock-inwards.index', ['status' => 'active']) }}" {{ $listingStatus === 'active' ? 'aria-current=page' : '' }}>Active</a>
                <a class="status-tab {{ $listingStatus === 'inactive' ? 'is-selected' : '' }}" href="{{ route('stock-inwards.index', ['status' => 'inactive']) }}" {{ $listingStatus === 'inactive' ? 'aria-current=page' : '' }}>Inactive</a>
            </div>
        </div>
        <div class="table-wrap stock-inward-table-wrap" tabindex="0" role="region" aria-label="Stock inward records. Scroll horizontally to view all columns.">
            <table class="data-table listing-table">
                <thead><tr><th>#</th><th>INWARD NO.</th><th>DATE</th><th>GRN</th><th>PO</th><th>PRODUCT</th><th>SUPPLIER</th><th>LOCATION</th><th>INVOICE</th><th>QUANTITY IN</th><th>PURCHASE PRICE</th><th>SUBTOTAL</th><th>SGST</th><th>CGST</th><th>TAX TOTAL</th><th>GRAND TOTAL</th><th>STATUS</th><th>ACTIONS</th></tr></thead>
                <tbody>
                @forelse ($stockInwards as $stock)
                    <tr data-table-row data-date="{{ \Carbon\Carbon::parse($stock->inward_date)->format('Y-m-d') }}">
                        <td class="muted-cell">{{ $loop->iteration }}</td>
                        <td><span class="unit-code">{{ $stock->inward_number ?: '—' }}</span></td>
                        <td>{{ \Carbon\Carbon::parse($stock->inward_date)->format('d M Y') }}</td>
                        <td>{{ optional(optional($stock->goodsReceiptItem)->goodsReceipt)->grn_number ?: '—' }}</td>
                        <td>{{ optional(optional(optional($stock->goodsReceiptItem)->goodsReceipt)->purchaseOrder)->po_number ?: '—' }}</td>
                        <td><strong class="table-primary-text">{{ optional($stock->product)->name ?: '—' }}</strong></td>
                        <td>{{ optional($stock->supplier)->name ?: '—' }}</td>
                        <td>{{ optional($stock->store)->name ?: '—' }}</td>
                        <td><span class="unit-code">{{ $stock->invoice_number ?: '—' }}</span></td>
                        <td class="number-cell quantity-in">+{{ number_format($stock->quantity, 2) }}</td>
                        <td class="currency-cell">₹{{ number_format($stock->purchase_price, 2) }}</td>
                        <td class="currency-cell">₹{{ number_format($stock->subtotal, 2) }}</td>
                        <td class="currency-cell">{{ number_format($stock->sgst_rate, 2) }}% / ₹{{ number_format($stock->sgst_amount, 2) }}</td>
                        <td class="currency-cell">{{ number_format($stock->cgst_rate, 2) }}% / ₹{{ number_format($stock->cgst_amount, 2) }}</td>
                        <td class="currency-cell">₹{{ number_format($stock->tax_total, 2) }}</td>
                        <td><strong class="total-cell">₹{{ number_format($stock->grand_total, 2) }}</strong></td>
                        <td>{{ $stock->isGoodsReceiptGenerated() ? 'Posted' : ($stock->is_active ? 'Active' : 'Inactive') }}</td>
                        <td class="action-cell">
                            <a class="button button-small button-light" href="{{ route('stock-inwards.show', $stock->id) }}">View</a>
                            @if (!$stock->isGoodsReceiptGenerated() && auth()->user()->role === 'admin')
                                <a class="button button-small button-light" href="{{ route('stock-inwards.edit', $stock->id) }}">Edit legacy</a>
                                <form class="status-action-form" action="{{ route('stock-inwards.status', $stock->id) }}" method="POST">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="is_active" value="{{ $stock->is_active ? 0 : 1 }}">
                                    <input type="hidden" name="listing_status" value="{{ $listingStatus }}">
                                    <button class="button button-small {{ $stock->is_active ? 'button-deactivate' : 'button-activate' }}" type="submit">{{ $stock->is_active ? 'Inactive' : 'Active' }}</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="18">@include('components.empty-state', ['icon' => 'icon-tray-in', 'title' => 'No ' . $listingStatus . ' inward records', 'message' => $listingStatus === 'active' ? 'Stock inward records are generated automatically when Goods Received is posted.' : 'Deactivated legacy receipts will appear here.'])</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <p class="table-scroll-hint">Scroll horizontally to view all stock inward columns.</p>
        <div class="filter-empty" data-filter-empty hidden>No {{ $listingStatus }} inward records match your search or date range.</div>
        <div class="table-footer"><span class="table-summary" data-table-summary></span><div class="pagination" data-table-pagination></div></div>
    </section>
@endsection
