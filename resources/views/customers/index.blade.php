@extends('layouts.app')

@section('title', 'Customers / Recipients')
@section('topbar-title', 'Customers')

@section('content')
    <div class="page-heading workspace-page-heading">
        <div><span class="section-kicker">RELATIONSHIPS</span><h1>Customers / Recipients</h1><p>Manage customers and recipients who receive inventory.</p></div>
        <div class="page-heading-actions">
            <a class="button button-primary" href="{{ route('customers.create') }}"><span class="button-plus">+</span> Add Customer</a>
            <a class="button button-light" href="{{ route('stock-outwards.create') }}">Record Stock Outward</a>
        </div>
    </div>

    <section class="workspace-summary-grid">
        <article class="workspace-summary-card"><span class="workspace-summary-icon workspace-blue"><svg><use href="#icon-users"></use></svg></span><div><small>Total Customers</small><strong>{{ number_format($totalCustomers) }}</strong></div></article>
        <article class="workspace-summary-card"><span class="workspace-summary-icon workspace-green"><svg><use href="#icon-users"></use></svg></span><div><small>Active Customers</small><strong>{{ number_format($activeCustomers) }}</strong></div></article>
        <article class="workspace-summary-card"><span class="workspace-summary-icon workspace-blue"><svg><use href="#icon-users"></use></svg></span><div><small>Inactive Customers</small><strong>{{ number_format($inactiveCustomers) }}</strong></div></article>
    </section>

    <section class="panel workspace-panel">
        <form class="workspace-toolbar" method="GET" action="{{ route('customers.index') }}">
            <input type="hidden" name="status" value="{{ $listingStatus }}">
            <label class="search-field workspace-search"><span class="search-glyph" aria-hidden="true">⌕</span><input type="search" name="search" value="{{ request('search') }}" placeholder="Search customers..." aria-label="Search customers"></label>
            <button class="button button-primary" type="submit">Search Customers</button>
            @if (request('search'))
                <a class="button button-light" href="{{ route('customers.index', ['status' => $listingStatus]) }}">Reset</a>
            @endif
            <div class="status-tabs" aria-label="Customer status">
                <a class="status-tab {{ $listingStatus === 'active' ? 'is-selected' : '' }}" href="{{ route('customers.index', ['status' => 'active']) }}" {{ $listingStatus === 'active' ? 'aria-current=page' : '' }}>Active</a>
                <a class="status-tab {{ $listingStatus === 'inactive' ? 'is-selected' : '' }}" href="{{ route('customers.index', ['status' => 'inactive']) }}" {{ $listingStatus === 'inactive' ? 'aria-current=page' : '' }}>Inactive</a>
            </div>
            <span class="workspace-result-count">{{ number_format($customers->total()) }} {{ \Illuminate\Support\Str::plural('customer', $customers->total()) }} found</span>
        </form>

        @if ($customers->count())
            <div class="table-wrap">
                <table class="data-table workspace-table">
                    <thead><tr><th>CUSTOMER NAME</th><th>CUSTOMER TYPE</th><th>CONTACT PERSON</th><th>PHONE</th><th>EMAIL</th><th>STORE / WAREHOUSE LOCATION</th><th>CITY</th><th>STATUS</th><th>ACTIONS</th></tr></thead>
                    <tbody>
                    @foreach ($customers as $customer)
                        <tr>
                            <td><div class="workspace-person"><span>{{ strtoupper(substr($customer->name, 0, 1)) }}</span><strong>{{ $customer->name }}</strong></div></td>
                            <td>{{ $customer->customer_type }}</td>
                            <td>{{ $customer->contact_person ?: '—' }}</td>
                            <td>{{ $customer->phone ?: '—' }}</td>
                            <td>{{ $customer->email ?: '—' }}</td>
                            <td>{{ $customer->store_warehouse_location ?: '—' }}</td>
                            <td>{{ $customer->city ?: '—' }}</td>
                            <td>@include('components.status-badge', ['status' => $customer->is_active ? 'Active' : 'Inactive'])</td>
                            <td class="action-cell">
                                <a class="button button-small button-light" href="{{ route('customers.show', $customer->id) }}">View</a>
                                <a class="button button-small button-light" href="{{ route('customers.edit', $customer->id) }}">Edit</a>
                                <form class="status-action-form" action="{{ route('customers.status', $customer->id) }}" method="POST">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="is_active" value="{{ $customer->is_active ? 0 : 1 }}">
                                    <input type="hidden" name="listing_status" value="{{ $listingStatus }}">
                                    <button class="button button-small {{ $customer->is_active ? 'button-deactivate' : 'button-activate' }}" type="submit">{{ $customer->is_active ? 'Deactivate' : 'Activate' }}</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <footer class="workspace-footer"><span>Showing {{ number_format($customers->firstItem()) }}–{{ number_format($customers->lastItem()) }} of {{ number_format($customers->total()) }} customers</span>@if ($customers->hasPages())<div class="workspace-pagination">{{ $customers->onEachSide(2)->links() }}</div>@endif</footer>
        @else
            <div class="workspace-empty"><span class="workspace-empty-icon"><svg><use href="#icon-users"></use></svg></span><h2>No {{ $listingStatus }} customers found</h2><p>Add a customer to your master list. Existing stock outward recipient records remain available in transaction history.</p><a class="button button-primary" href="{{ route('customers.create') }}">Add Customer</a></div>
        @endif
    </section>
@endsection
