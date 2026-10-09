@extends('layouts.app')

@section('title', 'Dashboard')
@section('topbar-title', 'Dashboard')

@section('content')
    @php
        $can = function ($module, $action = 'view') {
            return auth()->check() && auth()->user()->hasPermission($module, $action);
        };
    @endphp

    <div class="page-heading">
        <div>
            <span class="section-kicker">OVERVIEW</span>
            <h1>Good day, here's your inventory.</h1>
            <p>A live snapshot of what is happening across your stock.</p>
        </div>
        @if ($can('products', 'create'))
            <a class="button button-primary" href="{{ route('products.create') }}">
                <span class="button-plus">+</span> Add product
            </a>
        @endif
    </div>

    <div class="stats-grid">
        @if ($can('dashboard'))
            @include('components.stat-card', [
            'label' => 'Total products',
            'value' => number_format($stats['products']),
            'note' => 'Products in your catalogue',
            'icon' => 'icon-box',
            'tone' => 'tone-teal',
            ])
        @endif
        @if ($can('dashboard'))
            @include('components.stat-card', [
            'label' => 'Categories',
            'value' => number_format($stats['categories']),
            'note' => 'Product groups',
            'icon' => 'icon-layers',
            'tone' => 'tone-violet',
            ])
        @endif
        @if ($can('dashboard'))
            @include('components.stat-card', [
            'label' => 'Suppliers',
            'value' => number_format($stats['suppliers']),
            'note' => 'Supplier records',
            'icon' => 'icon-users',
            'tone' => 'tone-blue',
            ])
        @endif
        @if ($can('dashboard'))
            @include('components.stat-card', [
            'label' => 'Current stock value',
            'value' => '₹' . number_format($stats['stock_value'], 2),
            'note' => 'On-hand stock × purchase price',
            'icon' => 'icon-arrow-up',
            'tone' => 'tone-gold',
            ])
            @include('components.stat-card', [
            'label' => 'Low stock',
            'value' => number_format($stats['low_stock']),
            'note' => 'Above zero, at or below minimum',
            'icon' => 'icon-alert',
            'tone' => 'tone-amber',
            ])
            @include('components.stat-card', [
            'label' => 'Out of stock',
            'value' => number_format($stats['out_of_stock']),
            'note' => 'No available inventory',
            'icon' => 'icon-tray-out',
            'tone' => 'tone-red',
            ])
        @endif
    </div>

    <div class="dashboard-grid dashboard-grid-primary">
        @if ($can('dashboard'))
        <section class="panel">
            <div class="panel-heading">
                <div>
                    <h2>Low stock products</h2>
                    <p>Items at or below their minimum stock level</p>
                </div>
                @if ($can('products'))
                    <a class="text-link" href="{{ route('products.index') }}">View products <span>→</span></a>
                @endif
            </div>
            @if ($lowStockProducts->isEmpty())
                @include('components.empty-state', [
                    'icon' => 'icon-check',
                    'title' => 'Stock levels look healthy',
                    'message' => 'No products are currently at or below their minimum level.',
                ])
            @else
                <div class="table-wrap">
                    <table class="data-table">
                        <thead><tr><th>PRODUCT</th><th>STOCK</th><th>MINIMUM</th><th>STATUS</th></tr></thead>
                        <tbody>
                        @foreach ($lowStockProducts as $product)
                            <tr>
                                <td>
                                    <div class="product-cell">
                                        <span class="product-avatar">{{ strtoupper(substr($product->name, 0, 1)) }}</span>
                                        <span><strong>{{ $product->name }}</strong><small>{{ $product->product_code }}</small></span>
                                    </div>
                                </td>
                                <td class="number-cell">{{ number_format($product->current_stock, 2) }} <span>{{ optional($product->unit)->short_name }}</span></td>
                                <td class="number-cell">{{ number_format($product->minimum_stock, 2) }} <span>{{ optional($product->unit)->short_name }}</span></td>
                                <td>@include('components.status-badge', ['status' => $product->current_stock <= 0 ? 'Out of stock' : 'Low stock'])</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
        @endif

        @if ($can('dashboard'))
        <section class="panel category-panel">
            <div class="panel-heading">
                <div>
                    <h2>Stock value by category</h2>
                    <p>On-hand value at purchase price</p>
                </div>
                <span class="panel-icon"><svg><use href="#icon-layers"></use></svg></span>
            </div>
            @if ($categoryStock->isEmpty())
                @include('components.empty-state', [
                    'icon' => 'icon-layers',
                    'title' => 'No categories yet',
                    'message' => 'Add a category to organize your products.',
                ])
            @else
                @php($maxCategoryValue = max((float) $categoryStock->max('value_total'), 1))
                <div class="category-list">
                    @foreach ($categoryStock as $category)
                        @php($categoryPercent = min(100, ((float) $category->value_total / $maxCategoryValue) * 100))
                        <div class="category-row">
                            <div class="category-row-heading"><strong>{{ $category->name }}</strong><span>₹{{ number_format($category->value_total, 2) }}</span></div>
                            <div class="progress-track"><span style="width: {{ $categoryPercent }}%"></span></div>
                        </div>
                    @endforeach
                </div>
                @if ($can('categories'))
                    <a class="panel-bottom-link" href="{{ route('categories.index') }}">Manage categories <span>→</span></a>
                @endif
            @endif
        </section>
        @endif
    </div>

    <div class="dashboard-grid dashboard-grid-activity">
        @if ($can('dashboard'))
        <section class="panel">
            <div class="panel-heading">
                <div>
                    <h2>Recent stock inward</h2>
                    <p>Latest inventory received from suppliers</p>
                </div>
                @if ($can('stock'))
                    <a class="text-link" href="{{ route('stock-inwards.index') }}">See all <span>→</span></a>
                @endif
            </div>
            @if ($recentInwards->isEmpty())
                @include('components.empty-state', [
                    'icon' => 'icon-tray-in',
                    'title' => 'No inward records',
                    'message' => 'Stock receipts will appear here when recorded.',
                ])
            @else
                <div class="activity-list">
                    @foreach ($recentInwards as $stock)
                        <div class="activity-row">
                            <span class="activity-icon activity-in"><svg><use href="#icon-arrow-down"></use></svg></span>
                            <div class="activity-main"><strong>{{ optional($stock->product)->name ?? 'Product unavailable' }}</strong><span>{{ optional($stock->supplier)->name ?? 'Supplier unavailable' }} · {{ $stock->inward_date }}</span></div>
                            <span class="activity-quantity quantity-in">+{{ number_format($stock->quantity, 2) }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>
        @endif

        @if ($can('dashboard'))
        <section class="panel">
            <div class="panel-heading">
                <div>
                    <h2>Recent stock outward</h2>
                    <p>Latest inventory issued or sold</p>
                </div>
                @if ($can('stock'))
                    <a class="text-link" href="{{ route('stock-outwards.index') }}">See all <span>→</span></a>
                @endif
            </div>
            @if ($recentOutwards->isEmpty())
                @include('components.empty-state', [
                    'icon' => 'icon-tray-out',
                    'title' => 'No outward records',
                    'message' => 'Stock issued from inventory will appear here.',
                ])
            @else
                <div class="activity-list">
                    @foreach ($recentOutwards as $stock)
                        <div class="activity-row">
                            <span class="activity-icon activity-out"><svg><use href="#icon-arrow-up"></use></svg></span>
                            <div class="activity-main"><strong>{{ optional($stock->product)->name ?? 'Product unavailable' }}</strong><span>{{ $stock->issued_to ?: 'No recipient specified' }} · {{ $stock->outward_date }}</span></div>
                            <span class="activity-quantity quantity-out">−{{ number_format($stock->quantity, 2) }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>
        @endif
    </div>

    <footer class="page-footer">Aayojan Ai Inventory System <span>·</span> Inventory overview from your current records</footer>
@endsection
