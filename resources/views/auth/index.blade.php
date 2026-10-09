@extends('auth.layout')
@section('title', 'Aayojan Ai · Inventory System')
@section('content')
<div class="landing-shell">
    <nav class="landing-nav">
        <a href="{{ route('landing') }}" class="auth-brand">
            <span class="brand-icon">A</span>
            <span><strong>Aayojan <em>Ai</em></strong><small>Inventory System</small></span>
        </a>
        <div class="landing-nav-actions">
            <span class="nav-question">Already have an account?</span>
            <a class="btn btn-outline btn-small" href="{{ route('login') }}">Sign in</a>
        </div>
    </nav>

    <section class="hero-grid">
        <div class="hero-copy">
            <span class="eyebrow"><i></i> Smart inventory workspace</span>
            <h1>Inventory management,<br><span>made beautifully simple.</span></h1>
            <p>Keep products, stock movement, suppliers and inventory value organized in one intelligent workspace built for modern teams.</p>
            <div class="hero-actions">
                <a href="{{ route('login') }}" class="text-action">I already have an account <span>→</span></a>
            </div>
            <div class="trust-row">
                <div class="avatar-stack"><b>AK</b><b>PM</b><b>RS</b></div>
                <div><strong>Built for productive teams</strong><small>Simple. Secure. Ready to scale.</small></div>
            </div>
        </div>

        <div class="hero-visual">
            <div class="glow glow-one"></div><div class="glow glow-two"></div>
            <div class="dashboard-mock">
                <div class="mock-top"><span class="mock-brand"><b>A</b> Aayojan <em>Ai</em></span><span class="mock-user">AA</span></div>
                <div class="mock-title"><div><small>OVERVIEW</small><h3>Inventory dashboard</h3></div><span class="mock-date">Today · Oct 06</span></div>
                <div class="metric-grid">
                    <div class="metric-card"><small>Total products</small><strong>248</strong><span class="positive">↑ 12.4%</span></div>
                    <div class="metric-card"><small>Stock value</small><strong>₹4.82L</strong><span class="positive">↑ 8.7%</span></div>
                    <div class="metric-card"><small>Low stock</small><strong>12</strong><span class="warning">Needs attention</span></div>
                </div>
                <div class="activity-card">
                    <div class="activity-head"><strong>Stock activity</strong><span>Last 7 days</span></div>
                    <div class="chart"><i style="height:32%"></i><i style="height:52%"></i><i style="height:40%"></i><i style="height:70%"></i><i style="height:58%"></i><i style="height:84%"></i><i style="height:66%"></i><i style="height:92%"></i></div>
                </div>
                <div class="floating-card floating-stock"><span class="floating-icon">↗</span><div><small>Stock inward</small><strong>+184 units</strong></div></div>
                <div class="floating-card floating-alert"><span class="alert-dot"></span><div><small>Inventory alert</small><strong>3 items low</strong></div></div>
            </div>
        </div>
    </section>

    <section class="feature-row">
        <div><span>01</span><strong>Product control</strong><small>Everything organized in one place.</small></div>
        <div><span>02</span><strong>Stock visibility</strong><small>Know what moves in and out.</small></div>
        <div><span>03</span><strong>Better decisions</strong><small>Clear numbers for your next move.</small></div>
    </section>
</div>
@endsection
