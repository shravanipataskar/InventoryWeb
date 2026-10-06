@extends('layouts.app')

@section('title', 'Change Password')
@section('topbar-title', 'Change Password')

@section('content')

<div class="profile-page">

    <div class="page-heading">

        <div>

            <span class="topbar-eyebrow">
                Aayojan Ai Inventory System
            </span>

            <h1>Change Password</h1>

            <p>
                Update your password to keep your account secure.
            </p>

        </div>

    </div>


    <div class="password-card">

        <h2>Change Password</h2>

        <p class="card-description">
            Enter your current password and choose a new password.
        </p>


        <form
            method="POST"
            action="{{ route('password.change.update') }}">

            @csrf
            @method('PUT')


            <div class="form-group">

                <label>Current Password</label>

                <input
                    type="password"
                    name="current_password"
                    required>

            </div>


            <div class="form-group">

                <label>New Password</label>

                <input
                    type="password"
                    name="password"
                    required>

            </div>


            <div class="password-hint">

                Password must be at least 8 characters long.

            </div>


            <div class="form-group">

                <label>Confirm New Password</label>

                <input
                    type="password"
                    name="password_confirmation"
                    required>

            </div>


            <div class="form-actions">

                <a
                    href="{{ route('dashboard') }}"
                    class="secondary-button">

                    Cancel

                </a>


                <button
                    type="submit"
                    class="primary-button">

                    Update Password

                </button>

            </div>

        </form>

    </div>

</div>

@endsection