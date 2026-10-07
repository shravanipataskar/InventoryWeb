@extends('layouts.app')

@section('title', 'Add Opening Stock')
@section('topbar-title', 'Add opening stock')

@section('content')
    <div class="page-heading">
        <div>
            <span class="section-kicker">INVENTORY / OPENING STOCK</span>
            <h1>Add Opening Stock</h1>
            <p>Set an initial inventory quantity for a product at a location.</p>
        </div>
        <a class="button button-light" href="{{ route('opening-stock.index') }}">Back to Opening Stock</a>
    </div>

    @if ($errors->any())
        <div class="form-alert" role="alert">
            <strong>Please check the form:</strong>
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <section class="form-card">
        <form action="{{ route('opening-stock.store') }}" method="POST">
            @csrf
            <div class="form-section">
                <div class="form-card-heading">
                    <span class="form-section-icon"><svg><use href="#icon-tray-in"></use></svg></span>
                    <div><h2>Opening balance</h2><p>The entered quantity will be added to on-hand inventory and recorded in the stock ledger.</p></div>
                </div>

                <div class="form-grid">
                    <div class="field">
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
                        <label for="store_id">Location <span class="required-mark">*</span></label>
                        <select class="field-control" id="store_id" name="store_id" required>
                            <option value="">Select a location</option>
                            @foreach ($locations as $location)
                                <option value="{{ $location->id }}" {{ old('store_id') == $location->id ? 'selected' : '' }}>
                                    {{ $location->name }} ({{ $location->code }})
                                </option>
                            @endforeach
                        </select>
                        @error('store_id')<small class="field-error">{{ $message }}</small>@enderror
                    </div>

                    <div class="field">
                        <label for="quantity">Opening quantity <span class="required-mark">*</span></label>
                        <input class="field-control" id="quantity" type="number" name="quantity" value="{{ old('quantity') }}" min="0.01" step="0.01" required>
                        @error('quantity')<small class="field-error">{{ $message }}</small>@enderror
                    </div>

                    <div class="field">
                        <label for="transaction_date">Opening date <span class="required-mark">*</span></label>
                        <input class="field-control" id="transaction_date" type="date" name="transaction_date" value="{{ old('transaction_date', now()->toDateString()) }}" required>
                        @error('transaction_date')<small class="field-error">{{ $message }}</small>@enderror
                    </div>

                    <div class="field field-wide">
                        <label for="remarks">Notes</label>
                        <textarea class="field-control" id="remarks" name="remarks" rows="3" maxlength="2000" placeholder="Optional notes about this opening balance">{{ old('remarks') }}</textarea>
                        @error('remarks')<small class="field-error">{{ $message }}</small>@enderror
                    </div>
                </div>
            </div>

            @if (!$products->count() || !$locations->count())
                <div class="inventory-notice notice-out">
                    <span class="notice-symbol" aria-hidden="true">!</span>
                    <div>
                        <strong>Setup required</strong>
                        <small>
                            @if (!$products->count() && !$locations->count())
                                Add an active product and an active location before recording opening stock.
                            @elseif (!$products->count())
                                Add an active product before recording opening stock.
                            @else
                                Add an active location before recording opening stock.
                            @endif
                        </small>
                    </div>
                </div>
            @endif

            <div class="form-actions">
                <a class="button button-light" href="{{ route('opening-stock.index') }}">Cancel</a>
                <button class="button button-primary" type="submit" {{ !$products->count() || !$locations->count() ? 'disabled' : '' }}>Save Opening Stock</button>
            </div>
        </form>
    </section>
@endsection
