@extends('layouts.app')

@section('content')
<link rel="stylesheet" href="{{ asset('css/profile.css') }}">

<div class="profile-page password-page">
    <div class="profile-page-heading">
        <div class="profile-eyebrow">Aayojan Ai Inventory System</div>
        <h1>Change Password</h1>
        <p>Update your password to keep your account secure.</p>
    </div>

    <div class="password-card">
        <div class="password-card-icon">🔒</div>
        <div class="profile-card-heading">
            <h2>Change Password</h2>
            <p>Enter your current password and choose a new password.</p>
        </div>

        <form method="POST" action="{{ route('password.change.update') }}">
            @csrf
            @method('PUT')

            <div class="profile-form-group">
                <label>Current Password <span>*</span></label>
                <input type="password" name="current_password" required>
                @error('current_password') <small class="profile-error">{{ $message }}</small> @enderror
            </div>

            <div class="profile-form-group">
                <label>New Password <span>*</span></label>
                <input type="password" name="password" minlength="8" required>
                <div class="password-help">Minimum 8 characters. Use letters, numbers and symbols.</div>
                @error('password') <small class="profile-error">{{ $message }}</small> @enderror
            </div>

            <div class="profile-form-group">
                <label>Confirm New Password <span>*</span></label>
                <input type="password" name="password_confirmation" minlength="8" required>
            </div>

            <div class="profile-form-actions">
                <a href="{{ route('profile.show') }}" class="profile-btn profile-btn-secondary">Cancel</a>
                <button type="submit" class="profile-btn profile-btn-primary">Update Password →</button>
            </div>
        </form>
    </div>
</div>
@endsection
