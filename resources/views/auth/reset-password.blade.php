@extends('auth.layout')
@section('title', 'Reset password · Aayojan Ai Inventory')
@section('content')
<div class="center-auth"><div class="auth-form-card compact-card">
    <a href="{{ route('landing') }}" class="auth-brand centered-brand"><span class="brand-icon">A</span><span><strong>Aayojan <em>Ai</em></strong><small>Inventory System</small></span></a>
    <div class="form-heading centered"><span class="form-icon">✓</span><h2>Create a new password</h2><p>Choose a strong password you haven't used before.</p></div>
    @if($errors->any()) <div class="alert alert-error">{{ $errors->first() }}</div> @endif
    <form method="POST" action="{{ route('password.update') }}" class="auth-form">@csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <label>Email address</label><div class="input-wrap"><span class="input-icon">@</span><input type="email" name="email" value="{{ old('email', $email) }}" placeholder="you@company.com" required></div>
        <label>New password</label><div class="input-wrap"><input type="password" id="reset-password" name="password" placeholder="Minimum 8 characters" required><button type="button" class="toggle-password" data-target="reset-password">Show</button></div>
        <label>Confirm password</label><div class="input-wrap"><input type="password" name="password_confirmation" placeholder="Repeat your password" required></div>
        <button class="btn btn-primary btn-submit" type="submit">Reset password <span>→</span></button>
    </form>
    <a class="back-link" href="{{ route('login') }}">← Back to sign in</a>
</div></div>
@endsection
