@extends('layouts.app')

@section('title', 'Payment Requests')
@section('topbar-title', 'Payment requests')

@section('content')
    <div class="page-heading">
        <div>
            <span class="section-kicker">PURCHASING / PAYABLES</span>
            <h1>Payment Requests</h1>
            <p>Review payment requests, approvals, and completion status for supplier bills.</p>
        </div>
        @if (auth()->user()->hasPermission('payments', 'create'))
            <a class="button button-primary" href="{{ route('supplier-payments.create') }}"><span class="button-plus">+</span> New Payment Request</a>
        @endif
    </div>
    @include('components.flash')

    <section class="panel listing-panel">
        <form class="table-toolbar table-toolbar-wrap" method="GET" action="{{ route('supplier-payments.index') }}">
            <select class="filter-select" name="supplier_id" aria-label="Filter by supplier" onchange="this.form.submit()"><option value="">All suppliers</option>@foreach ($suppliers as $supplier)<option value="{{ $supplier->id }}" {{ (int) request('supplier_id') === $supplier->id ? 'selected' : '' }}>{{ $supplier->name }}</option>@endforeach</select>
            <select class="filter-select" name="status" aria-label="Filter by status" onchange="this.form.submit()"><option value="">All statuses</option>@foreach (['pending_approval', 'approved', 'completed', 'failed', 'rejected', 'cancelled', 'reversed'] as $status)<option value="{{ $status }}" {{ request('status') === $status ? 'selected' : '' }}>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>@endforeach</select>
        </form>
        <div class="table-wrap">
            <table class="data-table listing-table">
                <thead><tr><th>PAYMENT</th><th>SUPPLIER</th><th>BILLS</th><th>METHOD</th><th>DATE</th><th>REFERENCE</th><th>AMOUNT</th><th>STATUS</th><th>ACTIONS</th></tr></thead>
                <tbody>
                @forelse ($payments as $payment)
                    <tr>
                        <td><span class="unit-code">{{ $payment->payment_number }}</span></td>
                        <td>{{ optional($payment->supplier)->name ?: '—' }}</td>
                        <td>{{ $payment->allocations->map(function ($allocation) { return optional($allocation->bill)->bill_number; })->filter()->implode(', ') }}</td>
                        <td>{{ $payment->method_label }}</td>
                        <td>{{ $payment->payment_date ? \Carbon\Carbon::parse($payment->payment_date)->format('d M Y') : '—' }}</td>
                        <td>{{ $payment->reference_number ?: '—' }}</td>
                        <td>₹{{ number_format((float) $payment->amount, 2) }}</td>
                        <td>@include('procurement._status', ['status' => $payment->status])</td>
                        <td><a class="button button-small button-light" href="{{ route('supplier-payments.show', $payment->id) }}">View</a></td>
                    </tr>
                @empty
                    <tr><td colspan="9">@include('components.empty-state', ['icon' => 'icon-tray-out', 'title' => 'No payments', 'message' => 'Payment requests for approved supplier bills appear here.'])</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($payments->hasPages())
            <div class="table-footer">{{ $payments->links() }}</div>
        @endif
    </section>
@endsection
