@extends('layouts.app')

@section('title', $supplier->name . ' · Purchase History')
@section('topbar-title', 'Supplier history')

@section('content')
    <div class="page-heading">
        <div><span class="section-kicker">SUPPLIERS / PURCHASE &amp; PAYMENT HISTORY</span><h1>{{ $supplier->name }}</h1><p>{{ $supplier->supplier_code }}{{ $supplier->gst_number ? ' · GSTIN ' . $supplier->gst_number : '' }}</p></div>
        <a class="button button-light" href="{{ route('supplier-bills.index', ['supplier_id' => $supplier->id]) }}">Bills for this supplier</a>
    </div>

    <div class="stats-grid">
        @include('components.stat-card', ['label' => 'Ordered', 'value' => '₹' . number_format((float) $summary['ordered'], 2), 'note' => 'Non-cancelled POs (latest 50)', 'icon' => 'icon-tray-in', 'tone' => 'tone-blue'])
        @include('components.stat-card', ['label' => 'Billed', 'value' => '₹' . number_format((float) $summary['billed'], 2), 'note' => 'Approved and paid bills', 'icon' => 'icon-layers', 'tone' => 'tone-violet'])
        @include('components.stat-card', ['label' => 'Paid', 'value' => '₹' . number_format((float) $summary['paid'], 2), 'note' => 'Completed payments', 'icon' => 'icon-check', 'tone' => 'tone-teal'])
        @include('components.stat-card', ['label' => 'Outstanding', 'value' => '₹' . number_format((float) $summary['outstanding'], 2), 'note' => 'Approved, not yet paid', 'icon' => 'icon-alert', 'tone' => 'tone-gold'])
    </div>

    <section class="panel listing-panel">
        <div class="panel-heading"><h2>Purchase orders</h2></div>
        <div class="table-wrap"><table class="data-table listing-table">
            <thead><tr><th>PO</th><th>DATE</th><th>TOTAL</th><th>STATUS</th><th></th></tr></thead>
            <tbody>
            @forelse ($purchaseOrders as $order)
                <tr><td><span class="unit-code">{{ $order->po_number }}</span></td><td>{{ \Carbon\Carbon::parse($order->po_date)->format('d M Y') }}</td><td>₹{{ number_format((float) $order->grand_total, 2) }}</td><td>@include('procurement._status', ['status' => $order->status])</td><td>@if (auth()->user()->hasPermission('purchase', 'view'))<a class="button button-small button-light" href="{{ route('purchase-orders.show', $order->id) }}">View</a>@endif</td></tr>
            @empty
                <tr><td colspan="5">No purchase orders.</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </section>

    <section class="panel listing-panel">
        <div class="panel-heading"><h2>Bills</h2></div>
        <div class="table-wrap"><table class="data-table listing-table">
            <thead><tr><th>BILL</th><th>INVOICE</th><th>DUE</th><th>TOTAL</th><th>OUTSTANDING</th><th>STATUS</th><th></th></tr></thead>
            <tbody>
            @forelse ($bills as $bill)
                <tr><td><span class="unit-code">{{ $bill->bill_number }}</span></td><td>{{ $bill->invoice_number }}</td><td>{{ $bill->due_date ? \Carbon\Carbon::parse($bill->due_date)->format('d M Y') : '—' }}</td><td>₹{{ number_format((float) $bill->total_amount, 2) }}</td><td>₹{{ number_format((float) $bill->outstanding_amount, 2) }}</td><td>@include('procurement._status', ['status' => $bill->display_status])</td><td><a class="button button-small button-light" href="{{ route('supplier-bills.show', $bill->id) }}">View</a></td></tr>
            @empty
                <tr><td colspan="7">No bills.</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </section>

    <section class="panel listing-panel">
        <div class="panel-heading"><h2>Payments</h2></div>
        <div class="table-wrap"><table class="data-table listing-table">
            <thead><tr><th>PAYMENT</th><th>DATE</th><th>METHOD</th><th>REFERENCE</th><th>AMOUNT</th><th>STATUS</th><th></th></tr></thead>
            <tbody>
            @forelse ($payments as $payment)
                <tr><td><span class="unit-code">{{ $payment->payment_number }}</span></td><td>{{ $payment->payment_date ? \Carbon\Carbon::parse($payment->payment_date)->format('d M Y') : '—' }}</td><td>{{ $payment->method_label }}</td><td>{{ $payment->reference_number ?: '—' }}</td><td>₹{{ number_format((float) $payment->amount, 2) }}</td><td>@include('procurement._status', ['status' => $payment->status])</td><td>@if (auth()->user()->hasPermission('payments', 'view'))<a class="button button-small button-light" href="{{ route('supplier-payments.show', $payment->id) }}">View</a>@endif</td></tr>
            @empty
                <tr><td colspan="7">No payments.</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </section>
@endsection
