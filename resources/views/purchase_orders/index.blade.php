@extends('layouts.app')

@section('title', 'Purchase Orders')
@section('topbar-title', 'Purchase orders')

@section('content')
    <div class="page-heading">
        <div>
            <span class="section-kicker">PURCHASING</span>
            <h1>Purchase Orders</h1>
            <p>Track ordered quantities and receive goods into a selected store.</p>
        </div>
        <a class="button button-primary" href="{{ route('purchase-orders.create') }}"><span class="button-plus">+</span> New Purchase Order</a>
    </div>

    @if (session('success'))<div class="inventory-notice notice-in" role="status"><span class="notice-symbol">✓</span><span>{{ session('success') }}</span></div>@endif
    @if (session('error'))<div class="form-alert" role="alert">{{ session('error') }}</div>@endif

    <section class="panel listing-panel">
        <div class="table-wrap">
            <table class="data-table listing-table">
                <thead><tr><th>PO NUMBER</th><th>ORDER DATE</th><th>EXPECTED</th><th>SUPPLIER</th><th>ITEMS</th><th>STATUS</th><th>ACTIONS</th></tr></thead>
                <tbody>
                @forelse ($purchaseOrders as $order)
                    <tr>
                        <td><span class="unit-code">{{ $order->po_number }}</span></td>
                        <td>{{ \Carbon\Carbon::parse($order->order_date)->format('d M Y') }}</td>
                        <td>{{ $order->expected_date ? \Carbon\Carbon::parse($order->expected_date)->format('d M Y') : '—' }}</td>
                        <td>{{ optional($order->supplier)->name ?: '—' }}</td>
                        <td>{{ $order->items_count }}</td>
                        <td>@include('components.status-badge', ['status' => ucfirst(str_replace('_', ' ', $order->status))])</td>
                        <td><a class="button button-small button-light" href="{{ route('purchase-orders.show', $order->id) }}">View</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7">@include('components.empty-state', ['icon' => 'icon-tray-in', 'title' => 'No purchase orders', 'message' => 'Create a purchase order before receiving stock.'])</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($purchaseOrders->hasPages())
            <div class="table-footer">{{ $purchaseOrders->links() }}</div>
        @endif
    </section>
@endsection
