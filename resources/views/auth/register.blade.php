@extends('auth.layout')
@section('title', 'Create account · Aayojan Ai Inventory')
@section('content')
<div class="split-auth reverse-mobile">
    <section class="auth-promo register-promo">
        <a href="{{ route('landing') }}" class="auth-brand light"><span class="brand-icon">A</span><span><strong>Aayojan <em>Ai</em></strong><small>Inventory System</small></span></a>
        <div class="promo-content"><span class="eyebrow light-eyebrow"><i></i> Start smarter</span><h1>Everything your<br><span>inventory needs.</span></h1><p>Create your workspace and bring products, suppliers and stock movement together.</p><div class="feature-checks"><div>✓ Easy product management</div><div>✓ Real-time stock visibility</div><div>✓ Secure team workspace</div></div></div>
        <div class="promo-bottom">Designed for focused, productive teams</div>
    </section>
    <section class="auth-form-area"><div class="auth-form-card">
        <div class="mobile-brand"><a href="{{ route('landing') }}" class="auth-brand"><span class="brand-icon">A</span><span><strong>Aayojan <em>Ai</em></strong><small>Inventory System</small></span></a></div>
        <div class="form-heading"><span class="form-icon">+</span><h2>Create your account</h2><p>Set up your inventory workspace in a minute.</p></div>
        @if($errors->any()) <div class="alert alert-error">{{ $errors->first() }}</div> @endif
        <form method="POST" action="{{ route('register.submit') }}" class="auth-form" novalidate>@csrf
            <label>Full name</label><div class="input-wrap"><span class="input-icon">✦</span><input type="text" name="name" value="{{ old('name') }}" placeholder="Your full name" autocomplete="name" required autofocus></div>
            <label>Email address</label><div class="input-wrap"><span class="input-icon">@</span><input type="email" name="email" value="{{ old('email') }}" placeholder="you@company.com" autocomplete="email" required></div>
            <div class="two-inputs"><div><label>Password</label><div class="input-wrap"><input type="password" id="register-password" name="password" placeholder="Minimum 8 characters" autocomplete="new-password" required><button type="button" class="toggle-password" data-target="register-password">Show</button></div></div><div><label>Confirm password</label><div class="input-wrap"><input type="password" id="register-confirm" name="password_confirmation" placeholder="Repeat password" autocomplete="new-password" required></div></div></div>
            <div class="password-meter"><span data-meter-bar></span><small data-meter-text>Use 8+ characters for a strong password</small></div>
            <label class="check-label terms"><input type="checkbox" required><span>I agree to the <a href="#">Terms</a> and <a href="#">Privacy Policy</a>.</span></label>
            <button class="btn btn-primary btn-submit" type="submit">Create account <span>→</span></button>
        </form>
        <p class="form-footer">Already have an account? <a href="{{ route('login') }}">Sign in</a></p>
    </div></section>
</div>
@endsection
