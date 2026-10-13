@extends('layouts.app')

@php
    $user = auth()->user();
    $isOwnRequest = (int) $payment->requested_by === (int) $user->id;
@endphp

@section('title', $payment->payment_number)
@section('topbar-title', 'Supplier payment')

@section('content')
    <div class="page-heading">
        <div>
            <span class="section-kicker">SUPPLIER PAYMENTS / {{ $payment->payment_number }}</span>
            <h1>{{ $payment->payment_number }} @include('procurement._status', ['status' => $payment->status])</h1>
            <p>{{ optional($payment->supplier)->name }} · ₹{{ number_format((float) $payment->amount, 2) }} · {{ $payment->method_label }}</p>
        </div>
        <div class="page-heading-actions"><a class="button button-light" href="{{ route('supplier-payments.index') }}">Back to payments</a></div>
    </div>
    @include('components.flash')
    @if ($errors->any())<div class="form-alert" role="alert"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    @if ($payment->status === 'pending_approval' && ($user->hasPermission('payments', 'approve') || $user->hasPermission('payments', 'reject')))
        <section class="panel listing-panel">
            <div class="panel-heading"><h2>Approval</h2><p>{{ $isOwnRequest ? 'You requested this payment, so another authorised user must approve it.' : 'Approve only after checking the bills below.' }}</p></div>
            <div class="form-actions">
                @if ($user->hasPermission('payments', 'approve') && !$isOwnRequest)
                    <form class="inline-form" method="POST" action="{{ route('supplier-payments.approve', $payment->id) }}" data-submit-once>@csrf<button class="button button-primary" type="submit">Approve Payment</button></form>
                @endif
                @if ($user->hasPermission('payments', 'reject'))
                    <form class="inline-form" method="POST" action="{{ route('supplier-payments.reject', $payment->id) }}" data-submit-once>@csrf<input class="field-control" type="text" name="reason" placeholder="Rejection reason" required><button class="button button-small button-deactivate" type="submit">Reject</button></form>
                @endif
            </div>
        </section>
    @endif

    @if ($payment->status === 'approved' && $user->hasPermission('payments', 'edit'))
        <section class="form-card">
            <div class="form-card-heading"><div><h2>Record completed payment</h2><p>Enter the details after the transfer has actually been made.</p></div></div>
            <form method="POST" action="{{ route('supplier-payments.complete', $payment->id) }}" enctype="multipart/form-data" data-submit-once>
                @csrf
                <div class="form-grid">
                    <div class="field"><label for="payment_date">Payment date <span class="required-mark">*</span></label><input class="field-control" id="payment_date" name="payment_date" type="date" max="{{ date('Y-m-d') }}" value="{{ old('payment_date', date('Y-m-d')) }}" required></div>
                    <div class="field"><label for="reference_number">{{ $payment->payment_method === 'cheque' ? 'Cheque number' : 'UTR / transaction reference' }} @if ($payment->payment_method !== 'cash')<span class="required-mark">*</span>@endif</label><input class="field-control" id="reference_number" name="reference_number" type="text" maxlength="100" value="{{ old('reference_number') }}" {{ $payment->payment_method !== 'cash' ? 'required' : '' }}></div>
                    <div class="field"><label for="account_reference">Paying account reference</label><input class="field-control" id="account_reference" name="account_reference" type="text" maxlength="100" value="{{ old('account_reference', $payment->account_reference) }}"></div>
                    <div class="field"><label for="proof">Payment proof (PDF/JPG/PNG/WEBP)</label><input class="field-control" id="proof" name="proof" type="file" accept=".pdf,.jpg,.jpeg,.png,.webp"></div>
                </div>
                <div class="form-actions"><button class="button button-primary" type="submit">Mark as Completed</button></div>
            </form>
            <div class="form-actions">
                <form class="inline-form" method="POST" action="{{ route('supplier-payments.fail', $payment->id) }}" data-submit-once>@csrf<input class="field-control" type="text" name="reason" placeholder="Failure reason" required><button class="button button-small button-deactivate" type="submit">Record Failed</button></form>
            </div>
        </section>
    @endif

    @if (in_array($payment->status, ['pending_approval', 'approved'], true) && $user->hasPermission('payments', 'edit'))
        <section class="panel listing-panel">
            <form class="inline-form" method="POST" action="{{ route('supplier-payments.cancel', $payment->id) }}" data-submit-once>@csrf<input class="field-control" type="text" name="reason" placeholder="Cancellation reason" required><button class="button button-small button-danger" type="submit">Cancel Request</button></form>
        </section>
    @endif

    @if ($payment->status === 'completed' && $user->hasPermission('payments', 'approve'))
        <section class="panel listing-panel">
            <div class="panel-heading"><h2>Reverse payment</h2><p>Use when a completed payment bounced or was returned. The bill balance is restored; the record is kept.</p></div>
            <form class="inline-form" method="POST" action="{{ route('supplier-payments.reverse', $payment->id) }}" data-submit-once>@csrf<input class="field-control" type="text" name="reason" placeholder="Reversal reason" required><button class="button button-small button-danger" type="submit">Reverse Payment</button></form>
        </section>
    @endif

    <section class="panel listing-panel">
        <div class="panel-heading"><h2>Allocated bills</h2></div>
        <div class="table-wrap"><table class="data-table listing-table">
            <thead><tr><th>BILL</th><th>INVOICE</th><th>BILL TOTAL</th><th>BILL OUTSTANDING</th><th>ALLOCATED</th><th>BILL STATUS</th></tr></thead>
            <tbody>
            @foreach ($payment->allocations as $allocation)
                <tr>
                    <td><a href="{{ route('supplier-bills.show', $allocation->supplier_bill_id) }}">{{ $allocation->bill->bill_number }}</a></td>
                    <td>{{ $allocation->bill->invoice_number }}</td>
                    <td>₹{{ number_format((float) $allocation->bill->total_amount, 2) }}</td>
                    <td>₹{{ number_format((float) $allocation->bill->outstanding_amount, 2) }}</td>
                    <td>₹{{ number_format((float) $allocation->amount, 2) }}</td>
                    <td>@include('procurement._status', ['status' => $allocation->bill->display_status])</td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
        <div class="form-grid">
            <div class="field"><label>Requested by</label><p>{{ optional($payment->requester)->name ?: '—' }} · {{ optional($payment->created_at)->format('d M Y H:i') }}</p></div>
            <div class="field"><label>Approved by</label><p>{{ optional($payment->approver)->name ?: '—' }}{{ $payment->approved_at ? ' · ' . $payment->approved_at->format('d M Y H:i') : '' }}</p></div>
            <div class="field"><label>Recorded by</label><p>{{ optional($payment->completer)->name ?: '—' }}{{ $payment->completed_at ? ' · ' . $payment->completed_at->format('d M Y H:i') : '' }}</p></div>
            <div class="field"><label>Payment date / reference</label><p>{{ $payment->payment_date ? \Carbon\Carbon::parse($payment->payment_date)->format('d M Y') : '—' }} · {{ $payment->reference_number ?: '—' }}</p></div>
            <div class="field"><label>Account reference</label><p>{{ $payment->account_reference ?: '—' }}</p></div>
            <div class="field"><label>Proof</label><p>@if ($payment->proof_path)<a href="{{ route('supplier-payments.proof', $payment->id) }}">{{ $payment->proof_name ?: 'Download' }}</a>@else — @endif</p></div>
            <div class="field"><label>Notes</label><p>{{ $payment->notes ?: '—' }}</p></div>
            @if ($payment->status_reason)<div class="field"><label>Status reason</label><p>{{ $payment->status_reason }}</p></div>@endif
        </div>
    </section>

    @include('procurement._history', ['history' => $history])
    @include('procurement._submit_once')
@endsection
