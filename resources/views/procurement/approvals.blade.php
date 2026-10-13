@extends('layouts.app')

@section('title', 'Pending Approvals')
@section('topbar-title', 'Pending approvals')

@section('content')
    <div class="page-heading">
        <div><span class="section-kicker">PURCHASING / APPROVALS</span><h1>Pending Approvals</h1><p>Supplier bills awaiting verification and payment requests awaiting approval.</p></div>
    </div>
    @include('components.flash')

    @if ($canBills)
        <section class="panel listing-panel">
            <div class="panel-heading"><h2>Supplier bills ({{ $bills->count() }})</h2><p>On-hold and disputed bills have discrepancies that need an exception approval, correction or rejection.</p></div>
            <div class="table-wrap"><table class="data-table listing-table">
                <thead><tr><th>BILL</th><th>SUPPLIER / INVOICE</th><th>PO</th><th>TOTAL</th><th>ISSUES</th><th>SUBMITTED BY</th><th>STATUS</th><th></th></tr></thead>
                <tbody>
                @forelse ($bills as $bill)
                    <tr>
                        <td><span class="unit-code">{{ $bill->bill_number }}</span></td>
                        <td>{{ optional($bill->supplier)->name }}<small class="field-hint">{{ $bill->invoice_number }}</small></td>
                        <td>{{ optional($bill->purchaseOrder)->po_number }}</td>
                        <td>₹{{ number_format((float) $bill->total_amount, 2) }}</td>
                        <td>{{ count($bill->match_result['issues'] ?? []) }}</td>
                        <td>{{ optional($bill->creator)->name ?: '—' }}</td>
                        <td>@include('procurement._status', ['status' => $bill->status])</td>
                        <td><a class="button button-small button-light" href="{{ route('supplier-bills.show', $bill->id) }}">Review</a></td>
                    </tr>
                @empty
                    <tr><td colspan="8">No bills are waiting for review.</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </section>
    @endif

    @if ($canPayments)
        <section class="panel listing-panel">
            <div class="panel-heading"><h2>Payment requests ({{ $payments->count() }})</h2></div>
            <div class="table-wrap"><table class="data-table listing-table">
                <thead><tr><th>PAYMENT</th><th>SUPPLIER</th><th>BILLS</th><th>METHOD</th><th>AMOUNT</th><th>REQUESTED BY</th><th></th></tr></thead>
                <tbody>
                @forelse ($payments as $payment)
                    <tr>
                        <td><span class="unit-code">{{ $payment->payment_number }}</span></td>
                        <td>{{ optional($payment->supplier)->name }}</td>
                        <td>{{ $payment->allocations->map(function ($allocation) { return optional($allocation->bill)->bill_number; })->filter()->implode(', ') }}</td>
                        <td>{{ $payment->method_label }}</td>
                        <td>₹{{ number_format((float) $payment->amount, 2) }}</td>
                        <td>{{ optional($payment->requester)->name ?: '—' }}</td>
                        <td><a class="button button-small button-light" href="{{ route('supplier-payments.show', $payment->id) }}">Review</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7">No payment requests are waiting for approval.</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </section>
    @endif
@endsection
