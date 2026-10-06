@extends('layouts.app')

@section('title', 'Suppliers')
@section('topbar-title', 'Suppliers')

@section('content')
    <div class="page-heading">
        <div><span class="section-kicker">PARTNERS</span><h1>Suppliers</h1><p>Manage your inventory suppliers.</p></div>
        <a class="button button-primary" href="{{ route('suppliers.create') }}"><span class="button-plus">+</span> Add Supplier</a>
    </div>

    <section class="panel listing-panel" data-enhanced-table data-page-size="10">
        <div class="table-toolbar">
            <label class="search-field"><span class="search-glyph" aria-hidden="true">⌕</span><input type="search" placeholder="Search name, company, email or phone..." aria-label="Search suppliers" data-table-search></label>
            <div class="status-tabs" aria-label="Supplier status">
                <a class="status-tab {{ $listingStatus === 'active' ? 'is-selected' : '' }}" href="{{ route('suppliers.index', ['status' => 'active']) }}" {{ $listingStatus === 'active' ? 'aria-current=page' : '' }}>Active</a>
                <a class="status-tab {{ $listingStatus === 'inactive' ? 'is-selected' : '' }}" href="{{ route('suppliers.index', ['status' => 'inactive']) }}" {{ $listingStatus === 'inactive' ? 'aria-current=page' : '' }}>Inactive</a>
            </div>
        </div>
        <div class="table-wrap">
            <table class="data-table listing-table">
                <thead><tr><th>#</th><th>SUPPLIER</th><th>COMPANY</th><th>EMAIL</th><th>PHONE</th><th>STATUS</th><th>ACTIONS</th></tr></thead>
                <tbody>
                @forelse ($suppliers as $supplier)
                    <tr data-table-row>
                        <td class="muted-cell">{{ $loop->iteration }}</td>
                        <td><div class="table-person"><span class="product-avatar">{{ strtoupper(substr($supplier->name, 0, 1)) }}</span><strong class="table-primary-text">{{ $supplier->name }}</strong></div></td>
                        <td>{{ $supplier->company_name ?: '—' }}</td>
                        <td>{{ $supplier->email ?: '—' }}</td>
                        <td>{{ $supplier->phone ?: '—' }}</td>
                        <td>@include('components.status-badge', ['status' => $supplier->is_active ? 'Active' : 'Inactive'])</td>
                        <td class="action-cell">
                            <a class="button button-small button-light" href="{{ route('suppliers.edit', $supplier->id) }}">Edit</a>
                            <form class="status-action-form" action="{{ route('suppliers.status', $supplier->id) }}" method="POST">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="is_active" value="{{ $supplier->is_active ? 0 : 1 }}">
                                <input type="hidden" name="listing_status" value="{{ $listingStatus }}">
                                <button class="button button-small {{ $supplier->is_active ? 'button-deactivate' : 'button-activate' }}" type="submit">{{ $supplier->is_active ? 'Inactive' : 'Active' }}</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7">@include('components.empty-state', ['icon' => 'icon-users', 'title' => 'No suppliers yet', 'message' => 'Add a supplier to record where your stock comes from.'])</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="filter-empty" data-filter-empty hidden>No {{ $listingStatus }} suppliers match your search.</div>
        <div class="table-footer"><span class="table-summary" data-table-summary></span><div class="pagination" data-table-pagination></div></div>
    </section>
@endsection
