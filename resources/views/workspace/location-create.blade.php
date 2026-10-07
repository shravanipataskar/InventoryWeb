@extends('layouts.app')

@section('title', 'Add Location')
@section('topbar-title', 'Add location')

@section('content')
    <div class="page-heading workspace-page-heading">
        <div>
            <span class="section-kicker">MASTER DATA / LOCATIONS</span>
            <h1>Add Location</h1>
            <p>Create a store or area that can be used when recording inventory.</p>
        </div>
        <a class="button button-light" href="{{ route('locations.index') }}">Back to Locations</a>
    </div>

    @if ($errors->any())
        <div class="form-alert" role="alert">
            <strong>Please check the form:</strong>
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <section class="form-card">
        <form action="{{ route('locations.store') }}" method="POST">
            @csrf
            <div class="form-section">
                <div class="form-card-heading">
                    <span class="form-section-icon"><svg><use href="#icon-location"></use></svg></span>
                    <div><h2>Location details</h2><p>Use a unique code to identify this location.</p></div>
                </div>

                <div class="form-grid">
                    <div class="field">
                        <label for="company_id">Company / Brand <span class="required-mark">*</span></label>
                        <select class="field-control" id="company_id" name="company_id" required>
                            <option value="">Select a company or brand</option>
                            @foreach ($companies as $company)
                                <option value="{{ $company->id }}" {{ old('company_id') == $company->id ? 'selected' : '' }}>
                                    {{ $company->name }} ({{ $company->code }})
                                </option>
                            @endforeach
                        </select>
                        @error('company_id')<small class="field-error">{{ $message }}</small>@enderror
                    </div>

                    <div class="field">
                        <label for="name">Location name <span class="required-mark">*</span></label>
                        <input class="field-control" id="name" name="name" value="{{ old('name') }}" maxlength="255" required>
                        @error('name')<small class="field-error">{{ $message }}</small>@enderror
                    </div>

                    <div class="field">
                        <label for="code">Location code <span class="required-mark">*</span></label>
                        <input class="field-control" id="code" name="code" value="{{ old('code') }}" maxlength="255" required>
                        @error('code')<small class="field-error">{{ $message }}</small>@enderror
                    </div>

                    <div class="field field-wide">
                        <label for="location">Address / Area</label>
                        <input class="field-control" id="location" name="location" value="{{ old('location') }}" maxlength="255">
                        @error('location')<small class="field-error">{{ $message }}</small>@enderror
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <a class="button button-light" href="{{ route('locations.index') }}">Cancel</a>
                <button class="button button-primary" type="submit">Save Location</button>
            </div>
        </form>
    </section>
@endsection
