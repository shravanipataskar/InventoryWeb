@extends('layouts.app')

@section('title', 'Edit Product')
@section('topbar-title', 'Edit product')

@section('content')
    <div class="page-heading">
        <div><span class="section-kicker">CATALOGUE / PRODUCTS</span><h1>Edit Product</h1><p>Update the product information for {{ $product->name }}.</p></div>
        <a class="button button-light" href="{{ route('products.index') }}">Back to products</a>
    </div>
    @if ($errors->any())
        <div class="form-alert" role="alert"><strong>Please check the form:</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    <section class="form-card">
        <form action="{{ route('products.update', $product->id) }}" method="POST" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            <div class="form-section">
                <div class="form-card-heading"><span class="form-section-icon"><svg><use href="#icon-box"></use></svg></span><div><h2>Product information</h2><p>Names and identifiers used to recognize your product.</p></div></div>
                <div class="form-grid" data-location-selector data-racks-url="{{ route('locations.halls.racks', ['hall' => '__HALL__']) }}" data-shelves-url="{{ route('locations.racks.shelves', ['rack' => '__RACK__']) }}" data-current-hall="{{ $product->hall_id }}" data-initial-rack="{{ old('rack_id', optional($product->rack)->id) }}" data-initial-shelf="{{ old('shelf_id', optional($product->shelf)->id) }}" data-preserve-rack-id="{{ optional($product->rack)->id }}" data-preserve-rack-name="{{ optional($product->rack)->name }}" data-preserve-shelf-id="{{ optional($product->shelf)->id }}" data-preserve-shelf-name="{{ optional($product->shelf)->name }}">
                    <div class="field"><label for="name">Product name <span class="required-mark">*</span></label><input class="field-control" id="name" type="text" name="name" value="{{ old('name', $product->name) }}" maxlength="255" required>@error('name')<small class="field-error">{{ $message }}</small>@enderror</div>
                    <div class="field"><label for="product_code">Product code / SKU <span class="required-mark">*</span></label><input class="field-control" id="product_code" type="text" name="product_code" value="{{ old('product_code', $product->product_code) }}" maxlength="100" required>@error('product_code')<small class="field-error">{{ $message }}</small>@enderror</div>
                    <div class="field"><label for="barcode">Barcode</label><input class="field-control" id="barcode" type="text" name="barcode" value="{{ old('barcode', $product->barcode) }}" maxlength="100">@error('barcode')<small class="field-error">{{ $message }}</small>@enderror</div>
                    <div class="field">
                        <label for="hall_id">Hall <span class="required-mark">*</span></label>
                        <select class="field-control" id="hall_id" name="hall_id" data-location-hall required>
                            <option value="">Select Hall</option>
                            @foreach ($halls as $hall)<option value="{{ $hall->id }}" {{ old('hall_id', optional($product->hall)->id) == $hall->id ? 'selected' : '' }}>{{ $hall->name }}{{ !$hall->is_active ? ' (Inactive — existing location)' : '' }}</option>@endforeach
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
                        <label for="shelf_id">Shell</label>
                        <select class="field-control" id="shelf_id" name="shelf_id" data-location-shelf disabled>
                            <option value="">Select Rack First</option>
                        </select>
                        <small class="field-hint">Optional</small>
                        @error('shelf_id')<small class="field-error">{{ $message }}</small>@enderror
                    </div>
                    <div class="field"><label for="category_id">Category <span class="required-mark">*</span></label><select class="field-control" id="category_id" name="category_id" required><option value="">Select category</option>@foreach ($categories as $category)<option value="{{ $category->id }}" {{ old('category_id', $product->category_id) == $category->id ? 'selected' : '' }}>{{ $category->name }}</option>@endforeach</select>@error('category_id')<small class="field-error">{{ $message }}</small>@enderror</div>
                    <div class="field"><label for="unit_id">Unit <span class="required-mark">*</span></label><select class="field-control" id="unit_id" name="unit_id" required><option value="">Select unit</option>@foreach ($units as $unit)<option value="{{ $unit->id }}" {{ old('unit_id', $product->unit_id) == $unit->id ? 'selected' : '' }}>{{ $unit->name }} ({{ $unit->short_name }})</option>@endforeach</select>@error('unit_id')<small class="field-error">{{ $message }}</small>@enderror</div>
                    <div class="field field-wide" data-image-preview>
                        <label for="image">New Product Image</label>
                        <input class="field-control" id="image" type="file" name="image" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" data-image-input>
                        <small class="field-hint">JPG, JPEG, PNG or WEBP. Maximum file size: 2 MB. Optional.</small>
                        @error('image')<small class="field-error">{{ $message }}</small>@enderror
                        <div class="product-image-preview" data-image-preview-box {{ $product->image && Storage::disk('public')->exists($product->image) ? '' : 'hidden' }}>
                            @if ($product->image && Storage::disk('public')->exists($product->image))
                                <img src="{{ asset('storage/' . $product->image) }}" alt="Current product image" data-image-preview-img data-current-image="{{ asset('storage/' . $product->image) }}">
                                <span data-image-placeholder hidden>Product image preview</span>
                            @else
                                <img src="" alt="Selected product image preview" data-image-preview-img hidden>
                                <span data-image-placeholder>Product image preview</span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            <div class="form-section">
                <div class="form-card-heading"><span class="form-section-icon form-icon-gold"><svg><use href="#icon-arrow-up"></use></svg></span><div><h2>Pricing & stock settings</h2><p>Set the product prices and minimum stock threshold.</p></div></div>
                <div class="form-grid">
                    <div class="field"><label for="purchase_price">Purchase price <span class="required-mark">*</span></label><div class="input-prefix"><span>₹</span><input class="field-control" id="purchase_price" type="number" name="purchase_price" value="{{ old('purchase_price', $product->purchase_price) }}" min="0" step="0.01" required></div>@error('purchase_price')<small class="field-error">{{ $message }}</small>@enderror</div>
                    <div class="field"><label for="selling_price">Selling price <span class="required-mark">*</span></label><div class="input-prefix"><span>₹</span><input class="field-control" id="selling_price" type="number" name="selling_price" value="{{ old('selling_price', $product->selling_price) }}" min="0" step="0.01" required></div>@error('selling_price')<small class="field-error">{{ $message }}</small>@enderror</div>
                    <div class="field"><label for="minimum_stock">Minimum stock level <span class="required-mark">*</span></label><input class="field-control" id="minimum_stock" type="number" name="minimum_stock" value="{{ old('minimum_stock', $product->minimum_stock) }}" min="0" step="0.01" required>@error('minimum_stock')<small class="field-error">{{ $message }}</small>@enderror</div>
                </div>
            </div>
            <div class="form-section">
                <div class="form-card-heading"><span class="form-section-icon form-icon-violet"><svg><use href="#icon-layers"></use></svg></span><div><h2>Additional details</h2><p>Optional information to help your team.</p></div></div>
                <div class="form-grid"><div class="field field-wide"><label for="description">Description</label><textarea class="field-control" id="description" name="description" rows="3">{{ old('description', $product->description) }}</textarea>@error('description')<small class="field-error">{{ $message }}</small>@enderror</div></div>
            </div>
            <div class="form-actions"><a class="button button-light" href="{{ route('products.index') }}">Cancel</a><button class="button button-primary" type="submit">Update Product</button></div>
        </form>
    </section>
@endsection
