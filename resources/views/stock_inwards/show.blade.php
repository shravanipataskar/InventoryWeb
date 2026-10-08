@extends('layouts.app')

@section('title', $stockInward->inward_number ?: 'Stock Inward')
@section('topbar-title', 'Stock inward')

@section('content')
    @php
        $receiptItem = $stockInward->goodsReceiptItem;
        $receipt = $receiptItem ? $receiptItem->goodsReceipt : null;
        $purchaseOrder = $receipt ? $receipt->purchaseOrder : null;
    @endphp
    <div class="page-heading">
        <div>
            <span class="section-kicker">STOCK MOVEMENT / INWARD</span>
            <h1>{{ $stockInward->inward_number ?: 'Legacy Stock Inward #' . $stockInward->id }}</h1>
            <p>{{ $stockInward->isGoodsReceiptGenerated() ? 'Automatically posted from Goods Received.' : 'Historical stock inward record.' }}</p>
        </div>
        <a class="button button-light" href="{{ route('stock-inwards.index') }}">Back to Stock Inward</a>
    </div>

    <section class="panel listing-panel">
        <div class="form-grid">
            <div class="field"><label>Status</label><p>{{ $stockInward->isGoodsReceiptGenerated() ? 'Posted' : ($stockInward->is_active ? 'Active' : 'Inactive') }}</p></div>
            <div class="field"><label>Inward date</label><p>{{ \Carbon\Carbon::parse($stockInward->inward_date)->format('d M Y') }}</p></div>
            <div class="field"><label>Location</label><p>{{ optional($stockInward->store)->name ?: '—' }}</p></div>
            <div class="field"><label>Supplier</label><p>{{ optional($stockInward->supplier)->name ?: '—' }}</p></div>
            <div class="field"><label>Goods Received</label><p>{{ optional($receipt)->grn_number ?: '—' }}</p></div>
            <div class="field"><label>Purchase Order</label><p>{{ optional($purchaseOrder)->po_number ?: '—' }}</p></div>
            <div class="field"><label>Supplier invoice</label><p>{{ $stockInward->invoice_number ?: '—' }}</p></div>
            <div class="field"><label>Received by</label><p>{{ optional($receipt ? $receipt->receiver : null)->name ?: '—' }}</p></div>
        </div>
    </section>

    <section class="panel listing-panel">
        <div class="panel-heading"><h2>Product and quantities</h2></div>
        <div class="table-wrap"><table class="data-table listing-table">
            <thead><tr><th>CATEGORY</th><th>PRODUCT</th><th>SKU</th><th>UNIT</th><th>ORDERED</th><th>RECEIVED</th><th>REJECTED</th><th>ACCEPTED / IN</th><th>UNIT RATE</th><th>INWARD VALUE</th></tr></thead>
            <tbody><tr>
                <td>{{ optional(optional($stockInward->product)->category)->name ?: '—' }}</td>
                <td><strong>{{ optional($stockInward->product)->name ?: '—' }}</strong></td>
                <td>{{ optional($stockInward->product)->product_code ?: '—' }}</td>
                <td>{{ optional(optional($stockInward->product)->unit)->short_name ?: optional(optional($stockInward->product)->unit)->name ?: '—' }}</td>
                <td>{{ $receiptItem ? number_format($receiptItem->ordered_quantity, 2) : '—' }}</td>
                <td>{{ number_format($stockInward->received_quantity ?: $stockInward->quantity, 2) }}</td>
                <td>{{ number_format($stockInward->rejected_quantity, 2) }}</td>
                <td>+{{ number_format($stockInward->quantity, 2) }}</td>
                <td>₹{{ number_format($stockInward->purchase_price, 2) }}</td>
                <td>₹{{ number_format($stockInward->total_amount, 2) }}</td>
            </tr></tbody>
        </table></div>
    </section>

    <section class="panel listing-panel">
        <div class="form-grid">
            <div class="field"><label>Created by</label><p>{{ optional($stockInward->creator)->name ?: '—' }}</p></div>
            <div class="field"><label>Created at</label><p>{{ $stockInward->created_at ? $stockInward->created_at->format('d M Y h:i A') : '—' }}</p></div>
            <div class="field"><label>Remarks</label><p>{{ $stockInward->remarks ?: '—' }}</p></div>
        </div>
        @if ($receipt || $purchaseOrder)
            <div class="form-actions">
                @if ($receipt)<a class="button button-light" href="{{ route('goods-receipts.show', $receipt->id) }}">View GRN</a>@endif
                @if ($purchaseOrder)<a class="button button-light" href="{{ route('purchase-orders.show', $purchaseOrder->id) }}">View Purchase Order</a>@endif
                <a class="button button-light" href="{{ route('stock-movement.index', ['search' => $stockInward->inward_number]) }}">View Stock Movement</a>
            </div>
        @endif
    </section>
@endsection
