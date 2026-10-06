@extends('auth.layout')
@section('title', 'Forgot password · Aayojan Ai Inventory')
@section('content')
<div class="center-auth"><div class="auth-form-card compact-card">
    <a href="{{ route('landing') }}" class="auth-brand centered-brand"><span class="brand-icon">A</span><span><strong>Aayojan <em>Ai</em></strong><small>Inventory System</small></span></a>
    <div class="form-heading centered"><span class="form-icon">?</span><h2>Forgot your password?</h2><p>No worries. Enter your registered email and we'll send you a secure password reset link.</p></div>
    @if(session('status')) <div class="alert alert-success">✓ {{ session('status') }}</div> @endif
    @if($errors->any()) <div class="alert alert-error">{{ $errors->first() }}</div> @endif
    <form method="POST" action="{{ route('password.email') }}" class="auth-form">@csrf
        <label>Email address</label><div class="input-wrap"><span class="input-icon">@</span><input type="email" name="email" value="{{ old('email') }}" placeholder="you@company.com" autocomplete="email" required autofocus></div>
        <button class="btn btn-primary btn-submit" type="submit">Send reset link <span>→</span></button>
    </form>
    <a class="back-link" href="{{ route('login') }}">← Back to sign in</a>
</div></div>
@endsection
