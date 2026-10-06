@extends('layouts.app')

@section('title', 'Categories')
@section('topbar-title', 'Categories')

@section('content')
    <div class="page-heading">
        <div>
            <span class="section-kicker">CATALOGUE</span>
            <h1>Categories</h1>
            <p>Manage your inventory categories.</p>
        </div>
        <a class="button button-primary" href="{{ route('categories.create') }}"><span class="button-plus">+</span> Add Category</a>
    </div>

    <section class="panel listing-panel" data-enhanced-table data-page-size="10">
        <div class="table-toolbar">
            <label class="search-field">
                <span class="search-glyph" aria-hidden="true">⌕</span>
                <input type="search" placeholder="Search categories..." aria-label="Search categories" data-table-search>
            </label>
            <select class="filter-select" aria-label="Filter by status" data-filter-key="status">
                <option value="">All statuses</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </select>
            <button class="button button-light" type="button" data-filter-reset>Reset</button>
        </div>
        <div class="table-wrap">
            <table class="data-table listing-table">
                <thead><tr><th>#</th><th>CATEGORY NAME</th><th>DESCRIPTION</th><th>STATUS</th><th>ACTIONS</th></tr></thead>
                <tbody>
                @forelse ($categories as $category)
                    <tr data-table-row data-status="{{ $category->is_active ? 'active' : 'inactive' }}">
                        <td class="muted-cell">{{ $loop->iteration }}</td>
                        <td><strong class="table-primary-text">{{ $category->name }}</strong></td>
                        <td class="description-cell">{{ $category->description ?: '—' }}</td>
                        <td>@include('components.status-badge', ['status' => $category->is_active ? 'Active' : 'Inactive'])</td>
                        <td class="action-cell">
                            <a class="button button-small button-light" href="{{ route('categories.edit', $category->id) }}">Edit</a>
                            <form action="{{ route('categories.destroy', $category->id) }}" method="POST" onsubmit="return confirm('Delete this category? This action cannot be undone.');">
                                @csrf
                                @method('DELETE')
                                <button class="button button-small button-danger" type="submit">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5">@include('components.empty-state', ['icon' => 'icon-layers', 'title' => 'No categories yet', 'message' => 'Add a category to organize your products.'])</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="filter-empty" data-filter-empty hidden>No categories match your search or filters.</div>
        <div class="table-footer">
            <span class="table-summary" data-table-summary></span>
            <div class="pagination" data-table-pagination></div>
        </div>
    </section>
@endsection
