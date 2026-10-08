<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Dashboard') · Aayojan Ai Inventory System</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
          rel="stylesheet">

    <link href="{{ mix('css/app.css') }}" rel="stylesheet">

    {{-- Profile UI --}}
    <link href="{{ asset('css/profile.css') }}" rel="stylesheet">
</head>

<body>

    {{-- =========================================================
         ICON LIBRARY
    ========================================================== --}}
    <svg class="icon-library"
         xmlns="http://www.w3.org/2000/svg"
         aria-hidden="true">

        <symbol id="icon-grid" viewBox="0 0 24 24">
            <rect x="3.5" y="3.5" width="7" height="7" rx="1.5"/>
            <rect x="13.5" y="3.5" width="7" height="7" rx="1.5"/>
            <rect x="3.5" y="13.5" width="7" height="7" rx="1.5"/>
            <rect x="13.5" y="13.5" width="7" height="7" rx="1.5"/>
        </symbol>

        <symbol id="icon-box" viewBox="0 0 24 24">
            <path d="m12 3 8.5 4.5v9L12 21l-8.5-4.5v-9L12 3Z"/>
            <path d="m3.8 7.7 8.2 4.5 8.2-4.5"/>
            <path d="M12 12.2V21"/>
            <path d="M7.8 5.2l8.4 4.6"/>
        </symbol>

        <symbol id="icon-layers" viewBox="0 0 24 24">
            <path d="m12 3 9 5-9 5-9-5 9-5Z"/>
            <path d="m3 12 9 5 9-5"/>
            <path d="M3 16l9 5 9-5"/>
        </symbol>

        <symbol id="icon-users" viewBox="0 0 24 24">
            <path d="M16 20v-1.5a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4V20"/>
            <circle cx="9.5" cy="7.5" r="3.5"/>
            <path d="M17 11a3.5 3.5 0 1 0-1-6.8"/>
            <path d="M21 20v-1.5a4 4 0 0 0-3-3.9"/>
        </symbol>

        <symbol id="icon-building" viewBox="0 0 24 24">
            <path d="M4 21V5.5L12 2l8 3.5V21"/>
            <path d="M2 21h20M8 8h1M15 8h1M8 12h1M15 12h1M8 16h1M15 16h1M11 21v-4h2v4"/>
        </symbol>

        <symbol id="icon-chart" viewBox="0 0 24 24">
            <path d="M3 20h18M5 17V9h3v8M11 17V4h3v13M17 17v-6h3v6"/>
        </symbol>

        <symbol id="icon-location" viewBox="0 0 24 24">
            <path d="M20 10c0 5-8 12-8 12S4 15 4 10a8 8 0 1 1 16 0Z"/>
            <circle cx="12" cy="10" r="2.5"/>
        </symbol>

        <symbol id="icon-transfer" viewBox="0 0 24 24">
            <path d="M4 7h15l-3-3M20 17H5l3 3"/>
            <path d="M19 7v4M5 17v-4"/>
        </symbol>

        <symbol id="icon-adjustment" viewBox="0 0 24 24">
            <path d="M12 5v14M5 12h14"/>
            <circle cx="12" cy="12" r="9"/>
        </symbol>

        <symbol id="icon-settings" viewBox="0 0 24 24">
            <circle cx="12" cy="12" r="3"/>
            <path d="m19.4 15 .1.1 1.1.9-1.5 2.6-1.3-.5a7.8 7.8 0 0 1-1.6.9l-.2 1.4h-3l-.3-1.4a7.8 7.8 0 0 1-1.6-.9l-1.3.5-1.5-2.6 1.1-.9a7.6 7.6 0 0 1 0-1.9l-1.1-.9 1.5-2.6 1.3.5a7.8 7.8 0 0 1 1.6-.9l.3-1.4h3l.2 1.4a7.8 7.8 0 0 1 1.6.9l1.3-.5 1.5 2.6-1.1.9a7.6 7.6 0 0 1-.1 1.8Z"/>
        </symbol>

        <symbol id="icon-ruler" viewBox="0 0 24 24">
            <path d="m4 16 12-12 4 4L8 20H4v-4Z"/>
            <path d="m13 7 4 4"/>
            <path d="M10 10l2 2"/>
            <path d="M7 13l2 2"/>
        </symbol>

        <symbol id="icon-tray-in" viewBox="0 0 24 24">
            <path d="M4 4h16v11h-5l-2 3h-2l-2-3H4V4Z"/>
            <path d="M12 2v8"/>
            <path d="m9 7 3 3 3-3"/>
        </symbol>

        <symbol id="icon-tray-out" viewBox="0 0 24 24">
            <path d="M4 9h16v11H4V9Z"/>
            <path d="M12 2v9"/>
            <path d="m9 6 3 3 3-3"/>
            <path d="M4 14h5l2 3h2l2-3h5"/>
        </symbol>

        <symbol id="icon-menu" viewBox="0 0 24 24">
            <path d="M4 6h16"/>
            <path d="M4 12h16"/>
            <path d="M4 18h16"/>
        </symbol>

        <symbol id="icon-close" viewBox="0 0 24 24">
            <path d="m6 6 12 12"/>
            <path d="M18 6 6 18"/>
        </symbol>

        <symbol id="icon-calendar" viewBox="0 0 24 24">
            <rect x="3.5" y="5" width="17" height="16" rx="2"/>
            <path d="M7.5 3v4"/>
            <path d="M16.5 3v4"/>
            <path d="M3.5 10h17"/>
        </symbol>

        <symbol id="icon-arrow-up" viewBox="0 0 24 24">
            <path d="M12 19V5"/>
            <path d="m6 11 6-6 6 6"/>
        </symbol>

        <symbol id="icon-arrow-down" viewBox="0 0 24 24">
            <path d="M12 5v14"/>
            <path d="m18 13-6 6-6-6"/>
        </symbol>

        <symbol id="icon-check" viewBox="0 0 24 24">
            <path d="m5 12 4 4L19 6"/>
        </symbol>

        <symbol id="icon-alert" viewBox="0 0 24 24">
            <path d="M10.3 4.4 2.8 17.3A2 2 0 0 0 4.5 20h15a2 2 0 0 0 1.7-2.7L13.7 4.4a2 2 0 0 0-3.4 0Z"/>
            <path d="M12 9v4"/>
            <path d="M12 16h.01"/>
        </symbol>

        <symbol id="icon-close-small" viewBox="0 0 24 24">
            <path d="m7 7 10 10"/>
            <path d="M17 7 7 17"/>
        </symbol>

        <symbol id="icon-chevron" viewBox="0 0 24 24">
            <path d="m6 9 6 6 6-6"/>
        </symbol>

    </svg>


    {{-- =========================================================
         APPLICATION SHELL
    ========================================================== --}}
    <div class="app-shell">

        {{-- Mobile sidebar overlay --}}
        <div class="sidebar-scrim" data-sidebar-close></div>


        {{-- =====================================================
             SIDEBAR
        ====================================================== --}}
        <aside class="sidebar" id="app-sidebar">

            <a class="brand" href="{{ route('dashboard') }}">

                <span class="brand-mark">
                    <svg>
                        <use href="#icon-box"></use>
                    </svg>
                </span>

                <span class="brand-name">
                    Aayojan <span>Ai</span>
                    <small>Inventory System</small>
                </span>

                <button
                    class="sidebar-close icon-button"
                    type="button"
                    aria-label="Close menu"
                    data-sidebar-close>
                    <svg>
                        <use href="#icon-close"></use>
                    </svg>
                </button>

            </a>


            <nav class="sidebar-nav" aria-label="Main navigation">

                {{-- Dashboard --}}
                <a
                    class="nav-link {{ request()->routeIs('dashboard') ? 'is-active' : '' }}"
                    href="{{ route('dashboard') }}">

                    <svg>
                        <use href="#icon-grid"></use>
                    </svg>

                    <span>Dashboard</span>
                </a>

                {{-- Categories --}}
                <a
                    class="nav-link {{ request()->routeIs('categories.*') ? 'is-active' : '' }}"
                    href="{{ route('categories.index') }}">

                    <svg>
                        <use href="#icon-layers"></use>
                    </svg>

                    <span>Categories</span>
                </a>


                {{-- Units --}}
                <a
                    class="nav-link {{ request()->routeIs('units.*') ? 'is-active' : '' }}"
                    href="{{ route('units.index') }}">

                    <svg>
                        <use href="#icon-ruler"></use>
                    </svg>

                    <span>Units</span>
                </a>

                {{-- Locations --}}
                <a
                    class="nav-link {{ request()->routeIs('halls.*', 'racks.*', 'shelves.*') ? 'is-active' : '' }}"
                    href="{{ route('halls.index') }}">

                    <svg>
                        <use href="#icon-layers"></use>
                    </svg>

                    <span>Locations</span>
                </a>


                {{-- Products --}}
                <a
                    class="nav-link {{ request()->routeIs('products.*') ? 'is-active' : '' }}"
                    href="{{ route('products.index') }}">

                    <svg>
                        <use href="#icon-box"></use>
                    </svg>

                    <span>Products</span>
                </a>

                {{-- Companies --}}
                <a
                    class="nav-link {{ request()->routeIs('companies.*') ? 'is-active' : '' }}"
                    href="{{ route('companies.index') }}">
                    <svg><use href="#icon-building"></use></svg>
                    <span>Companies</span>
                </a>

                {{-- Suppliers --}}
                <a
                    class="nav-link {{ request()->routeIs('suppliers.*') ? 'is-active' : '' }}"
                    href="{{ route('suppliers.index') }}">

                    <svg>
                        <use href="#icon-users"></use>
                    </svg>

                    <span>Suppliers</span>
                </a>

                {{-- Customers --}}
                <a
                    class="nav-link {{ request()->routeIs('customers.*') ? 'is-active' : '' }}"
                    href="{{ route('customers.index') }}">
                    <svg><use href="#icon-users"></use></svg>
                    <span>Customers</span>
                </a>

                @php
                    $stockRoutes = ['opening-stock.*', 'stock-inwards.*', 'purchase-orders.*', 'goods-receipts.*', 'stock-outwards.*', 'stock-transfers.*', 'stock-adjustments.*', 'current-stock.*', 'stock-movement.*'];
                    $reportRoutes = ['reports.*', 'purchase-reports.*', 'issue-reports.*', 'stock-valuation.*'];
                    $settingsRoutes = ['users.*', 'activity-log.*', 'settings.*', 'general-settings.*'];
                    $stockIsActive = request()->routeIs(...$stockRoutes);
                    $reportsAreActive = request()->routeIs(...$reportRoutes);
                    $settingsAreActive = request()->routeIs(...$settingsRoutes);
                    $inwardRelatedRoutes = ['purchase-orders.*', 'goods-receipts.*'];
                    $inwardRelatedIsActive = request()->routeIs(...$inwardRelatedRoutes);
                @endphp

                <div class="nav-group {{ $stockIsActive ? 'is-open' : '' }}" data-nav-group>
                    <button class="nav-link nav-group-toggle {{ $stockIsActive ? 'is-active' : '' }}"
                            type="button"
                            aria-expanded="{{ $stockIsActive ? 'true' : 'false' }}"
                            aria-controls="stock-submenu"
                            data-nav-toggle>
                        <svg><use href="#icon-box"></use></svg>
                        <span>Stock</span>
                        <svg class="nav-chevron" aria-hidden="true"><use href="#icon-chevron"></use></svg>
                    </button>
                    <div class="nav-submenu {{ $stockIsActive ? 'is-open' : '' }}"
                         id="stock-submenu"
                         aria-label="Stock"
                         aria-hidden="{{ $stockIsActive ? 'false' : 'true' }}"
                         data-nav-panel>
                        <a class="nav-link {{ request()->routeIs('opening-stock.*') ? 'is-active' : '' }}"
                           href="{{ route('opening-stock.index') }}">
                            <svg><use href="#icon-box"></use></svg><span>Opening Stock</span>
                        </a>

                        <div class="nav-nested-group {{ $inwardRelatedIsActive ? 'is-open' : '' }}" data-nav-group>
                            <div class="nav-nested-row">
                                <a class="nav-link {{ request()->routeIs('stock-inwards.*') ? 'is-active' : '' }}"
                                   href="{{ route('stock-inwards.index') }}">
                                    <svg><use href="#icon-tray-in"></use></svg><span>Stock Inward</span>
                                </a>
                                <button class="nav-nested-toggle"
                                        type="button"
                                        aria-label="Toggle purchase order and goods received links"
                                        aria-expanded="{{ $inwardRelatedIsActive ? 'true' : 'false' }}"
                                        aria-controls="inward-related-submenu"
                                        data-nav-toggle>
                                    <svg class="nav-chevron" aria-hidden="true"><use href="#icon-chevron"></use></svg>
                                </button>
                            </div>
                            <div class="nav-submenu nav-submenu-nested {{ $inwardRelatedIsActive ? 'is-open' : '' }}"
                                 id="inward-related-submenu"
                                 aria-label="Purchase and receipt records"
                                 aria-hidden="{{ $inwardRelatedIsActive ? 'false' : 'true' }}"
                                 data-nav-panel>
                                <a class="nav-link {{ request()->routeIs('purchase-orders.*') ? 'is-active' : '' }}"
                                   href="{{ route('purchase-orders.index') }}">
                                    <svg><use href="#icon-tray-in"></use></svg><span>Purchase Orders</span>
                                </a>
                                <a class="nav-link {{ request()->routeIs('goods-receipts.*') ? 'is-active' : '' }}"
                                   href="{{ route('goods-receipts.index') }}">
                                    <svg><use href="#icon-check"></use></svg><span>Goods Received</span>
                                </a>
                            </div>
                        </div>

                        <a class="nav-link {{ request()->routeIs('stock-outwards.*') ? 'is-active' : '' }}"
                           href="{{ route('stock-outwards.index') }}">
                            <svg><use href="#icon-tray-out"></use></svg><span>Stock Outward</span>
                        </a>
                        <a class="nav-link {{ request()->routeIs('stock-transfers.*') ? 'is-active' : '' }}"
                           href="{{ route('stock-transfers.index') }}">
                            <svg><use href="#icon-transfer"></use></svg><span>Stock Transfer</span>
                        </a>
                        <a class="nav-link {{ request()->routeIs('stock-adjustments.*') ? 'is-active' : '' }}"
                           href="{{ route('stock-adjustments.index') }}">
                            <svg><use href="#icon-adjustment"></use></svg><span>Stock Adjustment</span>
                        </a>
                        <a class="nav-link {{ request()->routeIs('current-stock.*') ? 'is-active' : '' }}"
                           href="{{ route('current-stock.index') }}">
                            <svg><use href="#icon-box"></use></svg><span>Current Stock</span>
                        </a>
                        <a class="nav-link {{ request()->routeIs('stock-movement.*') ? 'is-active' : '' }}"
                           href="{{ route('stock-movement.index') }}">
                            <svg><use href="#icon-transfer"></use></svg><span>Stock Movement</span>
                        </a>
                    </div>
                </div>

                <div class="nav-group {{ $reportsAreActive ? 'is-open' : '' }}" data-nav-group>
                    <button class="nav-link nav-group-toggle {{ $reportsAreActive ? 'is-active' : '' }}"
                            type="button"
                            aria-expanded="{{ $reportsAreActive ? 'true' : 'false' }}"
                            aria-controls="reports-submenu"
                            data-nav-toggle>
                        <svg><use href="#icon-chart"></use></svg>
                        <span>Reports</span>
                        <svg class="nav-chevron" aria-hidden="true"><use href="#icon-chevron"></use></svg>
                    </button>
                    <div class="nav-submenu {{ $reportsAreActive ? 'is-open' : '' }}"
                         id="reports-submenu"
                         aria-label="Reports"
                         aria-hidden="{{ $reportsAreActive ? 'false' : 'true' }}"
                         data-nav-panel>
                        <a class="nav-link {{ request()->routeIs('reports.*') ? 'is-active' : '' }}"
                           href="{{ route('reports.index') }}">
                            <svg><use href="#icon-chart"></use></svg><span>Inventory Reports</span>
                        </a>
                        <a class="nav-link {{ request()->routeIs('purchase-reports.*') ? 'is-active' : '' }}"
                           href="{{ route('purchase-reports.index') }}">
                            <svg><use href="#icon-tray-in"></use></svg><span>Purchase Reports</span>
                        </a>
                        <a class="nav-link {{ request()->routeIs('issue-reports.*') ? 'is-active' : '' }}"
                           href="{{ route('issue-reports.index') }}">
                            <svg><use href="#icon-tray-out"></use></svg><span>Issue Reports</span>
                        </a>
                        <a class="nav-link {{ request()->routeIs('stock-valuation.*') ? 'is-active' : '' }}"
                           href="{{ route('stock-valuation.index') }}">
                            <svg><use href="#icon-chart"></use></svg><span>Stock Valuation</span>
                        </a>
                    </div>
                </div>

                <div class="nav-group {{ $settingsAreActive ? 'is-open' : '' }}" data-nav-group>
                    <button class="nav-link nav-group-toggle {{ $settingsAreActive ? 'is-active' : '' }}"
                            type="button"
                            aria-expanded="{{ $settingsAreActive ? 'true' : 'false' }}"
                            aria-controls="settings-submenu"
                            data-nav-toggle>
                        <svg><use href="#icon-settings"></use></svg>
                        <span>Settings</span>
                        <svg class="nav-chevron" aria-hidden="true"><use href="#icon-chevron"></use></svg>
                    </button>
                    <div class="nav-submenu {{ $settingsAreActive ? 'is-open' : '' }}"
                         id="settings-submenu"
                         aria-label="Settings"
                         aria-hidden="{{ $settingsAreActive ? 'false' : 'true' }}"
                         data-nav-panel>
                        <a class="nav-link {{ request()->routeIs('users.*') ? 'is-active' : '' }}"
                           href="{{ route('users.index') }}">
                            <svg><use href="#icon-users"></use></svg><span>Users</span>
                        </a>
                        <a class="nav-link {{ request()->routeIs('activity-log.*') ? 'is-active' : '' }}"
                           href="{{ route('activity-log.index') }}">
                            <svg><use href="#icon-chart"></use></svg><span>Activity Log</span>
                        </a>
                        <a class="nav-link {{ request()->routeIs('settings.*', 'general-settings.*') ? 'is-active' : '' }}"
                           href="{{ route('general-settings.index') }}">
                            <svg><use href="#icon-settings"></use></svg><span>General Settings</span>
                        </a>
                    </div>
                </div>

            </nav>


            {{-- Sidebar Footer --}}
            <div class="sidebar-footer">

                <span class="footer-orb"></span>

                <div>
                    <strong>Inventory overview</strong>
                    <span>Your stock, at a glance</span>
                </div>

            </div>

        </aside>


        {{-- =====================================================
             MAIN AREA
        ====================================================== --}}
        <main class="main-area">


            {{-- =================================================
                 TOP BAR
            ================================================== --}}
            <header class="topbar">


                {{-- Mobile menu --}}
                <button
                    class="mobile-menu icon-button"
                    type="button"
                    aria-label="Open menu"
                    aria-controls="app-sidebar"
                    aria-expanded="false"
                    data-sidebar-open>

                    <svg>
                        <use href="#icon-menu"></use>
                    </svg>

                </button>


                {{-- Page title --}}
                <div class="topbar-context">

                    <span class="topbar-eyebrow">
                        Aayojan Ai Inventory System
                    </span>

                    <span class="topbar-title">
                        @yield('topbar-title', 'Inventory overview')
                    </span>

                </div>


                {{-- =================================================
                     TOP RIGHT
                ================================================== --}}
                <div class="topbar-right">


                    {{-- Date --}}
                    <div class="date-chip">

                        <svg>
                            <use href="#icon-calendar"></use>
                        </svg>

                        <span>
                            {{ now()->format('D, M j, Y') }}
                        </span>

                    </div>


                    {{-- =================================================
                         PROFILE DROPDOWN
                    ================================================== --}}
                    @include('partials.profile-dropdown')


                </div>

            </header>


            {{-- =================================================
                 PAGE CONTENT
            ================================================== --}}
            <section class="page-content">

                @include('components.flash')

                @yield('content')

            </section>


        </main>

    </div>


    {{-- =========================================================
         APPLICATION JS
    ========================================================== --}}
    <script src="{{ mix('js/app.js') }}"></script>

</body>
</html>