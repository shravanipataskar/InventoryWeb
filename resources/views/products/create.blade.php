@extends('layouts.app')

@section('title', 'Add Product')
@section('topbar-title', 'Add product')

@section('content')
    <div class="page-heading">
        <div><span class="section-kicker">CATALOGUE / PRODUCTS</span><h1>Add Product</h1><p>Create a product record for your inventory.</p></div>
        <a class="button button-light" href="{{ route('products.index') }}">Back to products</a>
    </div>
    @if ($errors->any())
        <div class="form-alert" role="alert"><strong>Please check the form:</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    <section class="form-card">
        <form action="{{ route('products.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="form-section">
                <div class="form-card-heading"><span class="form-section-icon"><svg><use href="#icon-box"></use></svg></span><div><h2>Product information</h2><p>Product identifiers are generated automatically when you save.</p></div></div>
                <div class="form-grid" data-location-selector data-racks-url="{{ route('locations.halls.racks', ['hall' => '__HALL__']) }}" data-shelves-url="{{ route('locations.racks.shelves', ['rack' => '__RACK__']) }}">
                    <div class="field"><label for="name">Product name <span class="required-mark">*</span></label><input class="field-control" id="name" type="text" name="name" value="{{ old('name') }}" placeholder="e.g. Wireless Mouse" maxlength="255" required>@error('name')<small class="field-error">{{ $message }}</small>@enderror</div>
                    <div class="field"><label for="company_id">Company / Brand</label><select class="field-control" id="company_id" name="company_id"><option value="">No company</option>@foreach ($companies as $company)<option value="{{ $company->id }}" {{ old('company_id') == $company->id ? 'selected' : '' }}>{{ $company->name }} ({{ $company->code }})</option>@endforeach</select>@error('company_id')<small class="field-error">{{ $message }}</small>@enderror</div>
                    <div class="field">
                        <label for="hall_id">Hall <span class="required-mark">*</span></label>
                        <select class="field-control" id="hall_id" name="hall_id" data-location-hall required>
                            <option value="">Select Hall</option>
                            @foreach ($halls as $hall)<option value="{{ $hall->id }}" {{ old('hall_id') == $hall->id ? 'selected' : '' }}>{{ $hall->name }}</option>@endforeach
                        </select>
                        @error('hall_id')<small class="field-error">{{ $message }}</small>@enderror
                        <small class="field-error" data-location-error aria-live="polite" hidden></small>
                    </div>
                    <div class="field">
                        <label for="rack_id">Rack</label>
                        <select class="field-control" id="rack_id" name="rack_id" data-location-rack disabled>
                            <option value="">Select Hall First</option>
                        </select>
                        <small class="field-hint">Optional</small>
                        @error('rack_id')<small class="field-error">{{ $message }}</small>@enderror
                    </div>
                    <div class="field">
                        <label for="shelf_id">Shelf</label>
                        <select class="field-control" id="shelf_id" name="shelf_id" data-location-shelf disabled>
                            <option value="">Select Rack First</option>
                        </select>
                        <small class="field-hint">Optional</small>
                        @error('shelf_id')<small class="field-error">{{ $message }}</small>@enderror
                    </div>
                    <div class="field"><label for="category_id">Category <span class="required-mark">*</span></label><select class="field-control" id="category_id" name="category_id" required><option value="">Select category</option>@foreach ($categories as $category)<option value="{{ $category->id }}" {{ old('category_id') == $category->id ? 'selected' : '' }}>{{ $category->name }}</option>@endforeach</select>@error('category_id')<small class="field-error">{{ $message }}</small>@enderror</div>
                    <div class="field"><label for="unit_id">Unit <span class="required-mark">*</span></label><select class="field-control" id="unit_id" name="unit_id" required><option value="">Select unit</option>@foreach ($units as $unit)<option value="{{ $unit->id }}" {{ old('unit_id') == $unit->id ? 'selected' : '' }}>{{ $unit->name }} ({{ $unit->short_name }})</option>@endforeach</select>@error('unit_id')<small class="field-error">{{ $message }}</small>@enderror</div>
                </div>
            </div>

            <div class="form-section">
                <div class="form-card-heading"><span class="form-section-icon form-icon-gold"><svg><use href="#icon-alert"></use></svg></span><div><h2>Stock settings</h2><p>Set stock thresholds and replenishment quantities.</p></div></div>
                <div class="form-grid">
                    <div class="field"><label for="minimum_stock">Minimum stock level <span class="required-mark">*</span></label><input class="field-control" id="minimum_stock" type="number" name="minimum_stock" value="{{ old('minimum_stock', 0) }}" min="0" step="0.01" required>@error('minimum_stock')<small class="field-error">{{ $message }}</small>@enderror</div>
                    <div class="field"><label for="reorder_level">Reorder level <span class="required-mark">*</span></label><input class="field-control" id="reorder_level" type="number" name="reorder_level" value="{{ old('reorder_level', 0) }}" min="0" step="0.01" required>@error('reorder_level')<small class="field-error">{{ $message }}</small>@enderror</div>
                    <div class="field"><label for="reorder_quantity">Reorder quantity <span class="required-mark">*</span></label><input class="field-control" id="reorder_quantity" type="number" name="reorder_quantity" value="{{ old('reorder_quantity', 0) }}" min="0" step="0.01" required>@error('reorder_quantity')<small class="field-error">{{ $message }}</small>@enderror</div>
                </div>
            </div>

            <div class="form-section">
                <div class="form-card-heading"><span class="form-section-icon form-icon-violet"><svg><use href="#icon-box"></use></svg></span><div><h2>Inventory tracking</h2><p>Enable only the tracking this product requires.</p></div></div>
                <div class="form-grid">
                    <div class="field"><label><input type="checkbox" name="track_batch" value="1" {{ old('track_batch') ? 'checked' : '' }}> Track batches / lots</label><small class="field-hint">Opening Stock will require a batch or lot for this product.</small>@error('track_batch')<small class="field-error">{{ $message }}</small>@enderror</div>
                    <div class="field"><label><input type="checkbox" name="track_serial" value="1" {{ old('track_serial') ? 'checked' : '' }}> Track serial numbers</label><small class="field-hint">Opening Stock will require one unique serial number per unit.</small>@error('track_serial')<small class="field-error">{{ $message }}</small>@enderror</div>
                </div>
            </div>

            <div class="form-section">
                <div class="form-card-heading"><span class="form-section-icon form-icon-violet"><svg><use href="#icon-box"></use></svg></span><div><h2>Product image</h2><p>Upload a JPG, JPEG, PNG or WEBP image (up to 5 MB).</p></div></div>
                <div class="form-grid">
                    <div class="field field-wide">
                        <label for="image">Image</label>
                        <input class="field-control product-image-input" id="image" type="file" name="image" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" data-product-image-input>
                        @error('image')<small class="field-error">{{ $message }}</small>@enderror
                        <div class="product-image-preview" data-product-image-preview hidden>
                            <img src="" alt="Selected product image preview" data-product-image>
                            <button class="button button-small button-light" type="button" data-clear-product-image>Remove preview</button>
                        </div>
                    </div>
                    <div class="field field-wide"><label for="description">Description</label><textarea class="field-control" id="description" name="description" rows="3" placeholder="Add a product description (optional)">{{ old('description') }}</textarea>@error('description')<small class="field-error">{{ $message }}</small>@enderror</div>
                </div>
            </div>
            <div class="form-actions"><a class="button button-light" href="{{ route('products.index') }}">Cancel</a><button class="button button-primary" type="submit">Save Product</button></div>
        </form>
    </section>
@endsection
