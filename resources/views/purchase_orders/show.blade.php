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
            @if (!empty($billingEnabled) && $purchaseOrder->status !== 'cancelled' && $purchaseOrder->goodsReceipts->isNotEmpty() && auth()->user()->hasPermission('bills', 'create'))
                <a class="button button-light" href="{{ route('supplier-bills.create', $purchaseOrder->id) }}">Enter Supplier Bill</a>
            @endif
            <a class="button button-light" href="{{ route('purchase-orders.index') }}">Back to list</a>
        </div>
    </div>
    @if (session('success'))<div class="inventory-notice notice-in" role="status"><span class="notice-symbol">✓</span><span>{{ session('success') }}</span></div>@endif
    @if (session('error'))<div class="form-alert" role="alert">{{ session('error') }}</div>@endif
    @if ($errors->any())<div class="form-alert" role="alert"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <section class="panel listing-panel">
        <div class="form-grid">
            <div class="field"><label>Status</label><p>@include('procurement._status', ['status' => $purchaseOrder->status]){{ $purchaseOrder->status_reason ? ' · ' . $purchaseOrder->status_reason : '' }}</p></div>
            <div class="field"><label>Source quotation</label><p>@if ($purchaseOrder->quotation)<a href="{{ route('quotations.show', $purchaseOrder->quotation->quotation_request_id) }}">{{ $purchaseOrder->quotation->quotation_code }}</a>{{ optional($purchaseOrder->quotation->request)->quotation_request_code ? ' · ' . $purchaseOrder->quotation->request->quotation_request_code : '' }}@else Created manually @endif</p></div>
            <div class="field"><label>Order value</label><p>₹{{ number_format((float) $purchaseOrder->grand_total, 2) }}</p></div>
            <div class="field"><label>Created by</label><p>{{ optional($purchaseOrder->creator)->name ?: '—' }}</p></div>
        </div>
    </section>
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
    @if (!empty($billingEnabled))
        <section class="panel listing-panel">
            <div class="panel-heading"><h2>Supplier bills &amp; payments</h2></div>
            <div class="table-wrap"><table class="data-table listing-table">
                <thead><tr><th>BILL</th><th>SUPPLIER INVOICE</th><th>TOTAL</th><th>PAID</th><th>OUTSTANDING</th><th>PAYMENTS</th><th>STATUS</th><th>ACTION</th></tr></thead>
                <tbody>
                @forelse ($purchaseOrder->supplierBills as $bill)
                    <tr>
                        <td><span class="unit-code">{{ $bill->bill_number }}</span></td>
                        <td>{{ $bill->invoice_number }}</td>
                        <td>₹{{ number_format((float) $bill->total_amount, 2) }}</td>
                        <td>₹{{ number_format((float) $bill->paid_amount, 2) }}</td>
                        <td>₹{{ number_format((float) $bill->outstanding_amount, 2) }}</td>
                        <td>{{ $bill->allocations->map(function ($allocation) { return $allocation->payment->payment_number . ' (' . str_replace('_', ' ', $allocation->payment->status) . ')'; })->implode(', ') ?: '—' }}</td>
                        <td>@include('procurement._status', ['status' => $bill->display_status])</td>
                        <td>@if (auth()->user()->hasPermission('bills', 'view'))<a class="button button-small button-light" href="{{ route('supplier-bills.show', $bill->id) }}">View</a>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="8">No supplier bills have been entered for this order.</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </section>
        @if (auth()->user()->hasPermission('purchase', 'approve') && in_array($purchaseOrder->status, ['ordered', 'pending', 'approved', 'partially_received', 'received'], true))
            <section class="panel listing-panel">
                <div class="panel-heading"><h2>Order control</h2><p>Purchase order lines cannot be edited. Cancel an order before any goods are received, or close it to stop further receipts.</p></div>
                <div class="form-actions">
                    @if (in_array($purchaseOrder->status, ['ordered', 'pending', 'approved'], true) && $purchaseOrder->goodsReceipts->isEmpty())
                        <form class="inline-form" method="POST" action="{{ route('purchase-orders.cancel', $purchaseOrder->id) }}" data-submit-once>@csrf<input class="field-control" type="text" name="reason" placeholder="Cancellation reason" required><button class="button button-small button-danger" type="submit">Cancel Purchase Order</button></form>
                    @endif
                    @if (in_array($purchaseOrder->status, ['partially_received', 'received'], true))
                        <form class="inline-form" method="POST" action="{{ route('purchase-orders.close', $purchaseOrder->id) }}" data-submit-once>@csrf<input class="field-control" type="text" name="reason" placeholder="Closing reason" required><button class="button button-small button-light" type="submit">Close Purchase Order</button></form>
                    @endif
                </div>
            </section>
        @endif
        @include('procurement._history', ['history' => $history])
        @include('procurement._submit_once')
    @endif
@endsection
