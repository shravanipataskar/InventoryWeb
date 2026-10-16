@extends('layouts.app')

@section('title', 'Add Location')
@section('topbar-title', 'Add location')

@section('content')
    <div class="page-heading workspace-page-heading">
        <div>
            <span class="section-kicker">MASTER DATA / LOCATIONS</span>
            <h1>Add Inventory Location</h1>
            <p>Create warehouse or storage locations for tracking your company's inventory.</p>
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
        <form action="{{ route('locations.store') }}" method="POST" id="locationForm">
            @csrf
            <div class="form-section">
                <div class="form-card-heading">
                    <span class="form-section-icon"><svg><use href="#icon-location"></use></svg></span>
                    <div>
                        <h2>Location Setup</h2>
                        <p>Enter the number of warehouses/locations to create, or add a single location.</p>
                    </div>
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
                        <label for="location_count">Number of Locations to Create</label>
                        <input type="number" class="field-control" id="location_count" min="1" max="50" placeholder="Enter number (e.g., 2 for bulk)" value="{{ old('location_count', 1) }}">
                        <small class="field-hint">Enter 1 for single location or multiple for bulk creation</small>
                    </div>
                </div>

                <div style="margin: 20px 0;">
                    <button type="button" class="button button-light" id="generateFieldsBtn" onclick="generateLocationFields()">
                        Generate Location Fields
                    </button>
                </div>
            </div>

            <div id="dynamicLocationsContainer" style="display: none;">
                <div class="form-section">
                    <div class="form-card-heading">
                        <span class="form-section-icon"><svg><use href="#icon-building"></use></svg></span>
                        <div>
                            <h2>Location Details</h2>
                            <p>Enter unique names for each location. Codes will be auto-generated.</p>
                        </div>
                    </div>
                    <div id="locationFieldsContainer"></div>
                </div>

                <div class="form-actions">
                    <a class="button button-light" href="{{ route('locations.index') }}">Cancel</a>
                    <button class="button button-primary" type="submit">Save Location(s)</button>
                </div>
            </div>
        </form>
    </section>

    <style>
        .location-item {
            padding: 20px;
            margin-bottom: 20px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }
        .location-item h3 {
            margin: 0 0 15px;
            font-size: 16px;
            color: #334155;
            font-weight: 600;
        }
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

    <script>
        function generateLocationFields() {
            const count = parseInt(document.getElementById('location_count').value) || 1;
            
            if (count < 1 || count > 50) {
                alert('Please enter a number between 1 and 50');
                return;
            }

            const container = document.getElementById('locationFieldsContainer');
            const dynamicContainer = document.getElementById('dynamicLocationsContainer');
            
            container.innerHTML = '';
            
            for (let i = 0; i < count; i++) {
                const index = i;
                const itemDiv = document.createElement('div');
                itemDiv.className = 'location-item';
                
                const oldName = "{{ old('locations.0.name') ? old('locations.0.name') : '' }}".replace('0', index);
                const oldLocation = "{{ old('locations.0.location') ? old('locations.0.location') : '' }}".replace('0', index);
                const oldDescription = "{{ old('locations.0.description') ? old('locations.0.description') : '' }}".replace('0', index);
                
                itemDiv.innerHTML = `
                    <h3>Location ${i + 1}</h3>
                    <div class="form-grid">
                        <div class="field">
                            <label for="locations_${index}_name">Location Name <span class="required-mark">*</span></label>
                            <input type="text" class="field-control" 
                                   id="locations_${index}_name" 
                                   name="locations[${index}][name]" 
                                   value="${oldName}"
                                   placeholder="e.g., Pune Warehouse"
                                   maxlength="255" 
                                   required>
                            <small class="field-hint">Enter the actual location/warehouse name</small>
                        </div>
                        
                        <div class="field">
                            <label for="locations_${index}_location">Address / Area</label>
                            <input type="text" class="field-control" 
                                   id="locations_${index}_location" 
                                   name="locations[${index}][location]" 
                                   value="${oldLocation}"
                                   placeholder="e.g., Industrial Area, Pune"
                                   maxlength="255">
                        </div>
                        
                        <div class="field field-wide">
                            <label for="locations_${index}_description">Description (Optional)</label>
                            <textarea class="field-control" 
                                      id="locations_${index}_description" 
                                      name="locations[${index}][description]" 
                                      rows="2"
                                      maxlength="1000"
                                      placeholder="Additional details about this location">${oldDescription}</textarea>
                        </div>
                    </div>
                `;
                
                container.appendChild(itemDiv);
            }
            
            dynamicContainer.style.display = 'block';
            dynamicContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        // Auto-generate fields if old input exists (validation errors)
        document.addEventListener('DOMContentLoaded', function() {
            @if(old('locations'))
                const count = {{ count(old('locations', [])) }};
                if (count > 0) {
                    document.getElementById('location_count').value = count;
                    generateLocationFields();
                }
            @endif
        });
    </script>
@endsection
