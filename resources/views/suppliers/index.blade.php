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
            <select class="filter-select" aria-label="Filter by status" data-filter-key="status"><option value="">All statuses</option><option value="active">Active</option><option value="inactive">Inactive</option></select>
            <button class="button button-light" type="button" data-filter-reset>Reset</button>
        </div>
        <div class="table-wrap">
            <table class="data-table listing-table">
                <thead><tr><th>#</th><th>SUPPLIER</th><th>COMPANY</th><th>EMAIL</th><th>PHONE</th><th>STATUS</th><th>ACTIONS</th></tr></thead>
                <tbody>
                @forelse ($suppliers as $supplier)
                    <tr data-table-row data-status="{{ $supplier->is_active ? 'active' : 'inactive' }}">
                        <td class="muted-cell">{{ $loop->iteration }}</td>
                        <td><div class="table-person"><span class="product-avatar">{{ strtoupper(substr($supplier->name, 0, 1)) }}</span><strong class="table-primary-text">{{ $supplier->name }}</strong></div></td>
                        <td>{{ $supplier->company_name ?: '—' }}</td>
                        <td>{{ $supplier->email ?: '—' }}</td>
                        <td>{{ $supplier->phone ?: '—' }}</td>
                        <td>@include('components.status-badge', ['status' => $supplier->is_active ? 'Active' : 'Inactive'])</td>
                        <td class="action-cell">
                            <a class="button button-small button-light" href="{{ route('suppliers.edit', $supplier->id) }}">Edit</a>
                            <form action="{{ route('suppliers.destroy', $supplier->id) }}" method="POST" onsubmit="return confirm('Delete this supplier? This action cannot be undone.');">
                                @csrf
                                @method('DELETE')
                                <button class="button button-small button-danger" type="submit">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7">@include('components.empty-state', ['icon' => 'icon-users', 'title' => 'No suppliers yet', 'message' => 'Add a supplier to record where your stock comes from.'])</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="filter-empty" data-filter-empty hidden>No suppliers match your search or filters.</div>
        <div class="table-footer"><span class="table-summary" data-table-summary></span><div class="pagination" data-table-pagination></div></div>
    </section>
@endsection
