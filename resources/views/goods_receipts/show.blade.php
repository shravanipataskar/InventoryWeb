@extends('layouts.app')

@section('title', $goodsReceipt->grn_number)
@section('topbar-title', 'Goods received')

@section('content')
    <div class="page-heading">
        <div><span class="section-kicker">GOODS RECEIVED / {{ $goodsReceipt->grn_number }}</span><h1>{{ $goodsReceipt->grn_number }}</h1><p>Posted {{ \Carbon\Carbon::parse($goodsReceipt->received_date)->format('d M Y') }} into {{ optional($goodsReceipt->store)->name ?: '—' }}</p></div>
        <div class="page-heading-actions"><a class="button button-light" href="{{ route('purchase-orders.show', $goodsReceipt->purchase_order_id) }}">View Purchase Order</a><a class="button button-light" href="{{ route('goods-receipts.index') }}">Back to list</a></div>
    </div>
    @if (session('success'))<div class="inventory-notice notice-in" role="status"><span class="notice-symbol">✓</span><span>{{ session('success') }}</span></div>@endif
    <section class="panel listing-panel">
        <div class="table-wrap"><table class="data-table listing-table">
            <thead><tr><th>CATEGORY</th><th>PRODUCT / SKU</th><th>UNIT</th><th>ORDERED</th><th>RECEIVED</th><th>REJECTED</th><th>ACCEPTED / INWARD</th><th>RATE</th><th>VALUE</th></tr></thead>
            <tbody>
            @foreach ($goodsReceipt->items as $item)
                <tr>
                    <td>{{ optional($item->product->category)->name ?: '—' }}</td>
                    <td><strong>{{ $item->product->name }}</strong><small class="field-hint">{{ $item->product->product_code }}</small></td>
                    <td>{{ optional($item->product->unit)->short_name ?: optional($item->product->unit)->name ?: '—' }}</td>
                    <td>{{ number_format($item->ordered_quantity, 2) }}</td>
                    <td>{{ number_format($item->received_quantity, 2) }}</td>
                    <td>{{ number_format($item->rejected_quantity, 2) }}</td>
                    <td>{{ number_format($item->accepted_quantity, 2) }}<small class="field-hint">{{ optional($item->stockInward)->inward_number }}</small></td>
                    <td>₹{{ number_format($item->purchase_rate, 2) }}</td>
                    <td>₹{{ number_format($item->accepted_quantity * $item->purchase_rate, 2) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
    </section>
    <section class="panel listing-panel">
        <div class="form-grid">
            <div class="field"><label>Supplier</label><p>{{ optional($goodsReceipt->supplier)->name ?: '—' }}</p></div>
            <div class="field"><label>Supplier invoice</label><p>{{ $goodsReceipt->invoice_number ?: '—' }}</p></div>
            <div class="field"><label>Received by</label><p>{{ optional($goodsReceipt->receiver)->name ?: '—' }}</p></div>
            <div class="field"><label>Remarks</label><p>{{ $goodsReceipt->remarks ?: '—' }}</p></div>
        </div>
    </section>
@endsection
