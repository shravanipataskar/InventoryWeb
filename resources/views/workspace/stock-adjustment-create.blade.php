@extends('layouts.app')

@section('title', 'Record Stock Adjustment')
@section('topbar-title', 'Record stock adjustment')

@section('content')
    <div class="page-heading workspace-page-heading">
        <div>
            <span class="section-kicker">INVENTORY / STOCK ADJUSTMENT</span>
            <h1>Record Stock Adjustment</h1>
            <p>Correct on-hand quantity and keep a dated record of the reason.</p>
        </div>
        <a class="button button-light" href="{{ route('stock-adjustments.index') }}">Back to Stock Adjustments</a>
    </div>

    @if ($errors->any())
        <div class="form-alert" role="alert">
            <strong>Please check the form:</strong>
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <section class="form-card stock-adjustment-form-card">
        <form action="{{ route('stock-adjustments.store') }}" method="POST">
            @csrf
            <div class="form-section">
                <div class="form-card-heading">
                    <span class="form-section-icon"><svg><use href="#icon-adjustment"></use></svg></span>
                    <div><h2>Adjustment details</h2><p>Saving this adjustment updates the product's on-hand quantity immediately.</p></div>
                </div>

                <div class="form-grid stock-adjustment-grid">
                    <div class="field field-wide">
                        <label for="product_id">Product <span class="required-mark">*</span></label>
                        <select class="field-control" id="product_id" name="product_id" required>
                            <option value="">Select a product</option>
                            @foreach ($products as $product)
                                <option value="{{ $product->id }}" {{ old('product_id') == $product->id ? 'selected' : '' }}>
                                    {{ $product->name }} ({{ $product->product_code }}) — On hand: {{ number_format($product->current_stock, 2) }} {{ optional($product->unit)->short_name }}
                                </option>
                            @endforeach
                        </select>
                        @error('product_id')<small class="field-error">{{ $message }}</small>@enderror
                    </div>

                    @if ($requiresStore)
                        <div class="field field-wide">
                            <label for="store_id">Location <span class="required-mark">*</span></label>
                            <select class="field-control" id="store_id" name="store_id" required>
                                <option value="">Select a location</option>
                                @foreach ($locations as $location)
                                    <option value="{{ $location->id }}" {{ old('store_id') == $location->id ? 'selected' : '' }}>{{ $location->name }}</option>
                                @endforeach
                            </select>
                            @error('store_id')<small class="field-error">{{ $message }}</small>@enderror
                            @if (!$locations->count())
                                <small class="field-hint">Add an active location before recording a stock adjustment.</small>
                            @endif
                        </div>
                    @endif

                    <div class="field">
                        <label for="adjustment_type">Adjustment type <span class="required-mark">*</span></label>
                        <select class="field-control" id="adjustment_type" name="adjustment_type" required>
                            <option value="increase" {{ old('adjustment_type', 'increase') === 'increase' ? 'selected' : '' }}>Increase stock</option>
                            <option value="decrease" {{ old('adjustment_type') === 'decrease' ? 'selected' : '' }}>Decrease stock</option>
                        </select>
                        @error('adjustment_type')<small class="field-error">{{ $message }}</small>@enderror
                    </div>

                    <div class="field">
                        <label for="quantity">Quantity <span class="required-mark">*</span></label>
                        <input class="field-control" id="quantity" type="number" name="quantity" value="{{ old('quantity') }}" min="0.01" step="0.01" required>
                        @error('quantity')<small class="field-error">{{ $message }}</small>@enderror
                    </div>

                    <div class="field">
                        <label for="adjustment_date">Adjustment date <span class="required-mark">*</span></label>
                        <input class="field-control" id="adjustment_date" type="date" name="adjustment_date" value="{{ old('adjustment_date', now()->toDateString()) }}" required>
                        @error('adjustment_date')<small class="field-error">{{ $message }}</small>@enderror
                    </div>

                    <div class="field">
                        <label for="reason">Reason <span class="required-mark">*</span></label>
                        <input class="field-control" id="reason" name="reason" value="{{ old('reason') }}" maxlength="255" required>
                        @error('reason')<small class="field-error">{{ $message }}</small>@enderror
                    </div>
                </div>
            </div>

            @if (!$products->count() || ($requiresStore && !$locations->count()))
                <div class="inventory-notice notice-out">
                    <span class="notice-symbol" aria-hidden="true">!</span>
                    <div><strong>Setup required</strong><small>@if (!$products->count())Add an active product before recording an adjustment.@elseif ($requiresStore && !$locations->count())Add an active location before recording an adjustment.@endif</small></div>
                </div>
            @endif

            <div class="form-actions">
                <a class="button button-light" href="{{ route('stock-adjustments.index') }}">Cancel</a>
                <button class="button button-primary" type="submit" {{ !$products->count() || ($requiresStore && !$locations->count()) ? 'disabled' : '' }}>Save Adjustment</button>
            </div>
        </form>
    </section>
@endsection
