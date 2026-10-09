@extends('layouts.app')

@section('title', 'My Profile')
@section('topbar-title', 'My Profile')

@section('content')

<div class="profile-page">

    <div class="page-heading">
        <div>
            <span class="topbar-eyebrow">
                Aayojan Ai Inventory System
            </span>

            <h1>My Profile</h1>

            <p>
                View and update your personal information.
            </p>
        </div>
    </div>


    <div class="profile-card">

        <div class="profile-summary">

            <div class="large-avatar">
                {{ strtoupper(substr($user->name, 0, 2)) }}
            </div>

            <h2>{{ $user->name }}</h2>

            <p>{{ $user->email }}</p>

            @php
                $assignedRole = $user->roles->first();
                $profileRole = optional($assignedRole)->label
                    ?: optional($assignedRole)->name
                    ?: $user->role;
            @endphp
            <span class="profile-role">
                {{ $profileRole ? ucwords(strtolower(str_replace('_', ' ', $profileRole))) : 'No role assigned' }}
            </span>

            <span class="status-badge">
                <span></span>
                Active
            </span>

        </div>


        <div class="profile-details">

            <h2>Personal Information</h2>

            <form
                method="POST"
                action="{{ route('profile.update') }}">

                @csrf
                @method('PUT')

                <div class="form-group">

                    <label>Full Name</label>

                    <input
                        type="text"
                        name="name"
                        value="{{ old('name', $user->name) }}"
                        required>

                </div>


                <div class="form-group">

                    <label>Email Address</label>

                    <input
                        type="email"
                        name="email"
                        value="{{ old('email', $user->email) }}"
                        required>

                </div>


                <button type="submit" class="primary-button">
                    Update Profile
                </button>

            </form>

        </div>

    </div>

</div>

@endsection