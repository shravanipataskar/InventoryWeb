@extends('layouts.app')

@section('title', $company->name)
@section('topbar-title', 'Company overview')

@section('content')
    <div class="company-detail-heading">
        <a class="company-back-button" href="{{ route('companies.index') }}" aria-label="Back to companies">←</a>
        <span class="company-logo company-detail-logo company-logo-{{ $company->id % 5 }}">{{ strtoupper(substr($company->name, 0, 1)) }}</span>
        <div class="company-detail-title">
            <h1>{{ $company->name }} <span class="company-status {{ $company->is_active ? 'is-active' : 'is-inactive' }}">{{ $company->is_active ? 'Active' : 'Inactive' }}</span></h1>
            <p>{{ $company->code }} <span>Created on {{ optional($company->created_at)->format('d M Y') ?: '—' }}</span></p>
        </div>
        <a class="button company-primary-button company-edit-button" href="{{ route('companies.edit', $company->id) }}">✎ Edit Company</a>
    </div>

    <section class="company-profile-card">
        <div class="company-profile-description">
            <span class="company-detail-label">COMPANY NAME</span><strong>{{ $company->name }}</strong>
            <span class="company-detail-label">COMPANY CODE</span><strong>{{ $company->code }}</strong>
            <span class="company-detail-label">DESCRIPTION</span><p>{{ $company->description ?: 'No company description has been added.' }}</p>
        </div>
        <div class="company-profile-facts">
            <div><span class="company-detail-label">STATUS</span><span class="company-status {{ $company->is_active ? 'is-active' : 'is-inactive' }}">{{ $company->is_active ? 'Active' : 'Inactive' }}</span></div>
            <div><span class="company-detail-label">TOTAL PRODUCTS</span><strong>{{ number_format($totalProducts) }}</strong></div>
            <div><span class="company-detail-label">TOTAL CATEGORIES</span><strong>{{ number_format($totalCategories) }}</strong></div>
            <div><span class="company-detail-label">CREATED DATE</span><strong>{{ optional($company->created_at)->format('d M Y') ?: '—' }}</strong></div>
        </div>
    </section>

    <nav class="company-detail-tabs" aria-label="Company sections">
        @foreach (['overview' => 'Overview', 'products' => 'Products', 'categories' => 'Categories', 'suppliers' => 'Suppliers', 'stock' => 'Stock Movement', 'documents' => 'Documents', 'settings' => 'Settings'] as $tabKey => $tabLabel)
            @if ($tabKey === 'settings')
                <a class="{{ request()->routeIs('companies.edit') ? 'is-current' : '' }}" href="{{ route('companies.edit', $company->id) }}"><span aria-hidden="true">⚙</span> {{ $tabLabel }}</a>
            @else
                <a class="{{ $tab === $tabKey ? 'is-current' : '' }}" href="{{ route('companies.show', ['company' => $company->id, 'tab' => $tabKey]) }}"><span aria-hidden="true">@if ($tabKey === 'overview')⌂@elseif ($tabKey === 'products')◇@elseif ($tabKey === 'categories')▦@elseif ($tabKey === 'suppliers')♧@else▤@endif</span> {{ $tabLabel }}</a>
            @endif
        @endforeach
    </nav>

    @if ($tab === 'overview')
        <section class="company-overview-section">
            <div class="company-section-heading"><div><h2>Company Overview</h2><p>Live inventory metrics for {{ $company->name }}.</p></div><a href="{{ route('products.index', ['company_id' => $company->id]) }}">View products <span>→</span></a></div>
            <div class="company-metrics">
                <article class="company-metric-card"><span class="company-stat-icon company-icon-blue"><svg><use href="#icon-box"></use></svg></span><div><span>Total Products</span><strong>{{ number_format($totalProducts) }}</strong><small>{{ number_format($activeProducts) }} active products</small></div></article>
                <article class="company-metric-card"><span class="company-stat-icon company-icon-indigo"><svg><use href="#icon-grid"></use></svg></span><div><span>Categories</span><strong>{{ number_format($totalCategories) }}</strong><small>Linked to products</small></div></article>
                <article class="company-metric-card"><span class="company-stat-icon company-icon-green"><svg><use href="#icon-layers"></use></svg></span><div><span>Current Stock (Qty)</span><strong>{{ number_format($currentStock, 0) }}</strong><small>Across company products</small></div></article>
                <article class="company-metric-card"><span class="company-stat-icon company-icon-red"><span class="company-rupee">₹</span></span><div><span>Stock Value</span><strong>₹ {{ number_format($stockValue, 2) }}</strong><small>At purchase price</small></div></article>
            </div>
            <div class="company-charts">
                <article class="company-chart-card">
                    <h3>Products by Category</h3>
                    @if ($categorySummary->count())
                        <div class="company-chart-list">
                            @foreach ($categorySummary as $category)
                                <div class="company-chart-row"><span class="company-chart-marker company-chart-color-{{ $loop->index % 5 }}"></span><span>{{ $category->name }}</span><strong>{{ number_format($category->products_count) }}</strong><div class="company-chart-track"><span class="company-chart-color-{{ $loop->index % 5 }}" style="width: {{ $totalProducts ? max(2, $category->products_count / $totalProducts * 100) : 0 }}%"></span></div></div>
                            @endforeach
                        </div>
                    @elseif ($tab === 'documents')
                        <section class="company-content-card">
                            <div class="company-section-heading"><div><h2>Company Documents</h2><p>Store company-related reference documents (PDF, spreadsheets, and images; max 10 MB each).</p></div></div>
                            <form class="company-document-upload" action="{{ route('companies.documents.store', $company->id) }}" method="POST" enctype="multipart/form-data">
                                @csrf
                                <label for="company-document">Choose a document</label>
                                <input id="company-document" type="file" name="document" accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,.png,.jpg,.jpeg" required>
                                @error('document')<small class="field-error">{{ $message }}</small>@enderror
                                <button class="button company-primary-button" type="submit">Upload Document</button>
                            </form>
                            @if ($documents->count())
                                <div class="company-document-list">
                                    @foreach ($documents as $document)
                                        <div><span class="company-document-icon"><svg><use href="#icon-box"></use></svg></span><span class="company-document-meta"><strong>{{ $document->file_name }}</strong><small>{{ number_format($document->file_size / 1024, 1) }} KB · Added {{ optional($document->created_at)->format('d M Y') }}</small></span><a href="{{ route('companies.documents.download', [$company->id, $document->id]) }}">Download</a><form action="{{ route('companies.documents.destroy', [$company->id, $document->id]) }}" method="POST" onsubmit="return confirm('Delete this document?');">@csrf @method('DELETE')<button type="submit" aria-label="Delete document">Delete</button></form></div>
                                    @endforeach
                                </div>
                            @else
                                <div class="company-tab-empty">No documents uploaded for this company yet.</div>
                            @endif
                        </section>
                    @else
                        <p class="company-chart-empty">Products assigned to this company will appear here.</p>
                    @endif
                </article>
                <article class="company-chart-card">
                    <h3>Stock Value by Category</h3>
                    @if ($categorySummary->count())
                        @php($maxCategoryStockValue = max(1, (float) $categorySummary->max('stock_value')))
                        <div class="company-stock-bars">
                            @foreach ($categorySummary as $category)
                                <div class="company-stock-bar-column"><strong>₹{{ number_format($category->stock_value, 0) }}</strong><span class="company-stock-bar company-chart-color-{{ $loop->index % 5 }}" style="height: {{ max(4, (float) $category->stock_value / $maxCategoryStockValue * 100) }}%"></span><small>{{ $category->name }}</small></div>
                            @endforeach
                        </div>
                    @else
                        <p class="company-chart-empty">Stock valuations will appear when products are assigned.</p>
                    @endif
                </article>
            </div>
            <article class="company-content-card">
                <div class="company-section-heading"><div><h2>Products</h2><p>Recently added products from this company.</p></div><a href="{{ route('companies.show', ['company' => $company->id, 'tab' => 'products']) }}">View all <span>→</span></a></div>
                @include('companies.partials.products-table', ['products' => $products])
            </article>
        </section>
    @elseif ($tab === 'products')
        <section class="company-content-card">
            <div class="company-section-heading"><div><h2>Company Products</h2><p>{{ number_format($totalProducts) }} products associated with {{ $company->name }}.</p></div><a href="{{ route('products.create') }}">+ Add Product <span>→</span></a></div>
            @include('companies.partials.products-table', ['products' => $products])
        </section>
    @elseif ($tab === 'categories')
        <section class="company-content-card">
            <div class="company-section-heading"><div><h2>Categories</h2><p>Categories represented by this company's products.</p></div></div>
            @if ($categories->count())
                <div class="company-entity-list">@foreach ($categories as $category)<a href="{{ route('products.index', ['company_id' => $company->id, 'category_id' => $category->id]) }}"><span class="company-entity-icon"><svg><use href="#icon-layers"></use></svg></span><strong>{{ $category->name }}</strong><span>{{ number_format($category->company_products_count) }} {{ \Illuminate\Support\Str::plural('product', $category->company_products_count) }}</span><b>→</b></a>@endforeach</div>
            @else
                <div class="company-tab-empty">No categories have products associated with this company yet.</div>
            @endif
        </section>
    @elseif ($tab === 'suppliers')
        <section class="company-content-card">
            <div class="company-section-heading"><div><h2>Suppliers</h2><p>Suppliers from recorded inward stock for this company's products.</p></div><a href="{{ route('suppliers.index') }}">Manage suppliers <span>→</span></a></div>
            @if ($suppliers->count())
                <div class="company-entity-list">@foreach ($suppliers as $supplier)<a href="{{ route('suppliers.index') }}"><span class="company-entity-icon company-icon-green"><svg><use href="#icon-users"></use></svg></span><strong>{{ $supplier->name }}</strong><span>{{ number_format($supplier->company_inwards_count) }} stock receipts</span><b>→</b></a>@endforeach</div>
            @else
                <div class="company-tab-empty">No suppliers are associated yet. Suppliers appear here after a stock inward is recorded for one of this company's products.</div>
            @endif
        </section>
    @else
        <section class="company-content-card">
            <div class="company-section-heading"><div><h2>Stock Movement</h2><p>Recorded stock receipts and issues for this company's products.</p></div><div><a href="{{ route('stock-inwards.index') }}">Stock Inward <span>→</span></a> &nbsp; <a href="{{ route('stock-outwards.index') }}">Stock Outward <span>→</span></a></div></div>
            <div class="company-movement-grid">
                <div><h3>Stock Inward</h3>@if ($stockInwards->count())<div class="company-movement-list">@foreach ($stockInwards as $movement)<div><span><strong>{{ optional($movement->product)->name ?: 'Product' }}</strong><small>{{ optional($movement->supplier)->name ?: 'Supplier' }} · {{ \Carbon\Carbon::parse($movement->inward_date)->format('d M Y') }}</small></span><b>+{{ number_format($movement->quantity, 2) }}</b></div>@endforeach</div>@else<p class="company-chart-empty">No stock receipts found.</p>@endif</div>
                <div><h3>Stock Outward</h3>@if ($stockOutwards->count())<div class="company-movement-list">@foreach ($stockOutwards as $movement)<div><span><strong>{{ optional($movement->product)->name ?: 'Product' }}</strong><small>{{ \Carbon\Carbon::parse($movement->outward_date)->format('d M Y') }}</small></span><b class="company-movement-out">−{{ number_format($movement->quantity, 2) }}</b></div>@endforeach</div>@else<p class="company-chart-empty">No stock issues found.</p>@endif</div>
            </div>
        </section>
    @endif
@endsection
