@extends('layouts.app')

@section('title', 'Add Initial Stock')
@section('topbar-title', 'Initial stock setup')

@section('content')
    <div class="page-heading">
        <div>
            <span class="section-kicker">INVENTORY / INITIAL STOCK</span>
            <h1>Initial Stock Setup</h1>
            <p>Set the starting inventory quantity once for a product at a location. Daily opening balances are derived from the previous day’s closing stock.</p>
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
                    <div><h2>Initial inventory</h2><p>The entered quantity is treated as the initial on-hand balance only once and is recorded in the stock ledger.</p></div>
                </div>

                <div class="form-grid">
                    <div class="field">
                        <label for="product_id">Product <span class="required-mark">*</span></label>
                        <select class="field-control" id="product_id" name="product_id" required>
                            <option value="">Select a product</option>
                            @foreach ($products as $product)
                                <option value="{{ $product->id }}" {{ old('product_id') == $product->id ? 'selected' : '' }}>
                                    {{ $product->name }} ({{ $product->product_code }}) — Current on hand: {{ number_format($product->current_stock, 2) }} {{ optional($product->unit)->short_name }}
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
                        <label for="quantity">Initial quantity <span class="required-mark">*</span></label>
                        <input class="field-control" id="quantity" type="number" name="quantity" value="{{ old('quantity') }}" min="0.01" step="0.01" required>
                        @error('quantity')<small class="field-error">{{ $message }}</small>@enderror
                    </div>

                    <div class="field">
                        <label for="transaction_date">Initial date <span class="required-mark">*</span></label>
                        <input class="field-control" id="transaction_date" type="date" name="transaction_date" value="{{ old('transaction_date', now()->toDateString()) }}" required>
                        @error('transaction_date')<small class="field-error">{{ $message }}</small>@enderror
                    </div>

                    <div class="field field-wide">
                        <label for="remarks">Notes</label>
                        <textarea class="field-control" id="remarks" name="remarks" rows="3" maxlength="2000" placeholder="Optional notes about this initial stock setup">{{ old('remarks') }}</textarea>
                        @error('remarks')<small class="field-error">{{ $message }}</small>@enderror
                    </div>
                </div>
            </div>

            @if (!$products->count())
                <div class="inventory-notice notice-out">
                    <span class="notice-symbol" aria-hidden="true">!</span>
                    <div>
                        <strong>Setup required</strong>
                        <small>Add an active product before recording initial stock.</small>
                    </div>
                </div>
            @endif

            <div class="form-actions">
                <a class="button button-light" href="{{ route('opening-stock.index') }}">Cancel</a>
                <button class="button button-primary" type="submit" {{ !$products->count() ? 'disabled' : '' }}>Save Initial Stock</button>
            </div>
        </form>
    </section>
@endsection
