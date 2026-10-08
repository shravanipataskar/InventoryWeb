@extends('layouts.app')

@section('title', 'Goods Received')
@section('topbar-title', 'Goods received')

@section('content')
    <div class="page-heading">
        <div><span class="section-kicker">PURCHASING / RECEIVING</span><h1>Goods Received</h1><p>Post receipts against purchase orders. Accepted quantities create stock inward automatically.</p></div>
        <a class="button button-light" href="{{ route('purchase-orders.index') }}">View Purchase Orders</a>
    </div>
    @if (session('success'))<div class="inventory-notice notice-in" role="status"><span class="notice-symbol">✓</span><span>{{ session('success') }}</span></div>@endif
    <section class="panel listing-panel">
        <div class="table-wrap"><table class="data-table listing-table">
            <thead><tr><th>GRN NUMBER</th><th>DATE</th><th>PO NUMBER</th><th>SUPPLIER</th><th>LOCATION</th><th>ITEMS</th><th>STATUS</th><th>ACTION</th></tr></thead>
            <tbody>
            @forelse ($goodsReceipts as $receipt)
                <tr>
                    <td><span class="unit-code">{{ $receipt->grn_number }}</span></td>
                    <td>{{ \Carbon\Carbon::parse($receipt->received_date)->format('d M Y') }}</td>
                    <td>{{ optional($receipt->purchaseOrder)->po_number ?: '—' }}</td>
                    <td>{{ optional($receipt->supplier)->name ?: '—' }}</td>
                    <td>{{ optional($receipt->store)->name ?: '—' }}</td>
                    <td>{{ $receipt->items_count }}</td>
                    <td>@include('components.status-badge', ['status' => ucfirst($receipt->status)])</td>
                    <td><a class="button button-small button-light" href="{{ route('goods-receipts.show', $receipt->id) }}">View</a></td>
                </tr>
            @empty
                <tr><td colspan="8">@include('components.empty-state', ['icon' => 'icon-tray-in', 'title' => 'No goods received yet', 'message' => 'Post a receipt from an open purchase order to automatically add accepted quantities to stock.'])</td></tr>
            @endforelse
            </tbody>
        </table></div>
        @if ($goodsReceipts->hasPages())<div class="table-footer">{{ $goodsReceipts->links() }}</div>@endif
    </section>
@endsection
