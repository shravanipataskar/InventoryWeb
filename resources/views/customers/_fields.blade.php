<style>
    .customer-form .form-section{padding:20px;margin-bottom:16px;border:1px solid #e8edf2;border-radius:12px;background:#fff}
    .customer-form .customer-document-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}
    .customer-form .customer-document-card{min-width:0;padding:16px;border:1px dashed #cbd5e1;border-radius:10px;background:#f8fafc}
    .customer-form .customer-document-card>label{display:block;margin-bottom:10px;color:#192b36;font-weight:700}
    .customer-form .customer-upload-prompt{display:flex;align-items:center;gap:12px;margin-bottom:12px;color:#526170}
    .customer-form .customer-upload-icon{display:grid;width:38px;height:38px;flex:0 0 38px;place-items:center;border-radius:10px;background:#e6f4f3;color:#118786}
    .customer-form .customer-upload-icon svg{width:19px;height:19px;fill:none;stroke:currentColor;stroke-linecap:round;stroke-linejoin:round;stroke-width:1.8}
    .customer-form .customer-file-info{display:flex;align-items:center;gap:12px;margin-top:12px;min-width:0}
    .customer-form .customer-file-preview{width:64px;height:64px;flex:0 0 64px;border:1px solid #e2e8f0;border-radius:8px;object-fit:cover;background:#fff}
    .customer-form .customer-file-meta{min-width:0;flex:1}
    .customer-form .customer-file-name{display:block;overflow:hidden;color:#243746;font-size:12px;font-weight:600;text-overflow:ellipsis;white-space:nowrap}
    .customer-form .customer-document-actions{display:flex;align-items:center;gap:10px;margin-top:10px}
    .customer-form .customer-code-note{display:block;margin-top:5px;color:#64748b;font-size:11px}
    .customer-form .customer-address-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px}
    @media(max-width:700px){.customer-form .customer-document-grid,.customer-form .customer-address-grid{grid-template-columns:1fr}.customer-form .form-section{padding:15px}}
</style>

<div class="customer-form">
    <div class="form-section">
        <div class="form-card-heading"><span class="form-section-icon"><svg><use href="#icon-users"></use></svg></span><div><h2>Basic Information</h2><p>Customer identity and classification.</p></div></div>
        <div class="form-grid">
            <div class="field">
                <label for="customer_code">Customer Code</label>
                <input class="field-control" id="customer_code" type="text" value="{{ isset($customer) ? $customer->customer_code : 'Generated when saved' }}" readonly>
                <small class="customer-code-note">Customer codes are generated automatically.</small>
            </div>
            <div class="field">
                <label for="customer_type">Customer Type <span class="required-mark">*</span></label>
                <select class="field-control" id="customer_type" name="customer_type" required>
                    <option value="">Select customer type</option>
                    @foreach ($customerTypes as $type)
                        <option value="{{ $type }}" {{ old('customer_type', isset($customer) ? $customer->customer_type : '') === $type ? 'selected' : '' }}>{{ $type }}</option>
                    @endforeach
                </select>
                @error('customer_type')<small class="field-error">{{ $message }}</small>@enderror
            </div>
            <div class="field">
                <label for="name">Customer / Contact Name <span class="required-mark">*</span></label>
                <input class="field-control" id="name" type="text" name="name" value="{{ old('name', isset($customer) ? $customer->name : '') }}" maxlength="255" required>
                @error('name')<small class="field-error">{{ $message }}</small>@enderror
            </div>
            <div class="field">
                <label for="contact_person">Contact Person</label>
                <input class="field-control" id="contact_person" type="text" name="contact_person" value="{{ old('contact_person', isset($customer) ? $customer->contact_person : '') }}" maxlength="255">
                @error('contact_person')<small class="field-error">{{ $message }}</small>@enderror
            </div>
        </div>
    </div>

    <div class="form-section">
        <div class="form-card-heading"><span class="form-section-icon form-icon-violet"><svg><use href="#icon-users"></use></svg></span><div><h2>Contact Information</h2><p>Primary contact details for this customer.</p></div></div>
        <div class="form-grid">
            <div class="field">
                <label for="phone">Phone / Mobile <span class="required-mark">*</span></label>
                <input class="field-control" id="phone" type="tel" name="phone" value="{{ old('phone', isset($customer) ? $customer->phone : '') }}" maxlength="30" placeholder="e.g. 9988776655" required>
                @error('phone')<small class="field-error">{{ $message }}</small>@enderror
            </div>
            <div class="field">
                <label for="email">Email</label>
                <input class="field-control" id="email" type="email" name="email" value="{{ old('email', isset($customer) ? $customer->email : '') }}" maxlength="255" placeholder="abc@example.com">
                @error('email')<small class="field-error">{{ $message }}</small>@enderror
            </div>
        </div>
    </div>

    <div class="form-section">
        <div class="form-card-heading"><span class="form-section-icon form-icon-gold"><svg><use href="#icon-location"></use></svg></span><div><h2>Address Information</h2><p>Store location and mailing address.</p></div></div>
        <div class="form-grid">
            <div class="field field-wide">
                <label for="store_warehouse_location">Store / Warehouse Location</label>
                <input class="field-control" id="store_warehouse_location" type="text" name="store_warehouse_location" value="{{ old('store_warehouse_location', isset($customer) ? $customer->store_warehouse_location : '') }}" maxlength="1000" placeholder="e.g. Shop No. 12, FC Road, Pune">
                @error('store_warehouse_location')<small class="field-error">{{ $message }}</small>@enderror
            </div>
            <div class="field field-wide">
                <label for="address">Address</label>
                <textarea class="field-control" id="address" name="address" rows="3" maxlength="2000">{{ old('address', isset($customer) ? $customer->address : '') }}</textarea>
                @error('address')<small class="field-error">{{ $message }}</small>@enderror
            </div>
        </div>
        <div class="customer-address-grid">
            <div class="field">
                <label for="city">City</label>
                <input class="field-control" id="city" type="text" name="city" value="{{ old('city', isset($customer) ? $customer->city : '') }}" maxlength="255">
                @error('city')<small class="field-error">{{ $message }}</small>@enderror
            </div>
            <div class="field">
                <label for="state">State</label>
                <input class="field-control" id="state" type="text" name="state" value="{{ old('state', isset($customer) ? $customer->state : '') }}" maxlength="255">
                @error('state')<small class="field-error">{{ $message }}</small>@enderror
            </div>
            <div class="field">
                <label for="pincode">Pincode</label>
                <input class="field-control" id="pincode" type="text" name="pincode" value="{{ old('pincode', isset($customer) ? $customer->pincode : '') }}" maxlength="20">
                @error('pincode')<small class="field-error">{{ $message }}</small>@enderror
            </div>
        </div>
    </div>

    <div class="form-section">
        <div class="form-card-heading"><span class="form-section-icon form-icon-violet"><svg><use href="#icon-box"></use></svg></span><div><h2>Identity &amp; Documents</h2><p>Upload customer identity documents for verification.</p></div></div>
        <div class="customer-document-grid">
            @foreach (['aadhaar_card' => 'Aadhaar Card', 'pan_card' => 'PAN Card'] as $document => $label)
                <div class="customer-document-card" data-customer-document>
                    <label for="{{ $document }}">{{ $label }}</label>
                    @if (isset($customer) && $customer->{$document} && \Illuminate\Support\Facades\Storage::disk('local')->exists($customer->{$document}))
                        <div class="customer-file-info">
                            <img class="customer-file-preview" src="{{ route('customers.documents.show', [$customer->id, $document]) }}" alt="{{ $label }} preview">
                            <div class="customer-file-meta">
                                <span class="customer-file-name">Existing {{ $label }}</span>
                                <small>Choose a new image below to replace this file.</small>
                                <div class="customer-document-actions">
                                    <a class="button button-small button-light" href="{{ route('customers.documents.show', [$customer->id, $document]) }}" target="_blank" rel="noopener">View</a>
                                </div>
                            </div>
                        </div>
                    @endif
                    <div class="customer-upload-prompt">
                        <span class="customer-upload-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 16V4m0 0L7 9m5-5 5 5M5 14v5h14v-5"/></svg></span>
                        <span>Choose or replace {{ $label }} image</span>
                    </div>
                    <input class="field-control" id="{{ $document }}" type="file" name="{{ $document }}" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" data-customer-file>
                    <small class="field-hint">JPG, JPEG, PNG or WEBP · Max 5 MB</small>
                    <div class="customer-selected-file" data-customer-selected hidden>
                        <div class="customer-file-info">
                            <img class="customer-file-preview" src="" alt="Selected {{ $label }} preview" data-customer-preview>
                            <div class="customer-file-meta"><span class="customer-file-name" data-customer-filename></span><small data-customer-filesize></small></div>
                        </div>
                        <button class="button button-small button-light" type="button" data-customer-clear>Remove selected file</button>
                    </div>
                    <small class="field-error" data-customer-file-error hidden></small>
                    @error($document)<small class="field-error">{{ $message }}</small>@enderror
                </div>
            @endforeach
        </div>
    </div>

    <div class="form-section">
        <div class="form-card-heading"><span class="form-section-icon form-icon-gold"><svg><use href="#icon-layers"></use></svg></span><div><h2>Additional Information</h2><p>Tax identifiers and account status.</p></div></div>
        <div class="form-grid">
            <div class="field">
                <label for="gstin">GSTIN (optional)</label>
                <input class="field-control" id="gstin" type="text" name="gstin" value="{{ old('gstin', isset($customer) ? $customer->gstin : '') }}" maxlength="20">
                @error('gstin')<small class="field-error">{{ $message }}</small>@enderror
            </div>
            <div class="field">
                <label for="pan">PAN (optional)</label>
                <input class="field-control" id="pan" type="text" name="pan" value="{{ old('pan', isset($customer) ? $customer->pan : '') }}" maxlength="20">
                @error('pan')<small class="field-error">{{ $message }}</small>@enderror
            </div>
            <div class="field">
                <label for="status">Status <span class="required-mark">*</span></label>
                <select class="field-control" id="status" name="status" required>
                    <option value="active" {{ old('status', isset($customer) && !$customer->is_active ? 'inactive' : 'active') === 'active' ? 'selected' : '' }}>Active</option>
                    <option value="inactive" {{ old('status', isset($customer) && !$customer->is_active ? 'inactive' : 'active') === 'inactive' ? 'selected' : '' }}>Inactive</option>
                </select>
                @error('status')<small class="field-error">{{ $message }}</small>@enderror
            </div>
        </div>
    </div>
</div>

<script>
    document.querySelectorAll('[data-customer-document]').forEach(function (card) {
        var input = card.querySelector('[data-customer-file]');
        var selected = card.querySelector('[data-customer-selected]');
        var preview = card.querySelector('[data-customer-preview]');
        var filename = card.querySelector('[data-customer-filename]');
        var filesize = card.querySelector('[data-customer-filesize]');
        var error = card.querySelector('[data-customer-file-error]');
        var clear = card.querySelector('[data-customer-clear]');
        var objectUrl = null;

        function clearSelection() {
            input.value = '';
            selected.hidden = true;
            error.hidden = true;
            error.textContent = '';
            if (objectUrl) {
                URL.revokeObjectURL(objectUrl);
                objectUrl = null;
            }
        }

        input.addEventListener('change', function () {
            clearSelection();
            var file = input.files[0];
            if (!file) return;

            var allowedExtension = /\.(jpe?g|png|webp)$/i.test(file.name);
            var allowedMime = ['image/jpeg', 'image/png', 'image/webp'].indexOf(file.type) !== -1;
            if (!allowedExtension || !allowedMime) {
                error.textContent = 'Please upload a valid ' + card.querySelector('label').textContent + ' image.';
                error.hidden = false;
                input.value = '';
                return;
            }
            if (file.size > 5 * 1024 * 1024) {
                error.textContent = 'Maximum file size is 5 MB.';
                error.hidden = false;
                input.value = '';
                return;
            }

            objectUrl = URL.createObjectURL(file);
            preview.src = objectUrl;
            filename.textContent = file.name;
            filesize.textContent = (file.size / 1024 / 1024).toFixed(2) + ' MB';
            selected.hidden = false;
        });

        clear.addEventListener('click', clearSelection);
    });

    var customerForm = document.querySelector('[data-customer-form]');
    if (customerForm) {
        customerForm.addEventListener('submit', function () {
            var submitButton = customerForm.querySelector('[data-customer-submit]');
            if (submitButton) {
                submitButton.disabled = true;
                submitButton.textContent = submitButton.getAttribute('data-loading-label') || 'Saving...';
            }
        });
    }
</script>
