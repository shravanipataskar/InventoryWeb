@extends('layouts.app')

@section('title', $purchaseOrder->po_number)
@section('topbar-title', 'Purchase order')

@section('content')
    <div class="page-heading">
        <div><span class="section-kicker">PURCHASING / {{ $purchaseOrder->po_number }}</span><h1>{{ $purchaseOrder->po_number }}</h1><p>{{ optional($purchaseOrder->supplier)->name }} · {{ \Carbon\Carbon::parse($purchaseOrder->order_date)->format('d M Y') }} · {{ optional($purchaseOrder->store)->name ?: '—' }}</p></div>
        <div class="page-heading-actions">
            @if (in_array($purchaseOrder->status, ['ordered', 'pending', 'approved', 'partially_received'], true))
                <a class="button button-primary" href="{{ route('goods-receipts.create', $purchaseOrder->id) }}">Receive Goods</a>
            @endif
            <a class="button button-light" href="{{ route('purchase-orders.index') }}">Back to list</a>
        </div>
    </div>
    @if (session('success'))<div class="inventory-notice notice-in" role="status"><span class="notice-symbol">✓</span><span>{{ session('success') }}</span></div>@endif
    <div class="inventory-notice notice-in"><span class="notice-symbol">i</span><span><strong>Purchase orders do not increase current stock.</strong><small>Accepted quantities are added to Products and Current Stock after you post the Goods Received entry.</small></span></div>
    <section class="panel listing-panel">
        <div class="table-wrap"><table class="data-table listing-table">
            <thead><tr><th>CATEGORY</th><th>PRODUCT</th><th>SKU</th><th>UNIT</th><th>ORDERED</th><th>RECEIVED</th><th>REMAINING</th><th>UNIT RATE</th></tr></thead>
            <tbody>
            @foreach ($purchaseOrder->items as $item)
                <tr>
                    <td>{{ optional($item->product->category)->name ?: '—' }}</td>
                    <td><strong class="table-primary-text">{{ $item->product->name }}</strong></td>
                    <td>{{ $item->product->product_code }}</td>
                    <td>{{ optional($item->product->unit)->short_name ?: optional($item->product->unit)->name ?: '—' }}</td>
                    <td>{{ number_format($item->ordered_quantity, 2) }}</td>
                    <td>{{ number_format($item->received_quantity, 2) }}</td>
                    <td>{{ number_format(max(0, $item->ordered_quantity - $item->received_quantity), 2) }}</td>
                    <td>₹{{ number_format($item->purchase_rate, 2) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
    </section>
    <section class="panel listing-panel">
        <div class="panel-heading"><h2>Goods received</h2></div>
        <div class="table-wrap"><table class="data-table listing-table">
            <thead><tr><th>GRN</th><th>DATE</th><th>LOCATION</th><th>SUPPLIER INVOICE</th><th>RECEIVED ITEMS</th><th>ACTION</th></tr></thead>
            <tbody>
            @forelse ($purchaseOrder->goodsReceipts as $receipt)
                <tr>
                    <td><span class="unit-code">{{ $receipt->grn_number }}</span></td>
                    <td>{{ \Carbon\Carbon::parse($receipt->received_date)->format('d M Y') }}</td>
                    <td>{{ optional($receipt->store)->name ?: '—' }}</td>
                    <td>{{ $receipt->invoice_number ?: '—' }}</td>
                    <td>
                        <ul class="purchase-order-receipt-items">
                            @foreach ($receipt->items as $receiptItem)
                                <li>
                                    <strong>{{ optional($receiptItem->product)->name ?: 'Product unavailable' }}</strong>
                                    <small>
                                        Accepted: {{ number_format($receiptItem->accepted_quantity, 2) }}{{ optional(optional($receiptItem->product)->unit)->short_name ? ' ' . $receiptItem->product->unit->short_name : '' }}
                                        @if ((float) $receiptItem->rejected_quantity > 0)
                                            · Rejected: {{ number_format($receiptItem->rejected_quantity, 2) }}
                                        @endif
                                    </small>
                                </li>
                            @endforeach
                        </ul>
                    </td>
                    <td><a class="button button-small button-light" href="{{ route('goods-receipts.show', $receipt->id) }}">View</a></td>
                </tr>
            @empty
                <tr><td colspan="6">No goods receipts have been posted for this order.</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </section>
@endsection
