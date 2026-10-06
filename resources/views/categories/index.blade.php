@extends('layouts.app')

@section('title', 'Categories')
@section('topbar-title', 'Categories')

@section('content')
    <div class="page-heading category-page-heading">
        <div>
            <span class="section-kicker">CATALOGUE</span>
            <h1>Categories</h1>
            <p>Manage and organize your inventory categories.</p>
        </div>
        <a class="button button-primary" href="{{ route('categories.create') }}"><span class="button-plus">+</span> Add Category</a>
    </div>

<<<<<<< Updated upstream
    <section class="panel listing-panel" data-enhanced-table data-page-size="10">
        <div class="table-toolbar">
            <label class="search-field">
                <span class="search-glyph" aria-hidden="true">⌕</span>
                <input type="search" placeholder="Search categories..." aria-label="Search categories" data-table-search>
            </label>
            <div class="status-tabs" aria-label="Category status">
                <a class="status-tab {{ $listingStatus === 'active' ? 'is-selected' : '' }}" href="{{ route('categories.index', ['status' => 'active']) }}" {{ $listingStatus === 'active' ? 'aria-current=page' : '' }}>Active</a>
                <a class="status-tab {{ $listingStatus === 'inactive' ? 'is-selected' : '' }}" href="{{ route('categories.index', ['status' => 'inactive']) }}" {{ $listingStatus === 'inactive' ? 'aria-current=page' : '' }}>Inactive</a>
            </div>
        </div>
        <div class="table-wrap">
            <table class="data-table listing-table">
                <thead><tr><th>#</th><th>CATEGORY NAME</th><th>DESCRIPTION</th><th>STATUS</th><th>ACTIONS</th></tr></thead>
                <tbody>
                @forelse ($categories as $category)
                    <tr data-table-row>
                        <td class="muted-cell">{{ $loop->iteration }}</td>
                        <td><strong class="table-primary-text">{{ $category->name }}</strong></td>
                        <td class="description-cell">{{ $category->description ?: '—' }}</td>
                        <td>@include('components.status-badge', ['status' => $category->is_active ? 'Active' : 'Inactive'])</td>
                        <td class="action-cell">
                            <a class="button button-small button-light" href="{{ route('categories.edit', $category->id) }}">Edit</a>
                            <form class="status-action-form" action="{{ route('categories.status', $category->id) }}" method="POST">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="is_active" value="{{ $category->is_active ? 0 : 1 }}">
                                <input type="hidden" name="listing_status" value="{{ $listingStatus }}">
                                <button class="button button-small {{ $category->is_active ? 'button-deactivate' : 'button-activate' }}" type="submit">{{ $category->is_active ? 'Inactive' : 'Active' }}</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5">@include('components.empty-state', ['icon' => 'icon-layers', 'title' => 'No categories yet', 'message' => 'Add a category to organize your products.'])</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="filter-empty" data-filter-empty hidden>No {{ $listingStatus }} categories match your search.</div>
        <div class="table-footer">
            <span class="table-summary" data-table-summary></span>
            <div class="pagination" data-table-pagination></div>
        </div>
=======
    <section class="category-stats" aria-label="Category summary">
        <article class="category-stat-card">
            <span class="category-stat-icon category-tone-purple"><svg><use href="#icon-layers"></use></svg></span>
            <div><span class="category-stat-value">{{ number_format($totalCategories) }}</span><span class="category-stat-label">Total Categories</span></div>
        </article>
        <article class="category-stat-card">
            <span class="category-stat-icon category-tone-green"><svg><use href="#icon-check"></use></svg></span>
            <div><span class="category-stat-value">{{ number_format($activeCategories) }}</span><span class="category-stat-label">Active Categories</span></div>
        </article>
        <article class="category-stat-card">
            <span class="category-stat-icon category-tone-orange"><svg><use href="#icon-close"></use></svg></span>
            <div><span class="category-stat-value">{{ number_format($inactiveCategories) }}</span><span class="category-stat-label">Inactive Categories</span></div>
        </article>
        <article class="category-stat-card">
            <span class="category-stat-icon category-tone-blue"><svg><use href="#icon-box"></use></svg></span>
            <div><span class="category-stat-value">{{ number_format($totalProductsInCategories) }}</span><span class="category-stat-label">Products in Categories</span></div>
        </article>
>>>>>>> Stashed changes
    </section>

    <section class="panel category-list-panel">
        <form class="category-filter-form" method="GET" action="{{ route('categories.index') }}" data-category-filter>
            <label class="search-field category-search-field">
                <span class="search-glyph" aria-hidden="true">⌕</span>
                <input type="search" name="search" value="{{ request('search') }}" placeholder="Search categories by name or description..." aria-label="Search categories by name or description" data-category-search>
            </label>
            <select class="filter-select" name="status" aria-label="Filter by status" data-category-submit>
                <option value="">All statuses</option>
                <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
            </select>
            <select class="filter-select" name="sort" aria-label="Sort categories" data-category-submit>
                <option value="newest" {{ $sort === 'newest' ? 'selected' : '' }}>Newest First</option>
                <option value="oldest" {{ $sort === 'oldest' ? 'selected' : '' }}>Oldest First</option>
                <option value="name_asc" {{ $sort === 'name_asc' ? 'selected' : '' }}>Name A-Z</option>
                <option value="name_desc" {{ $sort === 'name_desc' ? 'selected' : '' }}>Name Z-A</option>
            </select>
            <a class="button button-light category-reset-button" href="{{ route('categories.index') }}">Reset</a>
            <span class="category-result-count">{{ number_format($categories->total()) }} {{ \Illuminate\Support\Str::plural('category', $categories->total()) }} found</span>
        </form>

        @if ($categories->count())
            <div class="table-wrap category-table-wrap">
                <table class="data-table listing-table category-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>CATEGORY</th>
                            <th>DESCRIPTION</th>
                            <th>PRODUCTS</th>
                            <th>STATUS</th>
                            <th>CREATED</th>
                            <th>ACTIONS</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($categories as $category)
                            <tr>
                                <td class="muted-cell">{{ $categories->firstItem() + $loop->index }}</td>
                                <td>
                                    <a class="category-name-cell" href="{{ route('categories.show', $category->id) }}">
                                        <span class="category-avatar category-tone-{{ $category->id % 4 }}">
                                            <svg><use href="#icon-layers"></use></svg>
                                        </span>
                                        <strong>{{ $category->name }}</strong>
                                    </a>
                                </td>
                                <td class="category-description">{{ $category->description ?: '—' }}</td>
                                <td>
                                    <a class="category-products-link" href="{{ route('products.index', ['category_id' => $category->id]) }}">
                                        {{ number_format($category->products_count) }} {{ \Illuminate\Support\Str::plural('product', $category->products_count) }} <span aria-hidden="true">→</span>
                                    </a>
                                </td>
                                <td>
                                    <span class="category-status {{ $category->is_active ? 'is-active' : 'is-inactive' }}">
                                        <span aria-hidden="true">&#9679;</span> {{ $category->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="category-created">{{ optional($category->created_at)->format('d M Y') ?: '—' }}</td>
                                <td class="category-action-cell">
                                    <details class="category-actions">
                                        <summary aria-label="Actions for {{ $category->name }}" title="Category actions">&#8942;</summary>
                                        <div class="category-action-menu">
                                            <a href="{{ route('categories.show', $category->id) }}">View Category</a>
                                            <a href="{{ route('categories.edit', $category->id) }}">Edit Category</a>
                                            <a href="{{ route('products.index', ['category_id' => $category->id]) }}">View Products</a>
                                            <form action="{{ route('categories.update', $category->id) }}" method="POST">
                                                @csrf
                                                @method('PUT')
                                                <input type="hidden" name="name" value="{{ $category->name }}">
                                                <input type="hidden" name="description" value="{{ $category->description }}">
                                                <input type="hidden" name="is_active" value="{{ $category->is_active ? 0 : 1 }}">
                                                <button type="submit">{{ $category->is_active ? 'Deactivate Category' : 'Activate Category' }}</button>
                                            </form>
                                            <span class="category-action-divider" aria-hidden="true"></span>
                                            <form action="{{ route('categories.destroy', $category->id) }}" method="POST" onsubmit="return confirm('Permanently delete this category? This action cannot be undone.');">
                                                @csrf
                                                @method('DELETE')
                                                <button class="category-action-delete" type="submit">Delete Category</button>
                                            </form>
                                        </div>
                                    </details>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <footer class="category-table-footer">
                <span>Showing {{ number_format($categories->firstItem()) }}–{{ number_format($categories->lastItem()) }} of {{ number_format($categories->total()) }} categories</span>
                @if ($categories->hasPages())
                    <div class="category-pagination">{{ $categories->onEachSide(2)->links() }}</div>
                @endif
            </footer>
        @else
            <div class="category-empty-state">
                <span class="category-empty-icon"><svg><use href="#icon-layers"></use></svg></span>
                <h2>No categories found</h2>
                <p>Try changing your search or filter, or create a new category.</p>
                <a class="button button-primary" href="{{ route('categories.create') }}"><span class="button-plus">+</span> Add Category</a>
            </div>
        @endif
    </section>

    <script>
        (function () {
            var filterForm = document.querySelector('[data-category-filter]');
            if (filterForm) {
                var search = filterForm.querySelector('[data-category-search]');
                var timer;
                if (search) {
                    search.addEventListener('input', function () {
                        window.clearTimeout(timer);
                        timer = window.setTimeout(function () {
                            filterForm.submit();
                        }, 350);
                    });
                }

                Array.prototype.forEach.call(filterForm.querySelectorAll('[data-category-submit]'), function (select) {
                    select.addEventListener('change', function () {
                        filterForm.submit();
                    });
                });
            }

            var actionMenus = Array.prototype.slice.call(document.querySelectorAll('.category-actions'));
            actionMenus.forEach(function (menu) {
                menu.addEventListener('toggle', function () {
                    if (!menu.open) {
                        return;
                    }

                    actionMenus.forEach(function (otherMenu) {
                        if (otherMenu !== menu) {
                            otherMenu.open = false;
                        }
                    });

                    var summary = menu.querySelector('summary');
                    var panel = menu.querySelector('.category-action-menu');
                    var anchor = summary.getBoundingClientRect();
                    panel.style.visibility = 'hidden';
                    panel.style.display = 'block';
                    var panelRect = panel.getBoundingClientRect();
                    var left = Math.min(anchor.right - panelRect.width, window.innerWidth - panelRect.width - 8);
                    var top = anchor.bottom + panelRect.height <= window.innerHeight - 8
                        ? anchor.bottom + 5
                        : anchor.top - panelRect.height - 5;
                    panel.style.left = Math.max(8, left) + 'px';
                    panel.style.top = Math.max(8, top) + 'px';
                    panel.style.visibility = '';
                    panel.style.display = '';
                });
            });

            document.addEventListener('click', function (event) {
                actionMenus.forEach(function (menu) {
                    if (menu.open && !menu.contains(event.target)) {
                        menu.open = false;
                    }
                });
            });
        }());
    </script>
@endsection
