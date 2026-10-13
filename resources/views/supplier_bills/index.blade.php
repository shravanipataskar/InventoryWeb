@extends('layouts.app')

@section('title', 'Supplier Bills')
@section('topbar-title', 'Supplier bills')

@section('content')
    <div class="page-heading">
        <div>
            <span class="section-kicker">PURCHASING / PAYABLES</span>
            <h1>Supplier Bills</h1>
            <p>Supplier invoices matched against purchase orders and goods received. Create a bill from a purchase order.</p>
        </div>
        @if (auth()->user()->hasPermission('payments', 'create'))
            <a class="button button-primary" href="{{ route('supplier-payments.create') }}"><span class="button-plus">+</span> New Payment Request</a>
        @endif
    </div>
    @include('components.flash')

    <div class="stats-grid">
        @include('components.stat-card', ['label' => 'Outstanding', 'value' => '₹' . number_format((float) $summary['outstanding'], 2), 'note' => 'Approved bills not yet paid', 'icon' => 'icon-tray-in', 'tone' => 'tone-teal'])
        @include('components.stat-card', ['label' => 'Overdue', 'value' => '₹' . number_format((float) $summary['overdue'], 2), 'note' => $summary['overdue_count'] . ' bill(s) past due date', 'icon' => 'icon-alert', 'tone' => 'tone-red'])
        @include('components.stat-card', ['label' => 'Awaiting review', 'value' => number_format($summary['awaiting']), 'note' => 'Under verification, on hold or disputed', 'icon' => 'icon-check', 'tone' => 'tone-gold'])
    </div>

    <section class="panel listing-panel">
        <form class="table-toolbar table-toolbar-wrap" method="GET" action="{{ route('supplier-bills.index') }}">
            <label class="search-field search-field-grow"><span class="search-glyph" aria-hidden="true">⌕</span><input type="search" name="search" value="{{ request('search') }}" placeholder="Search bill or invoice number..." aria-label="Search bills"></label>
            <select class="filter-select" name="supplier_id" aria-label="Filter by supplier" onchange="this.form.submit()"><option value="">All suppliers</option>@foreach ($suppliers as $supplier)<option value="{{ $supplier->id }}" {{ (int) request('supplier_id') === $supplier->id ? 'selected' : '' }}>{{ $supplier->name }}</option>@endforeach</select>
            <input type="hidden" name="filter" value="{{ $filter }}">
            <div class="status-tabs" aria-label="Bill status">
                @foreach (['all' => 'All', 'outstanding' => 'Outstanding', 'overdue' => 'Overdue', 'under_verification' => 'Under verification', 'on_hold' => 'On hold', 'paid' => 'Paid'] as $key => $label)
                    <a class="status-tab {{ ($filter ?: 'all') === $key ? 'is-selected' : '' }}" href="{{ route('supplier-bills.index', array_merge(request()->except('page', 'filter'), ['filter' => $key])) }}">{{ $label }}</a>
                @endforeach
            </div>
        </form>
        <div class="table-wrap">
            <table class="data-table listing-table">
                <thead><tr><th>BILL</th><th>SUPPLIER INVOICE</th><th>SUPPLIER</th><th>PO</th><th>DUE</th><th>TOTAL</th><th>OUTSTANDING</th><th>STATUS</th><th>ACTIONS</th></tr></thead>
                <tbody>
                @forelse ($bills as $bill)
                    <tr>
                        <td><span class="unit-code">{{ $bill->bill_number }}</span></td>
                        <td>{{ $bill->invoice_number }}<small class="field-hint">{{ \Carbon\Carbon::parse($bill->invoice_date)->format('d M Y') }}</small></td>
                        <td>{{ optional($bill->supplier)->name ?: '—' }}</td>
                        <td>{{ optional($bill->purchaseOrder)->po_number ?: '—' }}</td>
                        <td>{{ $bill->due_date ? \Carbon\Carbon::parse($bill->due_date)->format('d M Y') : '—' }}</td>
                        <td>₹{{ number_format((float) $bill->total_amount, 2) }}</td>
                        <td>₹{{ number_format((float) $bill->outstanding_amount, 2) }}</td>
                        <td>@include('procurement._status', ['status' => $bill->display_status])</td>
                        <td><a class="button button-small button-light" href="{{ route('supplier-bills.show', $bill->id) }}">View</a></td>
                    </tr>
                @empty
                    <tr><td colspan="9">@include('components.empty-state', ['icon' => 'icon-tray-in', 'title' => 'No supplier bills', 'message' => 'Open a purchase order with goods received and choose "Enter Supplier Bill".'])</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($bills->hasPages())
            <div class="table-footer">{{ $bills->links() }}</div>
        @endif
    </section>
@endsection
