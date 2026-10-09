@extends('auth.layout')
@section('title', 'Sign in · Aayojan Ai Inventory')
@section('content')
<div class="split-auth">
    <section class="auth-promo">
        <a href="{{ route('landing') }}" class="auth-brand light"><span class="brand-icon">A</span><span><strong>Aayojan <em>Ai</em></strong><small>Inventory System</small></span></a>
        <div class="promo-content">
            <span class="eyebrow light-eyebrow"><i></i> Welcome back</span>
            <h1>Your inventory.<br><span>Your way.</span></h1>
            <p>Access your workspace and keep every product, supplier and stock movement under control.</p>
            <div class="mini-stats"><div><strong>248+</strong><small>Products tracked</small></div><div><strong>99.9%</strong><small>Data reliability</small></div></div>
        </div>
        <div class="promo-bottom">Aayojan Ai · Smart business software</div>
    </section>

    <section class="auth-form-area">
        <div class="auth-form-card">
            <div class="mobile-brand"><a href="{{ route('landing') }}" class="auth-brand"><span class="brand-icon">A</span><span><strong>Aayojan <em>Ai</em></strong><small>Inventory System</small></span></a></div>
            <div class="form-heading"><span class="form-icon">↗</span><h2>Welcome back</h2><p>Sign in to your inventory workspace.</p></div>

            @if(session('status')) <div class="alert alert-success">✓ {{ session('status') }}</div> @endif
            @if($errors->any()) <div class="alert alert-error">{{ $errors->first() }}</div> @endif

            <form method="POST" action="{{ route('login.submit') }}" class="auth-form" novalidate>
                @csrf
                <label>Email address</label>
                <div class="input-wrap"><span class="input-icon">@</span><input type="email" name="email" value="{{ old('email') }}" placeholder="you@company.com" autocomplete="email" required autofocus></div>
                <label>Password</label>
                <div class="input-wrap"><span class="input-icon">●</span><input type="password" id="login-password" name="password" placeholder="Enter your password" autocomplete="current-password" required><button type="button" class="toggle-password" data-target="login-password">Show</button></div>
                <div class="form-options"><label class="check-label"><input type="checkbox" name="remember" value="1" {{ old('remember') ? 'checked' : '' }}><span>Remember me</span></label><a href="{{ route('password.request') }}">Forgot password?</a></div>
                <button class="btn btn-primary btn-submit" type="submit">Sign in <span>→</span></button>
            </form>
            <p class="form-footer">Need access? Contact your system administrator.</p>
        </div>
    </section>
</div>
@endsection
