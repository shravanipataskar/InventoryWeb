@extends('layouts.app')

@section('title', $category->name)
@section('topbar-title', 'Category details')

@section('content')
    <div class="page-heading">
        <div>
            <span class="section-kicker">CATALOGUE / CATEGORIES</span>
            <h1>{{ $category->name }}</h1>
            <p>Category details and associated inventory.</p>
        </div>
        <div class="category-detail-actions">
            <a class="button button-light" href="{{ route('categories.index') }}">Back to categories</a>
            <a class="button button-primary" href="{{ route('categories.edit', $category->id) }}">Edit Category</a>
        </div>
    </div>

    <section class="category-detail-card">
        <div>
            <span class="category-detail-label">DESCRIPTION</span>
            <p>{{ $category->description ?: 'No description has been added for this category.' }}</p>
        </div>
        <div>
            <span class="category-detail-label">STATUS</span>
            <span class="category-status {{ $category->is_active ? 'is-active' : 'is-inactive' }}">
                <span aria-hidden="true">&#9679;</span> {{ $category->is_active ? 'Active' : 'Inactive' }}
            </span>
        </div>
        <div>
            <span class="category-detail-label">PRODUCTS</span>
            <a class="category-products-link" href="{{ route('products.index', ['category_id' => $category->id]) }}">
                {{ number_format($category->products_count) }} {{ \Illuminate\Support\Str::plural('product', $category->products_count) }} <span aria-hidden="true">→</span>
            </a>
        </div>
        <div>
            <span class="category-detail-label">CREATED</span>
            <span class="category-created">{{ optional($category->created_at)->format('d M Y') ?: '—' }}</span>
        </div>
    </section>
@endsection
