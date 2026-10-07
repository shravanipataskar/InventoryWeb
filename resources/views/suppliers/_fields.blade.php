<div class="form-section">
    <div class="form-card-heading"><span class="form-section-icon"><svg><use href="#icon-users"></use></svg></span><div><h2>Basic Information</h2><p>Enter the supplier code, company name and primary contact details.</p></div></div>
    <div class="form-grid">
        <div class="field">
            <label for="supplier_code">Supplier Code <span class="required-mark">*</span></label>
            <input class="field-control" id="supplier_code" type="text" name="supplier_code" value="{{ old('supplier_code', isset($supplier) ? $supplier->supplier_code : '') }}" placeholder="e.g. SUP-001" maxlength="100" required>
            @error('supplier_code')<small class="field-error">{{ $message }}</small>@enderror
        </div>
        <div class="field">
            <label for="company_name">Company Name <span class="required-mark">*</span></label>
            <input class="field-control" id="company_name" type="text" name="company_name" value="{{ old('company_name', isset($supplier) ? $supplier->company_name : '') }}" placeholder="e.g. ABC Electronics Pvt. Ltd." maxlength="255" required>
            @error('company_name')<small class="field-error">{{ $message }}</small>@enderror
        </div>
        <div class="field">
            <label for="name">Supplier / Contact Name <span class="required-mark">*</span></label>
            <input class="field-control" id="name" type="text" name="name" value="{{ old('name', isset($supplier) ? $supplier->name : '') }}" placeholder="e.g. Rahul Sharma" maxlength="255" required>
            @error('name')<small class="field-error">{{ $message }}</small>@enderror
        </div>
        <div class="field">
            <label for="phone">Phone / Mobile <span class="required-mark">*</span></label>
            <input class="field-control" id="phone" type="tel" name="phone" value="{{ old('phone', isset($supplier) ? $supplier->phone : '') }}" placeholder="e.g. 9876543210" maxlength="20" required>
            @error('phone')<small class="field-error">{{ $message }}</small>@enderror
        </div>
    </div>
</div>

<div class="form-section">
    <div class="form-card-heading"><span class="form-section-icon form-icon-gold"><svg><use href="#icon-layers"></use></svg></span><div><h2>Tax Information</h2><p>GST and PAN details for tax and compliance (optional).</p></div></div>
    <div class="form-grid">
        <div class="field">
            <label for="gst_number">GSTIN</label>
            <input class="field-control" id="gst_number" type="text" name="gst_number" value="{{ old('gst_number', isset($supplier) ? $supplier->gst_number : '') }}" placeholder="e.g. 27ABCDE1234F1Z5" maxlength="50">
            @error('gst_number')<small class="field-error">{{ $message }}</small>@enderror
        </div>
        <div class="field">
            <label for="pan_number">PAN</label>
            <input class="field-control" id="pan_number" type="text" name="pan_number" value="{{ old('pan_number', isset($supplier) ? $supplier->pan_number : '') }}" placeholder="e.g. ABCDE1234F" maxlength="20">
            @error('pan_number')<small class="field-error">{{ $message }}</small>@enderror
        </div>
    </div>
</div>

<div class="form-section">
    <div class="form-card-heading"><span class="form-section-icon"><svg><use href="#icon-grid"></use></svg></span><div><h2>Address</h2><p>Supplier's business address.</p></div></div>
    <div class="form-grid">
        <div class="field field-wide">
            <label for="address">Address</label>
            <textarea class="field-control" id="address" name="address" rows="3" maxlength="2000" placeholder="e.g. 123 MG Road, Near ABC Chowk">{{ old('address', isset($supplier) ? $supplier->address : '') }}</textarea>
            @error('address')<small class="field-error">{{ $message }}</small>@enderror
        </div>
        <div class="field">
            <label for="city">City</label>
            <input class="field-control" id="city" type="text" name="city" value="{{ old('city', isset($supplier) ? $supplier->city : '') }}" placeholder="e.g. Pune" maxlength="255">
            @error('city')<small class="field-error">{{ $message }}</small>@enderror
        </div>
        <div class="field">
            <label for="state">State</label>
            <input class="field-control" id="state" type="text" name="state" value="{{ old('state', isset($supplier) ? $supplier->state : '') }}" placeholder="e.g. Maharashtra" maxlength="255">
            @error('state')<small class="field-error">{{ $message }}</small>@enderror
        </div>
        <div class="field">
            <label for="pincode">Pincode</label>
            <input class="field-control" id="pincode" type="text" name="pincode" value="{{ old('pincode', isset($supplier) ? $supplier->pincode : '') }}" placeholder="e.g. 411001" maxlength="20">
            @error('pincode')<small class="field-error">{{ $message }}</small>@enderror
        </div>
    </div>
</div>

<div class="form-section">
    <div class="form-card-heading"><span class="form-section-icon"><svg><use href="#icon-calendar"></use></svg></span><div><h2>Payment Information</h2><p>Payment terms and bank details (optional).</p></div></div>
    <div class="form-grid">
        <div class="field">
            <label for="payment_terms">Payment Terms</label>
            <select class="field-control" id="payment_terms" name="payment_terms">
                <option value="">Select Payment Terms</option>
                @foreach (['due_on_receipt' => 'Due on Receipt', 'net_7' => 'Net 7 Days', 'net_15' => 'Net 15 Days', 'net_30' => 'Net 30 Days', 'net_45' => 'Net 45 Days', 'net_60' => 'Net 60 Days'] as $value => $label)
                    <option value="{{ $value }}" {{ old('payment_terms', isset($supplier) ? $supplier->payment_terms : '') === $value ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
            @error('payment_terms')<small class="field-error">{{ $message }}</small>@enderror
        </div>
        <div class="field">
            <label for="bank_name">Bank Name</label>
            <input class="field-control" id="bank_name" type="text" name="bank_name" value="{{ old('bank_name', isset($supplier) ? $supplier->bank_name : '') }}" placeholder="e.g. HDFC Bank" maxlength="255">
            @error('bank_name')<small class="field-error">{{ $message }}</small>@enderror
        </div>
        <div class="field">
            <label for="account_holder_name">Account Holder Name</label>
            <input class="field-control" id="account_holder_name" type="text" name="account_holder_name" value="{{ old('account_holder_name', isset($supplier) ? $supplier->account_holder_name : '') }}" placeholder="e.g. ABC Electronics Pvt. Ltd." maxlength="255">
            @error('account_holder_name')<small class="field-error">{{ $message }}</small>@enderror
        </div>
        <div class="field">
            <label for="account_number">Account Number</label>
            <input class="field-control" id="account_number" type="text" name="account_number" value="{{ old('account_number', isset($supplier) ? $supplier->account_number : '') }}" placeholder="e.g. 123456789012" maxlength="100">
            @error('account_number')<small class="field-error">{{ $message }}</small>@enderror
        </div>
        <div class="field">
            <label for="ifsc_code">IFSC Code</label>
            <input class="field-control" id="ifsc_code" type="text" name="ifsc_code" value="{{ old('ifsc_code', isset($supplier) ? $supplier->ifsc_code : '') }}" placeholder="e.g. HDFC0001234" maxlength="20">
            @error('ifsc_code')<small class="field-error">{{ $message }}</small>@enderror
        </div>
        <div class="field">
            <label for="branch_name">Branch Name</label>
            <input class="field-control" id="branch_name" type="text" name="branch_name" value="{{ old('branch_name', isset($supplier) ? $supplier->branch_name : '') }}" placeholder="e.g. Pune" maxlength="255">
            @error('branch_name')<small class="field-error">{{ $message }}</small>@enderror
        </div>
    </div>
</div>

<div class="form-section">
    <div class="form-card-heading"><span class="form-section-icon form-icon-violet"><svg><use href="#icon-layers"></use></svg></span><div><h2>Additional Information</h2><p>Any other relevant information about the supplier.</p></div></div>
    <div class="form-grid">
        <div class="field field-wide">
            <label for="notes">Notes / Remarks</label>
            <textarea class="field-control" id="notes" name="notes" rows="3" maxlength="5000" placeholder="e.g. Additional information about supplier, billing details, etc.">{{ old('notes', isset($supplier) ? $supplier->notes : '') }}</textarea>
            @error('notes')<small class="field-error">{{ $message }}</small>@enderror
        </div>
    </div>
</div>
