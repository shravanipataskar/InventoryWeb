@extends('layouts.app')

@section('title', 'Edit Location')
@section('topbar-title', 'Edit location')

@section('content')
    <div class="page-heading workspace-page-heading">
        <div>
            <span class="section-kicker">MASTER DATA / LOCATIONS</span>
            <h1>Edit Location</h1>
            <p>Update inventory location details.</p>
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
        <form action="{{ route('locations.update', $location->id) }}" method="POST">
            @csrf
            @method('PUT')
            
            <div class="form-section">
                <div class="form-card-heading">
                    <span class="form-section-icon"><svg><use href="#icon-location"></use></svg></span>
                    <div>
                        <h2>Location Details</h2>
                        <p>Update the location information. Location code will be auto-generated if changed.</p>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="field">
                        <label for="company_id">Company / Brand <span class="required-mark">*</span></label>
                        <select class="field-control" id="company_id" name="company_id" required>
                            <option value="">Select a company or brand</option>
                            @foreach ($companies as $company)
                                <option value="{{ $company->id }}" 
                                    {{ old('company_id', $location->company_id) == $company->id ? 'selected' : '' }}>
                                    {{ $company->name }} ({{ $company->code }})
                                </option>
                            @endforeach
                        </select>
                        @error('company_id')<small class="field-error">{{ $message }}</small>@enderror
                    </div>

                    <div class="field">
                        <label for="name">Location Name <span class="required-mark">*</span></label>
                        <input class="field-control" 
                               id="name" 
                               name="name" 
                               value="{{ old('name', $location->name) }}" 
                               maxlength="255" 
                               required>
                        @error('name')<small class="field-error">{{ $message }}</small>@enderror
                    </div>

                    <div class="field">
                        <label for="code">Location Code <span class="required-mark">*</span></label>
                        <input class="field-control" 
                               id="code" 
                               name="code" 
                               value="{{ old('code', $location->code) }}" 
                               maxlength="255" 
                               required>
                        @error('code')<small class="field-error">{{ $message }}</small>@enderror
                    </div>

                    <div class="field field-wide">
                        <label for="location">Address / Area</label>
                        <input class="field-control" 
                               id="location" 
                               name="location" 
                               value="{{ old('location', $location->location) }}" 
                               maxlength="255">
                        @error('location')<small class="field-error">{{ $message }}</small>@enderror
                    </div>

                    <div class="field field-wide">
                        <label for="description">Description (Optional)</label>
                        <textarea class="field-control" 
                                  id="description" 
                                  name="description" 
                                  rows="3"
                                  maxlength="1000">{{ old('description', $location->description) }}</textarea>
                        @error('description')<small class="field-error">{{ $message }}</small>@enderror
                    </div>
                </div>

                <div class="field" style="margin-top: 20px; padding: 15px; background: #f1f5f9; border-radius: 6px;">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="checkbox" disabled {{ $location->is_active ? 'checked' : '' }}>
                        <span>Location is {{ $location->is_active ? 'Active' : 'Inactive' }}</span>
                    </label>
                    <small class="field-hint">
                        To activate or deactivate this location, use the status toggle button on the locations list.
                    </small>
                </div>
            </div>

            <div class="form-actions">
                <a class="button button-light" href="{{ route('locations.index') }}">Cancel</a>
                <button class="button button-primary" type="submit">Update Location</button>
            </div>
        </form>
    </section>

    <style>
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        .field-wide {
            grid-column: 1 / -1;
        }
        .field-hint {
            display: block;
            margin-top: 5px;
            font-size: 12px;
            color: #64748b;
        }
    </style>
@endsection
