@extends('layouts.app')

@section('title', 'Add User')
@section('topbar-title', 'Add user')

@section('content')
    <div class="page-heading">
        <div>
            <span class="section-kicker">ADMINISTRATION / USERS</span>
            <h1>Add User</h1>
            <p>Create an account and assign its role before the user signs in.</p>
        </div>
        <a class="button button-light" href="{{ route('users.index') }}">Back to users</a>
    </div>

    <section class="panel workspace-panel">
        <form method="POST" action="{{ route('users.store') }}">
            @csrf
            <div class="form-grid">
                <div class="field">
                    <label for="name">Name <span class="required-mark">*</span></label>
                    <input class="field-control" id="name" name="name" value="{{ old('name') }}" required>
                    @error('name')<small class="field-error">{{ $message }}</small>@enderror
                </div>
                <div class="field">
                    <label for="email">Email <span class="required-mark">*</span></label>
                    <input class="field-control" id="email" name="email" type="email" value="{{ old('email') }}" required>
                    @error('email')<small class="field-error">{{ $message }}</small>@enderror
                </div>
                <div class="field">
                    <label for="password">Password <span class="required-mark">*</span></label>
                    <input class="field-control" id="password" name="password" type="password" required>
                    @error('password')<small class="field-error">{{ $message }}</small>@enderror
                </div>
                <div class="field">
                    <label for="password_confirmation">Confirm Password <span class="required-mark">*</span></label>
                    <input class="field-control" id="password_confirmation" name="password_confirmation" type="password" required>
                </div>
                <div class="field">
                    <label for="role_id">Role <span class="required-mark">*</span></label>
                    <select class="field-control" id="role_id" name="role_id" required>
                        <option value="">Select a role</option>
                        @foreach ($roles as $role)
                            <option value="{{ $role->id }}" {{ (string) old('role_id') === (string) $role->id ? 'selected' : '' }}>{{ $role->label }}</option>
                        @endforeach
                    </select>
                    @error('role_id')<small class="field-error">{{ $message }}</small>@enderror
                </div>
            </div>
            <div class="form-actions">
                <button class="button button-primary" type="submit">Create User</button>
            </div>
        </form>
    </section>
@endsection
