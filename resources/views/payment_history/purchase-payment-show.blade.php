@extends('layouts.app')

@section('title', 'Purchase Payment Details')
@section('topbar-title', 'Purchase payment details')

@section('content')
    @php
        $payable = (float) $order->grand_total;
        $paymentPercentage = $payable > 0 ? min(100, (float) $payment->amount / $payable * 100) : 0;
    @endphp
    <div class="page-heading">
        <div>
            <span class="section-kicker">PURCHASING / PAYMENT HISTORY</span>
            <h1>Purchase Payment Details</h1>
            <p>{{ 'PP-' . str_pad($payment->id, 8, '0', STR_PAD_LEFT) }} · {{ optional($order->supplier)->name ?: 'Supplier' }}</p>
        </div>
        <a class="button button-light" href="{{ route('supplier-bills.index') }}">Back to Payment History</a>
    </div>
    @include('components.flash')

    <section class="form-card">
        <div class="form-card-heading">
            <div><h2>Payment Details</h2><p>Recorded purchase payment and its current purchase balance.</p></div>
        </div>
        <div class="form-grid">
            <div class="field"><label>Payment Voucher No.</label><p>{{ 'PP-' . str_pad($payment->id, 8, '0', STR_PAD_LEFT) }}</p></div>
            <div class="field"><label>Actual Payment Date</label><p>{{ $payment->payment_date ? \Carbon\Carbon::parse($payment->payment_date)->format('d M Y') : '—' }}</p></div>
            <div class="field"><label>Supplier</label><p>{{ optional($order->supplier)->name ?: '—' }}</p></div>
            <div class="field"><label>Purchase Order</label><p>{{ $order->po_number ?: '—' }}</p></div>
            <div class="field"><label>Payment Amount</label><p>₹{{ number_format((float) $payment->amount, 2) }}</p></div>
            <div class="field"><label>Payment Percentage</label><p>{{ number_format($paymentPercentage, 2) }}% of purchase payable</p></div>
            <div class="field"><label>Purchase Payable</label><p>₹{{ number_format($payable, 2) }}</p></div>
            <div class="field"><label>Total Confirmed Paid</label><p>₹{{ number_format((float) $totalPaid, 2) }}</p></div>
            <div class="field"><label>Outstanding</label><p>₹{{ number_format((float) $outstanding, 2) }}</p></div>
            <div class="field"><label>Payment Status</label><p>@include('procurement._status', ['status' => 'paid'])</p></div>
            <div class="field"><label>Approval Status</label><p>Confirmed purchase payment</p></div>
            <div class="field"><label>Recorded By</label><p>{{ optional($payment->enteredBy)->name ?: '—' }}</p></div>
            <div class="field"><label>Payment Method</label><p>{{ $payment->payment_method ?: '—' }}</p></div>
            <div class="field"><label>Reference Number</label><p>{{ $payment->reference_number ?: '—' }}</p></div>
            <div class="field"><label>Remarks</label><p>{{ $payment->remarks ?: '—' }}</p></div>
        </div>
    </section>

    @if ($payment->billAllocations->isNotEmpty())
        <section class="form-card">
            <div class="form-card-heading"><div><h2>Related Supplier Bills</h2><p>Bill balances to which this purchase payment was applied.</p></div></div>
            <div class="table-wrap">
                <table class="data-table listing-table">
                    <thead><tr><th>Bill No.</th><th>Supplier Invoice</th><th>Bill Total</th><th>Allocated Payment</th><th>Bill Status</th><th>Action</th></tr></thead>
                    <tbody>
                    @foreach ($payment->billAllocations as $allocation)
                        <tr>
                            <td>{{ optional($allocation->bill)->bill_number ?: '—' }}</td>
                            <td>{{ optional($allocation->bill)->invoice_number ?: '—' }}</td>
                            <td>₹{{ number_format((float) optional($allocation->bill)->total_amount, 2) }}</td>
                            <td>₹{{ number_format((float) $allocation->amount, 2) }}</td>
                            <td>@if ($allocation->bill) @include('procurement._status', ['status' => $allocation->bill->display_status]) @else — @endif</td>
                            <td>@if ($allocation->bill)<a class="button button-small button-light" href="{{ route('supplier-bills.show', $allocation->bill->id) }}">View Bill</a>@else — @endif</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    <section class="form-card">
        <div class="form-card-heading"><div><h2>Related Purchase Payment History</h2><p>Confirmed direct payments recorded against this purchase order.</p></div></div>
        <div class="table-wrap">
            <table class="data-table listing-table">
                <thead><tr><th>Payment Voucher No.</th><th>Actual Payment Date</th><th>Payment Amount</th><th>Recorded By</th></tr></thead>
                <tbody>
                @foreach ($relatedHistory as $relatedPayment)
                    <tr>
                        <td><a href="{{ $relatedPayment->detail_url }}">{{ $relatedPayment->voucher_number }}</a></td>
                        <td>{{ $relatedPayment->payment_date ? \Carbon\Carbon::parse($relatedPayment->payment_date)->format('d M Y') : '—' }}</td>
                        <td>₹{{ number_format((float) $relatedPayment->amount, 2) }}</td>
                        <td>{{ $relatedPayment->recorded_by ?: '—' }}</td>
                    </tr>
                @endforeach
                @if ($relatedHistory->isEmpty())
                    <tr><td colspan="4">No related payment history.</td></tr>
                @endif
                </tbody>
            </table>
        </div>
    </section>
@endsection
