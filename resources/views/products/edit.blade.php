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
        <form action="{{ route('products.update', $product->id) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="form-section">
                <div class="form-card-heading"><span class="form-section-icon"><svg><use href="#icon-box"></use></svg></span><div><h2>Product information</h2><p>Names and identifiers used to recognize your product.</p></div></div>
                <div class="form-grid">
                    <div class="field"><label for="name">Product name <span class="required-mark">*</span></label><input class="field-control" id="name" type="text" name="name" value="{{ old('name', $product->name) }}" maxlength="255" required>@error('name')<small class="field-error">{{ $message }}</small>@enderror</div>
                    <div class="field"><label for="product_code">Product code / SKU <span class="required-mark">*</span></label><input class="field-control" id="product_code" type="text" name="product_code" value="{{ old('product_code', $product->product_code) }}" maxlength="100" required>@error('product_code')<small class="field-error">{{ $message }}</small>@enderror</div>
                    <div class="field"><label for="barcode">Barcode</label><input class="field-control" id="barcode" type="text" name="barcode" value="{{ old('barcode', $product->barcode) }}" maxlength="100">@error('barcode')<small class="field-error">{{ $message }}</small>@enderror</div>
                    <div class="field"><label for="hall">Hall <span class="required-mark">*</span></label><input class="field-control" id="hall" type="text" name="hall" value="{{ old('hall', $product->hall) }}" placeholder="e.g. H1" maxlength="50" pattern="[A-Za-z0-9]+" title="Use letters and numbers only." required><small class="field-hint">Use letters and numbers only.</small>@error('hall')<small class="field-error">{{ $message }}</small>@enderror</div>
                    <div class="field"><label for="rack">Rack <span class="required-mark">*</span></label><input class="field-control" id="rack" type="text" name="rack" value="{{ old('rack', $product->rack) }}" placeholder="e.g. R01" maxlength="50" pattern="[A-Za-z0-9]+" title="Use letters and numbers only." required><small class="field-hint">Use letters and numbers only.</small>@error('rack')<small class="field-error">{{ $message }}</small>@enderror</div>
                    <div class="field"><label for="shell">Shell <span class="required-mark">*</span></label><input class="field-control" id="shell" type="text" name="shell" value="{{ old('shell', $product->shell) }}" placeholder="e.g. S01" maxlength="50" pattern="[A-Za-z0-9]+" title="Use letters and numbers only." required><small class="field-hint">Use letters and numbers only.</small>@error('shell')<small class="field-error">{{ $message }}</small>@enderror</div>
                    <div class="field"><label for="category_id">Category <span class="required-mark">*</span></label><select class="field-control" id="category_id" name="category_id" required><option value="">Select category</option>@foreach ($categories as $category)<option value="{{ $category->id }}" {{ old('category_id', $product->category_id) == $category->id ? 'selected' : '' }}>{{ $category->name }}</option>@endforeach</select>@error('category_id')<small class="field-error">{{ $message }}</small>@enderror</div>
                    <div class="field"><label for="unit_id">Unit <span class="required-mark">*</span></label><select class="field-control" id="unit_id" name="unit_id" required><option value="">Select unit</option>@foreach ($units as $unit)<option value="{{ $unit->id }}" {{ old('unit_id', $product->unit_id) == $unit->id ? 'selected' : '' }}>{{ $unit->name }} ({{ $unit->short_name }})</option>@endforeach</select>@error('unit_id')<small class="field-error">{{ $message }}</small>@enderror</div>
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
