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


            {{-- Workspace --}}
            <div class="sidebar-caption">
                WORKSPACE
            </div>

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


                {{-- Products --}}
                <a
                    class="nav-link {{ request()->routeIs('products.*') ? 'is-active' : '' }}"
                    href="{{ route('products.index') }}">

                    <svg>
                        <use href="#icon-box"></use>
                    </svg>

                    <span>Products</span>
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


                {{-- Stock Movement --}}
                <div class="sidebar-caption sidebar-caption-spaced">
                    STOCK MOVEMENT
                </div>


                {{-- Stock Inward --}}
                <a
                    class="nav-link {{ request()->routeIs('stock-inwards.*') ? 'is-active' : '' }}"
                    href="{{ route('stock-inwards.index') }}">

                    <svg>
                        <use href="#icon-tray-in"></use>
                    </svg>

                    <span>Stock Inward</span>
                </a>


                {{-- Stock Outward --}}
                <a
                    class="nav-link {{ request()->routeIs('stock-outwards.*') ? 'is-active' : '' }}"
                    href="{{ route('stock-outwards.index') }}">

                    <svg>
                        <use href="#icon-tray-out"></use>
                    </svg>

                    <span>Stock Outward</span>
                </a>

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