@extends('layouts.app')

@section('title', 'Settings')
@section('topbar-title', 'Settings')

@section('content')
    <div class="page-heading workspace-page-heading">
        <div><span class="section-kicker">PREFERENCES</span><h1>General Settings</h1><p>Maintain the organization contact details used by this inventory system.</p></div>
    </div>

    <section class="form-card">
        <form action="{{ route('general-settings.update') }}" method="POST">
            @csrf
            @method('PUT')
            <div class="form-section">
                <div class="form-card-heading">
                    <span class="form-section-icon"><svg><use href="#icon-building"></use></svg></span>
                    <div><h2>Organization profile</h2><p>These details are stored locally and can be updated at any time.</p></div>
                </div>

                @if ($errors->any())
                    <div class="form-alert" role="alert">
                        <strong>Please check the form:</strong>
                        <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                    </div>
                @endif

                <div class="form-grid">
                    <div class="field field-wide">
                        <label for="business_name">Organization name <span class="required-mark">*</span></label>
                        <input class="field-control" id="business_name" name="business_name" value="{{ old('business_name', $settings['business_name']) }}" maxlength="150" required>
                        @error('business_name')<small class="field-error">{{ $message }}</small>@enderror
                    </div>
                    <div class="field">
                        <label for="business_email">Contact email</label>
                        <input class="field-control" id="business_email" type="email" name="business_email" value="{{ old('business_email', $settings['business_email']) }}" maxlength="255">
                        @error('business_email')<small class="field-error">{{ $message }}</small>@enderror
                    </div>
                    <div class="field">
                        <label for="business_phone">Contact phone</label>
                        <input class="field-control" id="business_phone" type="tel" name="business_phone" value="{{ old('business_phone', $settings['business_phone']) }}" maxlength="30">
                        @error('business_phone')<small class="field-error">{{ $message }}</small>@enderror
                    </div>
                    <div class="field field-wide">
                        <label for="business_address">Business address</label>
                        <textarea class="field-control" id="business_address" name="business_address" rows="3" maxlength="500">{{ old('business_address', $settings['business_address']) }}</textarea>
                        @error('business_address')<small class="field-error">{{ $message }}</small>@enderror
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <button class="button button-primary" type="submit">Save General Settings</button>
            </div>
        </form>
    </section>

    <section class="workspace-settings-grid">
        <a class="workspace-setting-card" href="{{ route('profile.show') }}">
            <span class="workspace-summary-icon workspace-blue"><svg><use href="#icon-users"></use></svg></span>
            <span><strong>Profile</strong><small>Update your name and account details.</small></span>
            <b aria-hidden="true">→</b>
        </a>
        <a class="workspace-setting-card" href="{{ route('password.change') }}">
            <span class="workspace-summary-icon workspace-purple"><svg><use href="#icon-settings"></use></svg></span>
            <span><strong>Change Password</strong><small>Update the password used to sign in.</small></span>
            <b aria-hidden="true">→</b>
        </a>
    </section>
@endsection
