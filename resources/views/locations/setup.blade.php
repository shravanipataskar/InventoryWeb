@extends('layouts.app')

@section('title', 'Location Setup')
@section('topbar-title', 'Warehouse Setup')

@section('content')
    <div class="page-heading workspace-page-heading">
        <div>
            <span class="section-kicker">MASTER DATA</span>
            <h1>Location Setup</h1>
            <p>Set up your warehouse and inventory storage locations.</p>
        </div>
    </div>

    @if ($hasExistingLocations)
        <div class="alert alert-info" role="alert">
            <svg class="alert-icon"><use href="#icon-info"></use></svg>
            <div>
                <strong>Locations Already Exist</strong>
                <p>You can add more locations or <a href="{{ route('locations.index') }}">view existing locations</a>.</p>
            </div>
        </div>
    @endif

    @if (session('success'))
        <div class="alert alert-success" role="alert">
            <svg class="alert-icon"><use href="#icon-check"></use></svg>
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->has('locations'))
        <div class="alert alert-error" role="alert">
            <svg class="alert-icon"><use href="#icon-x"></use></svg>
            {{ $errors->first('locations') }}
        </div>
    @endif

    <section class="panel setup-panel">
        <div class="setup-container">
            <!-- Step 1: Enter Number of Warehouses -->
            <div id="step-1" class="setup-step">
                <div class="step-header">
                    <div class="step-number">1</div>
                    <div>
                        <h2>How many warehouses do you need?</h2>
                        <p>Enter the number of warehouse locations you want to create.</p>
                    </div>
                </div>

                <div class="form-group">
                    <label for="warehouse_count">Number of Warehouses <span class="required">*</span></label>
                    <input type="number" 
                           id="warehouse_count" 
                           name="warehouse_count" 
                           min="1" 
                           max="50" 
                           value="{{ old('warehouse_count', 1) }}"
                           placeholder="e.g., 2"
                           class="form-input"
                           required>
                    <small class="help-text">You can create between 1 and 50 locations at once.</small>
                </div>

                <div class="form-group">
                    <label for="company_id">Company / Brand <span class="required">*</span></label>
                    <select id="company_id" name="company_id" class="form-select" required>
                        <option value="">Select a company</option>
                        @foreach ($companies as $company)
                            <option value="{{ $company->id }}" {{ old('company_id') == $company->id ? 'selected' : '' }}>
                                {{ $company->name }}
                            </option>
                        @endforeach
                    </select>
                    <small class="help-text">Associate these locations with a company/brand.</small>
                </div>

                <button type="button" id="generate-locations-btn" class="button button-primary">
                    Generate Location Fields
                </button>
            </div>

            <!-- Step 2: Enter Location Names (Hidden initially) -->
            <div id="step-2" class="setup-step" style="display: none;">
                <div class="step-header">
                    <div class="step-number">2</div>
                    <div>
                        <h2>Enter Location Names</h2>
                        <p>Provide a unique name for each warehouse location.</p>
                    </div>
                </div>

                <form id="locations-form" method="POST" action="{{ route('locations.store') }}">
                    @csrf
                    <input type="hidden" name="warehouse_count" id="hidden_warehouse_count">
                    <input type="hidden" name="company_id" id="hidden_company_id">

                    <div id="location-fields-container"></div>

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

                    <div class="form-actions">
                        <button type="button" id="back-btn" class="button button-light">
                            Back
                        </button>
                        <button type="submit" class="button button-primary">
                            Save Locations
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </section>

    <style>
        .setup-panel {
            max-width: 800px;
            margin: 0 auto;
        }

        .setup-container {
            padding: 40px;
        }

        .setup-step {
            animation: fadeIn 0.3s ease-in;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .step-header {
            display: flex;
            gap: 20px;
            margin-bottom: 32px;
            align-items: flex-start;
        }

        .step-number {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            font-weight: 700;
            flex-shrink: 0;
        }

        .step-header h2 {
            margin: 0 0 8px;
            font-size: 24px;
            color: #0f172a;
        }

        .step-header p {
            margin: 0;
            color: #64748b;
            font-size: 15px;
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
        .form-select {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 15px;
            transition: all 0.15s;
        }

        .form-input:focus,
        .form-select:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .help-text {
            display: block;
            margin-top: 6px;
            font-size: 13px;
            color: #64748b;
        }

        .location-field-group {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 16px;
            border: 1px solid #e2e8f0;
        }

        .location-field-group label {
            font-size: 13px;
            color: #475569;
            margin-bottom: 6px;
        }

        .location-field-label {
            display: block;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 12px;
            font-size: 15px;
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
            display: inline-flex;
            align-items: center;
            gap: 8px;
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

        .alert-success {
            background: #ecfdf5;
            border: 1px solid #16a34a;
            color: #166534;
        }

        .alert-error {
            background: #fef2f2;
            border: 1px solid #dc2626;
            color: #991b1b;
        }

        .alert-info {
            background: #eff6ff;
            border: 1px solid #2563eb;
            color: #1e40af;
        }

        .alert-info a {
            color: #1d4ed8;
            text-decoration: underline;
            font-weight: 600;
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

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const warehouseCountInput = document.getElementById('warehouse_count');
            const companySelect = document.getElementById('company_id');
            const generateBtn = document.getElementById('generate-locations-btn');
            const backBtn = document.getElementById('back-btn');
            const step1 = document.getElementById('step-1');
            const step2 = document.getElementById('step-2');
            const locationFieldsContainer = document.getElementById('location-fields-container');
            const hiddenWarehouseCount = document.getElementById('hidden_warehouse_count');
            const hiddenCompanyId = document.getElementById('hidden_company_id');

            // Generate location fields
            generateBtn.addEventListener('click', function() {
                const count = parseInt(warehouseCountInput.value);
                const companyId = companySelect.value;

                // Validation
                if (!companyId) {
                    alert('Please select a company first.');
                    companySelect.focus();
                    return;
                }

                if (!count || count < 1 || count > 50) {
                    alert('Please enter a valid number of warehouses (1-50).');
                    warehouseCountInput.focus();
                    return;
                }

                // Store values in hidden inputs
                hiddenWarehouseCount.value = count;
                hiddenCompanyId.value = companyId;

                // Generate fields
                generateLocationFields(count);

                // Show step 2, hide step 1
                step1.style.display = 'none';
                step2.style.display = 'block';
            });

            // Back button
            backBtn.addEventListener('click', function() {
                step2.style.display = 'none';
                step1.style.display = 'block';
            });

            // Generate location input fields
            function generateLocationFields(count) {
                locationFieldsContainer.innerHTML = '';

                for (let i = 0; i < count; i++) {
                    const fieldGroup = document.createElement('div');
                    fieldGroup.className = 'location-field-group';
                    
                    const oldValue = getOldValue(i);
                    
                    fieldGroup.innerHTML = `
                        <span class="location-field-label">Warehouse ${i + 1}</span>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label for="location_name_${i}">Location Name <span class="required">*</span></label>
                            <input type="text" 
                                   id="location_name_${i}" 
                                   name="locations[${i}][name]" 
                                   class="form-input" 
                                   value="${oldValue}"
                                   placeholder="e.g., Pune, Mumbai, Delhi"
                                   maxlength="255"
                                   required>
                        </div>
                    `;
                    
                    locationFieldsContainer.appendChild(fieldGroup);
                }

                // Focus first input
                const firstInput = document.getElementById('location_name_0');
                if (firstInput) {
                    setTimeout(() => firstInput.focus(), 100);
                }
            }

            // Get old input value from Laravel's old() helper if validation failed
            function getOldValue(index) {
                @if (old('locations'))
                    const oldLocations = @json(old('locations'));
                    if (oldLocations && oldLocations[index] && oldLocations[index].name) {
                        return oldLocations[index].name;
                    }
                @endif
                return '';
            }

            // If there are validation errors, regenerate the form
            @if (old('warehouse_count'))
                const oldCount = {{ old('warehouse_count') }};
                const oldCompanyId = {{ old('company_id') }};
                
                if (oldCount && oldCompanyId) {
                    hiddenWarehouseCount.value = oldCount;
                    hiddenCompanyId.value = oldCompanyId;
                    generateLocationFields(oldCount);
                    step1.style.display = 'none';
                    step2.style.display = 'block';
                }
            @endif
        });
    </script>
@endsection
