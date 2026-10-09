@extends('layouts.app')

@section('title', 'Add User')
@section('topbar-title', 'Add user')

@section('content')
    <div class="user-create-page">
        <div class="page-heading">
            <div>
                <span class="section-kicker">ADMINISTRATION / USERS</span>
                <h1>Add User</h1>
                <p>Create an account and assign its role before the user signs in.</p>
            </div>
        </div>

        <section class="panel workspace-panel user-create-card">
            <form class="user-create-form" method="POST" action="{{ route('users.store') }}" autocomplete="off">
                @csrf
                <div class="user-create-grid">
                    <div class="user-create-field">
                        <label for="name">Full Name <span class="required-mark">*</span></label>
                        <input class="field-control" id="name" name="name" value="{{ old('name') }}" required>
                        @error('name')<small class="field-error">{{ $message }}</small>@enderror
                    </div>
                    <div class="user-create-field">
                        <label for="email">Email Address <span class="required-mark">*</span></label>
                        <input class="field-control" id="email" name="email" type="email" value="{{ old('email') }}" required>
                        @error('email')<small class="field-error">{{ $message }}</small>@enderror
                    </div>
                    <div class="user-create-field">
                        <label for="phone">Phone Number</label>
                        <input class="field-control" id="phone" name="phone" type="tel" inputmode="tel" autocomplete="tel" value="{{ old('phone') }}">
                        @error('phone')<small class="field-error">{{ $message }}</small>@enderror
                    </div>
                    <div class="user-create-field">
                        <label for="role_id">Role <span class="required-mark">*</span></label>
                        <select class="field-control" id="role_id" name="role_id" required>
                            <option value="">Select a role</option>
                            @foreach ($roles as $role)
                                <option value="{{ $role->id }}" {{ (string) old('role_id') === (string) $role->id ? 'selected' : '' }}>{{ $role->label }}</option>
                            @endforeach
                        </select>
                        @error('role_id')<small class="field-error">{{ $message }}</small>@enderror
                    </div>
                    <div class="user-create-field">
                        <label for="password">Password <span class="required-mark">*</span></label>
                        <input class="field-control" id="password" name="password" type="password" autocomplete="new-password" required>
                        @error('password')<small class="field-error">{{ $message }}</small>@enderror
                    </div>
                    <div class="user-create-field">
                        <label for="password_confirmation">Confirm Password <span class="required-mark">*</span></label>
                        <input class="field-control" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
                        @error('password_confirmation')<small class="field-error">{{ $message }}</small>@enderror
                    </div>
                    <div class="user-create-field">
                        <label for="status">Account Status <span class="required-mark">*</span></label>
                        <select class="field-control" id="status" name="status" required>
                            <option value="active" {{ old('status', 'active') === 'active' ? 'selected' : '' }}>Active</option>
                            <option value="inactive" {{ old('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
                        </select>
                        @error('status')<small class="field-error">{{ $message }}</small>@enderror
                    </div>
                </div>
                <div class="user-create-actions">
                    <a class="button button-light" href="{{ route('users.index') }}">Cancel / Back to Users</a>
                    <button class="button button-primary" type="submit">Create User</button>
                </div>
            </form>
        </section>
    </div>

    <style>
        .user-create-page {
            width: 100%;
            max-width: 1040px;
        }

        .user-create-card {
            overflow: visible;
            padding: 26px;
            border-radius: 12px;
            background: #fff;
            box-shadow: 0 3px 12px rgba(24, 48, 60, 0.04);
        }

        .user-create-form,
        .user-create-form *,
        .user-create-form *::before,
        .user-create-form *::after {
            box-sizing: border-box;
        }

        .user-create-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            column-gap: 24px;
            row-gap: 22px;
        }

        .user-create-field {
            display: flex;
            min-width: 0;
            flex-direction: column;
            align-items: stretch;
            gap: 7px;
            margin: 0;
        }

        .user-create-field label {
            display: block;
            margin: 0;
            color: #50616b;
            font-size: 12px;
            font-weight: 700;
            line-height: 1.4;
        }

        .user-create-field .field-control {
            display: block;
            width: 100%;
            min-width: 0;
            height: 46px;
            min-height: 46px;
            margin: 0;
            padding: 0 13px;
            border: 1px solid #e4eaed;
            border-radius: 8px;
            background-color: #fff;
            color: #364b56;
            font: inherit;
            font-size: 13px;
            line-height: normal;
        }

        .user-create-field .field-control:focus {
            border-color: #0f8b8d;
            outline: 0;
            box-shadow: 0 0 0 3px rgba(15, 139, 141, 0.12);
        }

        .user-create-field .field-error {
            display: block;
            margin: 0;
            font-size: 11px;
            line-height: 1.45;
        }

        .user-create-field .field-error {
            color: #bd514c;
        }

        .user-create-actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid #edf0f2;
        }

        .user-create-actions .button {
            min-height: 48px;
            box-sizing: border-box;
            padding: 0 18px;
            font-size: 12px;
        }

        .user-create-actions .button-light {
            border: 1px solid #d7e0e3;
        }

        @media (max-width: 640px) {
            .user-create-card {
                padding: 22px;
            }

            .user-create-grid {
                grid-template-columns: minmax(0, 1fr);
                row-gap: 18px;
            }

            .user-create-actions {
                align-items: stretch;
                flex-direction: column;
            }

            .user-create-actions .button {
                width: 100%;
            }
        }
    </style>
@endsection
