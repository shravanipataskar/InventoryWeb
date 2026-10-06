@extends('layouts.app')

@section('content')
<link rel="stylesheet" href="{{ asset('css/profile.css') }}">

<div class="profile-page">
    <div class="profile-page-heading">
        <div class="profile-eyebrow">Aayojan Ai Inventory System</div>
        <h1>My Profile</h1>
        <p>View and update your personal information.</p>
    </div>

    @if(session('success'))
        <div class="profile-alert profile-alert-success">✓ {{ session('success') }}</div>
    @endif

    <div class="profile-grid">
        <div class="profile-card profile-summary-card">
            <div class="large-profile-avatar">{{ strtoupper(substr($user->name, 0, 1)) }}</div>
            <h2>{{ $user->name }}</h2>
            <p class="profile-email">{{ $user->email }}</p>
            <span class="profile-status"><span></span> Active</span>

            <div class="profile-summary-details">
                <div><span>Account</span><strong>Inventory User</strong></div>
                <div><span>Member Since</span><strong>{{ optional($user->created_at)->format('d M Y') }}</strong></div>
            </div>
        </div>

        <div class="profile-card">
            <div class="profile-card-heading">
                <h2>Edit Profile</h2>
                <p>Keep your account information up to date.</p>
            </div>

            <form method="POST" action="{{ route('profile.update') }}">
                @csrf
                @method('PUT')

                <div class="profile-form-group">
                    <label>Full Name <span>*</span></label>
                    <input type="text" name="name" value="{{ old('name', $user->name) }}" required>
                    @error('name') <small class="profile-error">{{ $message }}</small> @enderror
                </div>

                <div class="profile-form-group">
                    <label>Email Address <span>*</span></label>
                    <input type="email" name="email" value="{{ old('email', $user->email) }}" required>
                    @error('email') <small class="profile-error">{{ $message }}</small> @enderror
                </div>

                <div class="profile-form-actions">
                    <a href="{{ route('dashboard') }}" class="profile-btn profile-btn-secondary">Cancel</a>
                    <button type="submit" class="profile-btn profile-btn-primary">Update Profile →</button>
                </div>
            </form>
        </div>
    </div>

    <div class="profile-security-card">
        <div>
            <div class="profile-security-icon">🔒</div>
            <div><h3>Password & Security</h3><p>Keep your account secure with a strong password.</p></div>
        </div>
        <a href="{{ route('password.change') }}" class="profile-btn profile-btn-secondary">Change Password</a>
    </div>
</div>
@endsection
