@extends('layouts.app')

@section('title', 'Companies / Brands')
@section('topbar-title', 'Companies / Brands')

@section('content')
    <div class="page-heading company-page-heading">
        <div>
            <span class="section-kicker">CATALOGUE</span>
            <h1>Companies / Brands</h1>
            <p>Manage all product companies and brands. A company can have multiple products.</p>
        </div>
        <a class="button company-primary-button" href="{{ route('companies.create') }}"><span class="button-plus">+</span> Add Company</a>
    </div>

    <section class="company-stats" aria-label="Company summary">
        <article class="company-stat-card">
            <span class="company-stat-icon company-icon-blue"><svg><use href="#icon-building"></use></svg></span>
            <div><span class="company-stat-label">Total Companies</span><strong>{{ number_format($totalCompanies) }}</strong><small>{{ number_format($activeCompanies) }} active</small></div>
        </article>
        <article class="company-stat-card">
            <span class="company-stat-icon company-icon-indigo"><svg><use href="#icon-box"></use></svg></span>
            <div><span class="company-stat-label">Total Products</span><strong>{{ number_format($totalProducts) }}</strong><small>Across all companies</small></div>
        </article>
        <article class="company-stat-card">
            <span class="company-stat-icon company-icon-green"><svg><use href="#icon-check"></use></svg></span>
            <div><span class="company-stat-label">Active Companies</span><strong>{{ number_format($activeCompanies) }}</strong><small>{{ $totalCompanies ? number_format($activeCompanies / $totalCompanies * 100, 1) : '0.0' }}% of total</small></div>
        </article>
        <article class="company-stat-card">
            <span class="company-stat-icon company-icon-red"><svg><use href="#icon-close"></use></svg></span>
            <div><span class="company-stat-label">Inactive Companies</span><strong>{{ number_format($inactiveCompanies) }}</strong><small>{{ $totalCompanies ? number_format($inactiveCompanies / $totalCompanies * 100, 1) : '0.0' }}% of total</small></div>
        </article>
    </section>

    <section class="panel company-list-panel">
        <form class="company-filter-form" method="GET" action="{{ route('companies.index') }}">
            <label class="search-field company-search-field">
                <span class="search-glyph" aria-hidden="true">⌕</span>
                <input type="search" name="search" value="{{ request('search') }}" placeholder="Search company name, code or description..." aria-label="Search companies">
            </label>
            <label class="company-filter-label">Status
                <select class="filter-select" name="status">
                    <option value="">All</option>
                    <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                    <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
                </select>
            </label>
            <label class="company-filter-label">Sort By
                <select class="filter-select" name="sort">
                    <option value="name" {{ $sort === 'name' ? 'selected' : '' }}>Company Name</option>
                    <option value="name_desc" {{ $sort === 'name_desc' ? 'selected' : '' }}>Company Name Z-A</option>
                    <option value="newest" {{ $sort === 'newest' ? 'selected' : '' }}>Newest First</option>
                    <option value="oldest" {{ $sort === 'oldest' ? 'selected' : '' }}>Oldest First</option>
                </select>
            </label>
            <button class="button company-primary-button company-search-button" type="submit"><span aria-hidden="true">⌕</span> Search</button>
            <a class="button button-light" href="{{ route('companies.index') }}">Reset</a>
        </form>

        @if ($companies->count())
            <div class="table-wrap company-table-wrap">
                <table class="data-table company-table">
                    <thead>
                        <tr><th>#</th><th>LOGO</th><th>COMPANY NAME</th><th>CODE</th><th>TOTAL PRODUCTS</th><th>CATEGORIES</th><th>STATUS</th><th>CREATED DATE</th><th>ACTIONS</th></tr>
                    </thead>
                    <tbody>
                    @foreach ($companies as $company)
                        <tr>
                            <td class="muted-cell">{{ $companies->firstItem() + $loop->index }}</td>
                            <td><span class="company-logo company-logo-{{ $company->id % 5 }}">{{ strtoupper(substr($company->name, 0, 1)) }}</span></td>
                            <td><a class="company-table-name" href="{{ route('companies.show', $company->id) }}">{{ $company->name }}</a></td>
                            <td><span class="company-code">{{ $company->code }}</span></td>
                            <td>{{ number_format($company->products_count) }}</td>
                            <td>{{ number_format($categoryCounts[$company->id] ?? 0) }}</td>
                            <td><span class="company-status {{ $company->is_active ? 'is-active' : 'is-inactive' }}">{{ $company->is_active ? 'Active' : 'Inactive' }}</span></td>
                            <td>{{ optional($company->created_at)->format('d M Y') ?: '—' }}</td>
                            <td>
                                <div class="company-row-actions">
                                    <a href="{{ route('companies.show', $company->id) }}" aria-label="View {{ $company->name }}" title="View">◎</a>
                                    <a href="{{ route('companies.edit', $company->id) }}" aria-label="Edit {{ $company->name }}" title="Edit">✎</a>
                                    <form action="{{ route('companies.status', $company->id) }}" method="POST" onsubmit="return confirm('{{ $company->is_active ? 'Deactivate this company? Its products will remain in inventory.' : 'Activate this company?' }}');">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="is_active" value="{{ $company->is_active ? 0 : 1 }}">
                                        <button class="{{ $company->is_active ? 'company-deactivate-action' : 'company-activate-action' }}" type="submit" aria-label="{{ $company->is_active ? 'Deactivate' : 'Activate' }} {{ $company->name }}" title="{{ $company->is_active ? 'Deactivate' : 'Activate' }}">{{ $company->is_active ? '⌫' : '✓' }}</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <footer class="company-table-footer">
                <span>Showing {{ number_format($companies->firstItem()) }}–{{ number_format($companies->lastItem()) }} of {{ number_format($companies->total()) }} companies</span>
                @if ($companies->hasPages())
                    <div class="company-pagination">{{ $companies->onEachSide(2)->links() }}</div>
                @endif
            </footer>
        @else
            <div class="company-empty-state">
                <span class="company-stat-icon company-icon-blue"><svg><use href="#icon-building"></use></svg></span>
                <h2>No companies found</h2>
                <p>Try changing your search or filters, or add a company to organize its products.</p>
                <a class="button company-primary-button" href="{{ route('companies.create') }}">+ Add Company</a>
            </div>
        @endif
    </section>
@endsection
