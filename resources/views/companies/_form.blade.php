@if ($errors->any())
    <div class="form-alert" role="alert"><strong>Please check the form:</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif

<section class="form-card">
    <div class="form-card-heading">
        <span class="form-section-icon"><svg><use href="#icon-building"></use></svg></span>
        <div><h2>Company information</h2><p>Company details are used to organize products and stock reports.</p></div>
    </div>
    <form action="{{ $company ? route('companies.update', $company->id) : route('companies.store') }}" method="POST">
        @csrf
        @if ($company)
            @method('PUT')
        @endif
        <div class="form-grid">
            <div class="field">
                <label for="name">Company name <span class="required-mark">*</span></label>
                <input class="field-control" id="name" type="text" name="name" value="{{ old('name', $company ? $company->name : '') }}" maxlength="255" required>
                @error('name')<small class="field-error">{{ $message }}</small>@enderror
            </div>
            <div class="field">
                <label for="code">Company code <span class="required-mark">*</span></label>
                <input class="field-control" id="code" type="text" name="code" value="{{ old('code', $company ? $company->code : '') }}" maxlength="100" required>
                @error('code')<small class="field-error">{{ $message }}</small>@enderror
            </div>
            <div class="field field-wide">
                <label for="description">Description</label>
                <textarea class="field-control" id="description" name="description" rows="4" placeholder="Describe this company or brand (optional)">{{ old('description', $company ? $company->description : '') }}</textarea>
                @error('description')<small class="field-error">{{ $message }}</small>@enderror
            </div>
        </div>
        <div class="form-actions">
            <a class="button button-light" href="{{ $company ? route('companies.show', $company->id) : route('companies.index') }}">Cancel</a>
            <button class="button company-primary-button" type="submit">{{ $company ? 'Save Changes' : 'Add Company' }}</button>
        </div>
    </form>
</section>
