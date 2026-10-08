@extends('layouts.app')

@section('title', 'Purchase Details')
@section('topbar-title', 'Purchase Details')

@section('content')
    <div class="page-heading purchase-report-heading">
        <div><span class="section-kicker">REPORTS / PROCUREMENT</span><h1>Purchase Details</h1><p>Read-only purchase and receipt information.</p></div>
        <div class="purchase-report-detail-actions">
            @if ($kind === 'receipt')
                <a class="button button-light" href="{{ route('goods-receipts.show', $record->id) }}">Open GRN</a>
                <a class="button button-light" href="{{ route('purchase-orders.show', $record->purchase_order_id) }}">Open Purchase Order</a>
            @elseif ($kind === 'order')
                <a class="button button-light" href="{{ route('purchase-orders.show', $record->id) }}">Open Purchase Order</a>
            @endif
            <a class="button button-light" href="{{ route('purchase-reports.index') }}">Back to Purchase Reports</a>
        </div>
    </div>

    @if ($kind === 'receipt')
        @php
            $supplier = $record->supplier;
            $purchaseOrder = $record->purchaseOrder;
            $store = $record->store;
            $lines = $record->items;
            $date = $record->received_date;
            $poNumber = optional($purchaseOrder)->po_number;
            $reference = $record->grn_number;
            $createdBy = optional($record->receiver)->name;
            $status = optional($purchaseOrder)->status === 'received' ? 'Received' : 'Partial';
        @endphp
    @elseif ($kind === 'order')
        @php
            $supplier = $record->supplier;
            $purchaseOrder = $record;
            $store = $record->store;
            $lines = $record->items;
            $date = $record->po_date;
            $poNumber = $record->po_number;
            $reference = null;
            $createdBy = null;
            $status = ucfirst(str_replace('_', ' ', $record->status));
        @endphp
    @else
        @php
            $supplier = $record->supplier;
            $purchaseOrder = null;
            $store = $record->store;
            $lines = collect([$record]);
            $date = $record->inward_date;
            $poNumber = null;
            $reference = $record->inward_number ?: $record->invoice_number;
            $createdBy = optional($record->creator)->name;
            $status = ucfirst($record->status ?: 'Received');
        @endphp
    @endif

    <section class="panel purchase-report-detail-card">
        <div class="purchase-report-section-heading"><span class="section-kicker">TRANSACTION INFORMATION</span><h2>{{ $reference ?: ($poNumber ?: 'Purchase record') }}</h2></div>
        <div class="purchase-report-detail-grid">
            <div><span>GRN / Reference</span><strong>{{ $reference ?: '—' }}</strong></div>
            <div><span>Purchase Order</span><strong>{{ $poNumber ?: '—' }}</strong></div>
            <div><span>Invoice</span><strong>{{ $kind === 'receipt' ? ($record->invoice_number ?: '—') : ($kind === 'inward' ? ($record->invoice_number ?: '—') : '—') }}</strong></div>
            <div><span>Date</span><strong>{{ $date ? \Carbon\Carbon::parse($date)->format('d M Y') : '—' }}</strong></div>
            <div><span>Supplier</span><strong>{{ optional($supplier)->name ?: '—' }}</strong><small>{{ optional($supplier)->phone ?: '' }}</small></div>
            <div><span>Location</span><strong>{{ optional($store)->name ?: '—' }}</strong></div>
            <div><span>Status</span><strong>{{ $status }}</strong></div>
            <div><span>Entered By</span><strong>{{ $createdBy ?: '—' }}</strong></div>
        </div>
    </section>

    <section class="panel purchase-report-detail-card">
        <div class="purchase-report-section-heading"><span class="section-kicker">PURCHASE LINES</span><h2>Item Details</h2></div>
        <div class="table-wrap"><table class="data-table purchase-report-table purchase-detail-table">
            <thead><tr><th>PRODUCT</th><th>SKU</th><th>ORDERED QTY</th><th>RECEIVED QTY</th><th>REJECTED QTY</th><th>ACCEPTED QTY</th><th>UNIT COST</th><th>TAX</th><th>SUBTOTAL</th><th>TOTAL</th></tr></thead>
            <tbody>
                @foreach ($lines as $line)
                    @if ($kind === 'receipt')
                        @php
                            $product = $line->product;
                            $orderItem = $line->purchaseOrderItem;
                            $inward = $line->stockInward;
                            $ordered = $line->ordered_quantity;
                            $received = $line->received_quantity;
                            $rejected = $line->rejected_quantity;
                            $accepted = $line->accepted_quantity;
                            $rate = $line->purchase_rate;
                            $subtotal = $inward ? $inward->subtotal : (float) $accepted * (float) $rate;
                            $tax = $inward ? $inward->tax_total : null;
                            $total = $inward ? $inward->grand_total : (float) $subtotal;
                        @endphp
                    @elseif ($kind === 'order')
                        @php
                            $product = $line->product;
                            $ordered = $line->quantity;
                            $received = $record->goodsReceipts->sum(function ($receipt) use ($line) {
                                return $receipt->items->where('purchase_order_item_id', $line->id)->sum('received_quantity');
                            });
                            $rejected = $record->goodsReceipts->sum(function ($receipt) use ($line) {
                                return $receipt->items->where('purchase_order_item_id', $line->id)->sum('rejected_quantity');
                            });
                            $accepted = $record->goodsReceipts->sum(function ($receipt) use ($line) {
                                return $receipt->items->where('purchase_order_item_id', $line->id)->sum('accepted_quantity');
                            });
                            $rate = $line->purchase_rate;
                            $subtotal = (float) $ordered * (float) $rate;
                            $tax = null;
                            $total = $line->total_amount;
                        @endphp
                    @else
                        @php
                            $product = $line->product;
                            $ordered = $line->quantity;
                            $received = $line->received_quantity ?: $line->quantity;
                            $rejected = $line->rejected_quantity ?: 0;
                            $accepted = $line->quantity;
                            $rate = $line->purchase_price;
                            $subtotal = $line->subtotal ?: $line->total_amount;
                            $tax = $line->tax_total;
                            $total = $line->grand_total ?: $line->total_amount;
                        @endphp
                    @endif
                    <tr>
                        <td>{{ optional($product)->name ?: '—' }}<small class="purchase-report-po-ref">{{ optional(optional($product)->category)->name ?: '—' }}</small></td>
                        <td>{{ optional($product)->product_code ?: '—' }}</td>
                        <td>{{ number_format((float) $ordered, 2) }}</td>
                        <td>{{ number_format((float) $received, 2) }}</td>
                        <td>{{ number_format((float) $rejected, 2) }}</td>
                        <td>{{ number_format((float) $accepted, 2) }}</td>
                        <td>₹{{ number_format((float) $rate, 2) }}</td>
                        <td>{{ $tax !== null && (float) $tax > 0 ? '₹' . number_format((float) $tax, 2) : '—' }}</td>
                        <td>₹{{ number_format((float) $subtotal, 2) }}</td>
                        <td>₹{{ number_format((float) $total, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table></div>
        @if ($kind === 'receipt' && $record->remarks)
            <div class="purchase-report-detail-note"><strong>Receipt remarks</strong><p>{{ $record->remarks }}</p></div>
        @elseif ($kind === 'inward' && $record->remarks)
            <div class="purchase-report-detail-note"><strong>Purchase remarks</strong><p>{{ $record->remarks }}</p></div>
        @elseif ($kind === 'order' && $record->notes)
            <div class="purchase-report-detail-note"><strong>Purchase order notes</strong><p>{{ $record->notes }}</p></div>
        @endif
    </section>
@endsection
