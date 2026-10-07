@extends('layouts.app')

@section('title', 'Record Stock Transfer')
@section('topbar-title', 'Record stock transfer')

@section('content')
    <div class="page-heading workspace-page-heading">
        <div>
            <span class="section-kicker">INVENTORY / STOCK TRANSFER</span>
            <h1>Record Stock Transfer</h1>
            <p>Record a movement of existing stock from one location to another.</p>
        </div>
        <a class="button button-light" href="{{ route('stock-transfers.index') }}">Back to Stock Transfers</a>
    </div>

    @if ($errors->any())
        <div class="form-alert" role="alert">
            <strong>Please check the form:</strong>
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <section class="form-card">
        <form action="{{ route('stock-transfers.store') }}" method="POST">
            @csrf
            <div class="form-section">
                <div class="form-card-heading">
                    <span class="form-section-icon"><svg><use href="#icon-transfer"></use></svg></span>
                    <div><h2>Transfer details</h2><p>A transfer changes the stock location, not the total on-hand quantity.</p></div>
                </div>

                <div class="form-grid">
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

                    <div class="field">
                        <label for="from_location">From location <span class="required-mark">*</span></label>
                        <select class="field-control" id="from_location" name="from_location" required>
                            <option value="">Select a location</option>
                            @foreach ($locations as $location)
                                <option value="{{ $location->name }}" {{ old('from_location') === $location->name ? 'selected' : '' }}>{{ $location->name }}</option>
                            @endforeach
                        </select>
                        @error('from_location')<small class="field-error">{{ $message }}</small>@enderror
                    </div>

                    <div class="field">
                        <label for="to_location">To location <span class="required-mark">*</span></label>
                        <select class="field-control" id="to_location" name="to_location" required>
                            <option value="">Select a location</option>
                            @foreach ($locations as $location)
                                <option value="{{ $location->name }}" {{ old('to_location') === $location->name ? 'selected' : '' }}>{{ $location->name }}</option>
                            @endforeach
                        </select>
                        @error('to_location')<small class="field-error">{{ $message }}</small>@enderror
                    </div>

                    <div class="field">
                        <label for="quantity">Quantity <span class="required-mark">*</span></label>
                        <input class="field-control" id="quantity" type="number" name="quantity" value="{{ old('quantity') }}" min="0.01" step="0.01" required>
                        @error('quantity')<small class="field-error">{{ $message }}</small>@enderror
                    </div>

                    <div class="field">
                        <label for="transfer_date">Transfer date <span class="required-mark">*</span></label>
                        <input class="field-control" id="transfer_date" type="date" name="transfer_date" value="{{ old('transfer_date', now()->toDateString()) }}" required>
                        @error('transfer_date')<small class="field-error">{{ $message }}</small>@enderror
                    </div>

                    <div class="field field-wide">
                        <label for="remarks">Notes</label>
                        <textarea class="field-control" id="remarks" name="remarks" rows="3" maxlength="2000">{{ old('remarks') }}</textarea>
                        @error('remarks')<small class="field-error">{{ $message }}</small>@enderror
                    </div>
                </div>
            </div>

            @if (!$products->count() || $locations->count() < 2)
                <div class="inventory-notice notice-out">
                    <span class="notice-symbol" aria-hidden="true">!</span>
                    <div>
                        <strong>Setup required</strong>
                        <small>
                            @if (!$products->count() && $locations->count() < 2)
                                Add an active product and two active locations before recording a transfer.
                            @elseif (!$products->count())
                                Add an active product with stock before recording a transfer.
                            @else
                                Add at least two active locations before recording a transfer.
                            @endif
                        </small>
                    </div>
                </div>
            @endif

            <div class="form-actions">
                <a class="button button-light" href="{{ route('stock-transfers.index') }}">Cancel</a>
                <button class="button button-primary" type="submit" {{ !$products->count() || $locations->count() < 2 ? 'disabled' : '' }}>Save Transfer</button>
            </div>
        </form>
    </section>
@endsection
