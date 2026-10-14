@extends('layouts.app')

@section('title', 'Dashboard')
@section('topbar-title', 'Dashboard')

@section('topbar-actions')
    <div class="dashboard-notification" data-notification>
        <button class="dashboard-notification-button" type="button" aria-label="Notifications{{ $reorderCount > 0 ? ', ' . $reorderCount . ' reorder alerts' : '' }}" aria-expanded="false" aria-controls="reorder-notification-panel" data-notification-toggle>
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/></svg>
            @if ($reorderCount > 0)<span class="dashboard-notification-count">{{ $reorderCount > 99 ? '99+' : $reorderCount }}</span>@endif
        </button>
        <div class="dashboard-notification-panel" id="reorder-notification-panel" role="region" aria-label="Notifications" hidden>
            <div class="dashboard-notification-heading">
                <strong>Notifications</strong>
                @if ($reorderCount > 0)<span>{{ $reorderCount }} {{ \Illuminate\Support\Str::plural('alert', $reorderCount) }}</span>@endif
            </div>
            @if ($reorderProducts->isEmpty())
                <p class="dashboard-notification-empty">You have no current notifications.</p>
            @else
                <ul class="dashboard-notification-list">
                    @foreach ($reorderProducts as $product)
                        <li class="dashboard-notification-item">
                            <span class="dashboard-notification-indicator {{ $product->current_stock <= 0 ? 'is-out' : '' }}" aria-hidden="true"></span>
                            <span class="dashboard-notification-message">
                                <strong>{{ $product->current_stock <= 0 ? 'Out of stock' : 'Reorder required' }}: {{ $product->name }} ({{ $product->product_code }})</strong>
                                <span>Current stock is {{ number_format((float) $product->current_stock, 2) }} {{ optional($product->unit)->short_name }}, below its minimum stock level of {{ number_format((float) $product->minimum_stock, 2) }} {{ optional($product->unit)->short_name }}. Please arrange replenishment.</span>
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
            @if (auth()->user()->hasPermission('reports'))
                <a class="dashboard-notification-footer" href="{{ route('reports.index', ['tab' => 'low-reorder']) }}">View reorder report <span aria-hidden="true">→</span></a>
            @endif
        </div>
    </div>
    <style>
        .dashboard-notification{position:relative;display:flex;align-items:center}
        .dashboard-notification-button{position:relative;display:inline-flex;width:40px;height:40px;align-items:center;justify-content:center;border:1px solid #e3eaf0;border-radius:11px;background:#fff;color:#52677b;cursor:pointer}
        .dashboard-notification-button:hover,.dashboard-notification-button[aria-expanded="true"]{border-color:#b9dfe0;background:#f4fbfb;color:#078c8d}
        .dashboard-notification-button svg{width:20px;height:20px;fill:none;stroke:currentColor;stroke-linecap:round;stroke-linejoin:round;stroke-width:1.7}
        .dashboard-notification-count{position:absolute;top:-5px;right:-5px;min-width:18px;height:18px;padding:0 4px;border:2px solid #fff;border-radius:10px;background:#e34848;color:#fff;font-size:9px;font-weight:700;line-height:14px;text-align:center}
        .dashboard-notification-panel{position:absolute;z-index:60;top:calc(100% + 10px);right:0;width:min(370px,calc(100vw - 28px));overflow:hidden;border:1px solid #e4ebf1;border-radius:13px;background:#fff;box-shadow:0 14px 38px rgba(24,46,67,.16)}
        .dashboard-notification-panel[hidden]{display:none}
        .dashboard-notification-heading{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:14px 16px;border-bottom:1px solid #edf1f5}
        .dashboard-notification-heading strong{color:#19385e;font-size:13px}
        .dashboard-notification-heading span{color:#8190a0;font-size:11px}
        .dashboard-notification-list{max-height:400px;margin:0;padding:0;overflow-y:auto;list-style:none}
        .dashboard-notification-item{display:flex;align-items:flex-start;gap:10px;padding:12px 15px;border-bottom:1px solid #f0f3f6}
        .dashboard-notification-indicator{width:8px;height:8px;flex:0 0 8px;border-radius:50%;background:#e6a129}
        .dashboard-notification-indicator.is-out{background:#df5151}
        .dashboard-notification-message{display:block;min-width:0;flex:1}
        .dashboard-notification-message strong,.dashboard-notification-message span{display:block}
        .dashboard-notification-message strong{color:#29445e;font-size:11px;line-height:1.5}
        .dashboard-notification-message span{margin-top:4px;color:#718196;font-size:10px;line-height:1.55}
        .dashboard-notification-empty{margin:0;padding:18px 16px;color:#718196;font-size:11px}
        .dashboard-notification-footer{display:flex;justify-content:space-between;padding:12px 16px;color:#078c8d;font-size:11px;font-weight:700}
        @media(max-width:640px){.dashboard-notification-panel{right:-48px}}
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var container = document.querySelector('[data-notification]');
            var toggle = document.querySelector('[data-notification-toggle]');
            var panel = document.getElementById('reorder-notification-panel');
            if (!container || !toggle || !panel) return;
            toggle.addEventListener('click', function () {
                var expanded = toggle.getAttribute('aria-expanded') === 'true';
                toggle.setAttribute('aria-expanded', expanded ? 'false' : 'true');
                panel.hidden = expanded;
            });
            document.addEventListener('click', function (event) {
                if (!container.contains(event.target)) {
                    panel.hidden = true;
                    toggle.setAttribute('aria-expanded', 'false');
                }
            });
            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') {
                    panel.hidden = true;
                    toggle.setAttribute('aria-expanded', 'false');
                    toggle.focus();
                }
            });
        });
    </script>
@endsection

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
