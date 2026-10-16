@extends('layouts.app')

@section('title', 'Edit Location')
@section('topbar-title', 'Edit Location')

@section('content')
    <div class="page-heading workspace-page-heading">
        <div>
            <span class="section-kicker">MASTER DATA / LOCATIONS</span>
            <h1>Edit Location</h1>
            <p>Update warehouse location details.</p>
        </div>
    </div>

    @if ($errors->any())
        <div class="alert alert-error" role="alert">
            <svg class="alert-icon"><use href="#icon-x"></use></svg>
            <div>
                <strong>Please fix the following errors:</strong>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    <section class="panel form-panel">
        <form method="POST" action="{{ route('locations.update', $location->id) }}">
            @csrf
            @method('PUT')

            <div class="form-group">
                <label for="name">Location Name <span class="required">*</span></label>
                <input type="text" 
                       id="name" 
                       name="name" 
                       class="form-input @error('name') is-invalid @enderror" 
                       value="{{ old('name', $location->name) }}"
                       maxlength="255"
                       required>
                @error('name')
                    <span class="error-message">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group">
                <label for="code">Location Code <span class="required">*</span></label>
                <input type="text" 
                       id="code" 
                       name="code" 
                       class="form-input @error('code') is-invalid @enderror" 
                       value="{{ old('code', $location->code) }}"
                       maxlength="255"
                       required>
                <small class="help-text">Unique identifier for this location.</small>
                @error('code')
                    <span class="error-message">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group">
                <label for="company_id">Company / Brand <span class="required">*</span></label>
                <select id="company_id" 
                        name="company_id" 
                        class="form-select @error('company_id') is-invalid @enderror" 
                        required>
                    <option value="">Select a company</option>
                    @foreach ($companies as $company)
                        <option value="{{ $company->id }}" 
                                {{ old('company_id', $location->company_id) == $company->id ? 'selected' : '' }}>
                            {{ $company->name }}
                        </option>
                    @endforeach
                </select>
                @error('company_id')
                    <span class="error-message">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group">
                <label for="location">Address / Area</label>
                <input type="text" 
                       id="location" 
                       name="location" 
                       class="form-input @error('location') is-invalid @enderror" 
                       value="{{ old('location', $location->location) }}"
                       maxlength="255"
                       placeholder="e.g., Pune, Maharashtra">
                <small class="help-text">Physical address or area of this location.</small>
                @error('location')
                    <span class="error-message">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" 
                          name="description" 
                          class="form-textarea @error('description') is-invalid @enderror" 
                          rows="3"
                          maxlength="1000"
                          placeholder="Additional details about this location...">{{ old('description', $location->description) }}</textarea>
                <small class="help-text">Optional notes or details about this location.</small>
                @error('description')
                    <span class="error-message">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-actions">
                <a href="{{ route('locations.index') }}" class="button button-light">
                    Cancel
                </a>
                <button type="submit" class="button button-primary">
                    Update Location
                </button>
            </div>
        </form>
    </section>

    <style>
        .form-panel {
            max-width: 700px;
            margin: 0 auto;
            padding: 32px;
        }

        .form-group {
            margin-bottom: 24px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #334155;
            font-size: 14px;
        }

        .required {
            color: #dc2626;
        }

        .form-input,
        .form-select,
        .form-textarea {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 15px;
            transition: all 0.15s;
        }

        .form-input:focus,
        .form-select:focus,
        .form-textarea:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .form-input.is-invalid,
        .form-select.is-invalid,
        .form-textarea.is-invalid {
            border-color: #dc2626;
        }

        .form-textarea {
            resize: vertical;
            font-family: inherit;
        }

        .help-text {
            display: block;
            margin-top: 6px;
            font-size: 13px;
            color: #64748b;
        }

        .error-message {
            display: block;
            margin-top: 6px;
            font-size: 13px;
            color: #dc2626;
        }

        .form-actions {
            display: flex;
            gap: 12px;
            margin-top: 32px;
            padding-top: 24px;
            border-top: 1px solid #e2e8f0;
        }

        .button {
            padding: 10px 24px;
            border-radius: 6px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.15s;
            border: none;
            text-decoration: none;
            display: inline-block;
        }

        .button-primary {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: white;
        }

        .button-primary:hover {
            background: linear-gradient(135deg, #1d4ed8, #1e40af);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
        }

        .button-light {
            background: white;
            color: #475569;
            border: 1px solid #cbd5e1;
        }

        .button-light:hover {
            background: #f8f9fa;
            border-color: #94a3b8;
        }

        .alert {
            padding: 16px 20px;
            border-radius: 8px;
            margin-bottom: 24px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }

        .alert-icon {
            width: 20px;
            height: 20px;
            flex-shrink: 0;
            margin-top: 2px;
        }

        .alert-error {
            background: #fef2f2;
            border: 1px solid #dc2626;
            color: #991b1b;
        }

        .alert ul {
            margin: 8px 0 0;
            padding-left: 20px;
        }

        .alert li {
            margin-bottom: 4px;
        }

        .alert strong {
            display: block;
            margin-bottom: 4px;
        }
    </style>
@endsection
