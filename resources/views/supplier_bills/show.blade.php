@extends('layouts.app')

@php
    $user = auth()->user();
    $isOwnBill = (int) $bill->created_by === (int) $user->id;
    $editable = in_array($bill->status, \App\SupplierBill::EDITABLE_STATUSES, true);
    $availableMinor = max(0, $bill->outstandingMinor() - $reservedMinor);
@endphp

@section('title', $bill->bill_number)
@section('topbar-title', 'Supplier bill')

@section('content')
    <div class="page-heading">
        <div>
            <span class="section-kicker">SUPPLIER BILLS / {{ $bill->bill_number }}</span>
            <h1>{{ $bill->bill_number }} @include('procurement._status', ['status' => $bill->display_status])</h1>
            <p>Invoice {{ $bill->invoice_number }} · {{ optional($bill->supplier)->name }} · {{ \Carbon\Carbon::parse($bill->invoice_date)->format('d M Y') }}{{ $bill->due_date ? ' · Due ' . \Carbon\Carbon::parse($bill->due_date)->format('d M Y') : '' }}</p>
        </div>
        <div class="page-heading-actions">
            @if ($editable && $user->hasPermission('bills', 'edit'))<a class="button button-light" href="{{ route('supplier-bills.edit', $bill->id) }}">Correct Bill</a>@endif
            @if (in_array($bill->status, \App\SupplierBill::PAYABLE_STATUSES, true) && $availableMinor > 0 && $user->hasPermission('payments', 'create'))
                <a class="button button-primary" href="{{ route('supplier-payments.create', ['supplier_id' => $bill->supplier_id, 'bill_id' => $bill->id]) }}">Request Payment</a>
            @endif
            <a class="button button-light" href="{{ route('suppliers.purchase-history', $bill->supplier_id) }}">Supplier History</a>
            <a class="button button-light" href="{{ route('purchase-orders.show', $bill->purchase_order_id) }}">View Purchase Order</a>
            <a class="button button-light" href="{{ route('supplier-bills.index') }}">Back to list</a>
        </div>
    </div>
    @include('components.flash')
    @if ($errors->any())<div class="form-alert" role="alert"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <div class="stats-grid">
        @include('components.stat-card', ['label' => 'Payable total', 'value' => '₹' . number_format((float) $bill->total_amount, 2), 'note' => 'Invoice as printed: ₹' . number_format((float) $bill->invoice_total, 2), 'icon' => 'icon-tray-in', 'tone' => 'tone-teal'])
        @include('components.stat-card', ['label' => 'Paid', 'value' => '₹' . number_format((float) $bill->paid_amount, 2), 'note' => 'PO advances and completed payments', 'icon' => 'icon-check', 'tone' => 'tone-blue'])
        @include('components.stat-card', ['label' => 'Outstanding', 'value' => '₹' . number_format((float) $bill->outstanding_amount, 2), 'note' => '₹' . \App\Support\Money::fromMinor($reservedMinor) . ' reserved by pending requests', 'icon' => 'icon-alert', 'tone' => $bill->isOverdue() ? 'tone-red' : 'tone-gold'])
    </div>

    <section class="panel listing-panel">
        <div class="panel-heading"><h2>Three-way match: @include('procurement._status', ['status' => $bill->match_status])</h2><p>Purchase order (price, tax) × goods received (accepted quantity) × supplier invoice.{{ $bill->exception_approved ? ' Discrepancies were approved as an exception.' : '' }}</p></div>
        @php($issues = $bill->match_result['issues'] ?? [])
        @if ($issues)
            <div class="form-alert" role="alert"><ul>@foreach ($issues as $issue)<li>{{ $issue['message'] }}</li>@endforeach</ul></div>
        @else
            <div class="inventory-notice notice-in"><span class="notice-symbol">✓</span><span>No discrepancies found.</span></div>
        @endif
        @if ($bill->status_reason)<p><strong>Latest reason:</strong> {{ $bill->status_reason }}</p>@endif
    </section>

    @if ($editable && ($user->hasPermission('bills', 'approve') || $user->hasPermission('bills', 'reject')))
        <section class="panel listing-panel">
            <div class="panel-heading"><h2>Review</h2><p>{{ $isOwnBill ? 'You submitted this bill, so another authorised user must approve it.' : 'Approving a bill with discrepancies requires a reason and is recorded as an exception.' }}</p></div>
            <div class="form-actions">
                @if ($user->hasPermission('bills', 'approve') && !$isOwnBill)
                    <form class="inline-form" method="POST" action="{{ route('supplier-bills.approve', $bill->id) }}" data-submit-once>@csrf
                        @if ($bill->match_status !== 'matched' || $bill->status !== 'under_verification')<input class="field-control" type="text" name="reason" placeholder="Exception approval reason" required>@endif
                        <button class="button button-primary" type="submit">{{ $bill->match_status === 'matched' && $bill->status === 'under_verification' ? 'Approve for Payment' : 'Approve as Exception' }}</button>
                    </form>
                @endif
                @if ($user->hasPermission('bills', 'reject'))
                    @if ($bill->status !== 'disputed')
                        <form class="inline-form" method="POST" action="{{ route('supplier-bills.dispute', $bill->id) }}" data-submit-once>@csrf<input class="field-control" type="text" name="reason" placeholder="Dispute reason" required><button class="button button-light" type="submit">Mark Disputed</button></form>
                    @endif
                    <form class="inline-form" method="POST" action="{{ route('supplier-bills.reject', $bill->id) }}" data-submit-once>@csrf<input class="field-control" type="text" name="reason" placeholder="Rejection reason" required><button class="button button-small button-deactivate" type="submit">Reject Bill</button></form>
                @endif
            </div>
        </section>
    @endif

    <section class="panel listing-panel">
        <div class="panel-heading"><h2>Billed items</h2></div>
        <div class="table-wrap"><table class="data-table listing-table">
            <thead><tr><th>PRODUCT</th><th>QTY</th><th>RATE</th><th>PO RATE</th><th>DISC %</th><th>TAX %</th><th>BASIC</th><th>TAX</th><th>TOTAL</th></tr></thead>
            <tbody>
            @foreach ($bill->items as $item)
                <tr>
                    <td><strong>{{ optional($item->product)->name }}</strong></td>
                    <td>{{ number_format((float) $item->quantity, 2) }} {{ optional(optional($item->product)->unit)->short_name }}</td>
                    <td>₹{{ number_format((float) $item->rate, 2) }}</td>
                    <td>₹{{ number_format((float) optional($item->purchaseOrderItem)->purchase_rate, 2) }}</td>
                    <td>{{ number_format((float) $item->discount_percent, 2) }}</td>
                    <td>{{ number_format((float) $item->tax_percent, 2) }}</td>
                    <td>₹{{ number_format((float) $item->basic_amount, 2) }}</td>
                    <td>₹{{ number_format((float) $item->tax_amount, 2) }}</td>
                    <td>₹{{ number_format((float) $item->total_amount, 2) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
        <div class="form-grid">
            <div class="field"><label>Purchase order</label><p><a href="{{ route('purchase-orders.show', $bill->purchase_order_id) }}">{{ optional($bill->purchaseOrder)->po_number }}</a>{{ optional(optional($bill->purchaseOrder)->quotation)->quotation_code ? ' · from ' . $bill->purchaseOrder->quotation->quotation_code : '' }}</p></div>
            <div class="field"><label>Submitted by</label><p>{{ optional($bill->creator)->name ?: '—' }}</p></div>
            <div class="field"><label>Approved by</label><p>{{ optional($bill->approver)->name ?: '—' }}{{ $bill->approved_at ? ' · ' . $bill->approved_at->format('d M Y H:i') : '' }}</p></div>
            <div class="field"><label>Invoice document</label><p>@if ($bill->attachment_path)<a href="{{ route('supplier-bills.attachment', $bill->id) }}">{{ $bill->attachment_name ?: 'Download' }}</a>@else — @endif</p></div>
            <div class="field"><label>Notes</label><p>{{ $bill->notes ?: '—' }}</p></div>
        </div>
    </section>

    <section class="panel listing-panel">
        <div class="panel-heading"><h2>Payments</h2></div>
        <div class="table-wrap"><table class="data-table listing-table">
            <thead><tr><th>PAYMENT</th><th>METHOD</th><th>DATE</th><th>REFERENCE</th><th>ALLOCATED</th><th>STATUS</th><th></th></tr></thead>
            <tbody>
            @forelse ($bill->allocations as $allocation)
                <tr>
                    <td><span class="unit-code">{{ $allocation->payment->payment_number }}</span></td>
                    <td>{{ $allocation->payment->method_label }}</td>
                    <td>{{ $allocation->payment->payment_date ? \Carbon\Carbon::parse($allocation->payment->payment_date)->format('d M Y') : '—' }}</td>
                    <td>{{ $allocation->payment->reference_number ?: '—' }}</td>
                    <td>₹{{ number_format((float) $allocation->amount, 2) }}</td>
                    <td>@include('procurement._status', ['status' => $allocation->payment->status])</td>
                    <td><a class="button button-small button-light" href="{{ route('supplier-payments.show', $allocation->payment->id) }}">View</a></td>
                </tr>
            @empty
                @if ($bill->purchasePaymentAllocations->isEmpty())
                <tr><td colspan="7">No payments have been requested for this bill.</td></tr>
                @endif
            @endforelse
            @foreach ($bill->purchasePaymentAllocations as $allocation)
                <tr>
                    <td><span class="unit-code">PO Advance #{{ $allocation->purchase_payment_id }}</span></td>
                    <td>{{ $allocation->purchasePayment->payment_method }}</td>
                    <td>{{ \Carbon\Carbon::parse($allocation->purchasePayment->payment_date)->format('d M Y') }}</td>
                    <td>{{ $allocation->purchasePayment->reference_number ?: '—' }}</td>
                    <td>₹{{ number_format((float) $allocation->amount, 2) }}</td>
                    <td>@include('procurement._status', ['status' => $allocation->purchasePayment->status])</td>
                    <td><a class="button button-small button-light" href="{{ route('purchase-orders.show', $bill->purchase_order_id) }}">View PO</a></td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
    </section>

    @if ($canCancel && $user->hasPermission('bills', 'approve'))
        <section class="panel listing-panel">
            <div class="panel-heading"><h2>Cancel bill</h2><p>Cancelling keeps the record and its history and frees the invoice number for a corrected bill.</p></div>
            <form class="inline-form" method="POST" action="{{ route('supplier-bills.cancel', $bill->id) }}" data-submit-once>@csrf<input class="field-control" type="text" name="reason" placeholder="Cancellation reason" required><button class="button button-small button-danger" type="submit">Cancel Bill</button></form>
        </section>
    @endif

    @include('procurement._history', ['history' => $history])
    @include('procurement._submit_once')
@endsection
