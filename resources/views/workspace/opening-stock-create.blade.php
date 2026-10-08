@extends('layouts.app')

@section('title', 'Add Opening Stock')
@section('topbar-title', 'Opening stock setup')

@section('content')
    <div class="page-heading">
        <div>
            <span class="section-kicker">INVENTORY / OPENING STOCK</span>
            <h1>Opening Stock Setup</h1>
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
                    <div><h2>Opening inventory</h2><p>Opening stock is recorded once for a product and location; use stock adjustments for later corrections.</p></div>
                </div>

                <div class="form-grid">
                    <div class="field">
                        <label for="category_id">Category <span class="required-mark">*</span></label>
                        <select class="field-control" id="category_id" name="category_id" required>
                            <option value="">Select a category</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}" {{ old('category_id') == $category->id ? 'selected' : '' }}>
                                    {{ $category->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('category_id')<small class="field-error">{{ $message }}</small>@enderror
                    </div>

                    <div class="field">
                        <label for="product_id">Product <span class="required-mark">*</span></label>
                        <select class="field-control" id="product_id" name="product_id" required disabled>
                            <option value="">Select a product</option>
                            @foreach ($products as $product)
                                <option value="{{ $product->id }}" data-category-id="{{ $product->category_id }}" data-purchase-price="{{ number_format((float) ($product->purchase_price ?? 0), 2, '.', '') }}" data-unit="{{ optional($product->unit)->short_name ?? '' }}" data-current-stock="{{ number_format((float) $product->current_stock, 2, '.', '') }}" {{ old('product_id') == $product->id ? 'selected' : '' }}>
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
                        <label for="quantity">Opening quantity <span class="required-mark">*</span></label>
                        <input class="field-control" id="quantity" type="number" name="quantity" value="{{ old('quantity') }}" min="0.01" step="0.01" required>
                        @error('quantity')<small class="field-error">{{ $message }}</small>@enderror
                    </div>

                    <div class="field">
                        <label for="unit_purchase_rate">Unit purchase rate <span class="required-mark">*</span></label>
                        <input class="field-control" id="unit_purchase_rate" type="number" name="unit_purchase_rate" value="{{ old('unit_purchase_rate') }}" min="0" step="0.01" required>
                        @error('unit_purchase_rate')<small class="field-error">{{ $message }}</small>@enderror
                    </div>

                    <div class="field">
                        <label for="opening_value">Opening value</label>
                        <input class="field-control" id="opening_value" type="text" value="₹0.00" readonly aria-describedby="opening-value-help">
                        <small id="opening-value-help">Calculated as opening quantity × unit purchase rate.</small>
                    </div>

                    <div class="field">
                        <label for="transaction_date">Opening date <span class="required-mark">*</span></label>
                        <input class="field-control" id="transaction_date" type="date" name="transaction_date" value="{{ old('transaction_date', now()->toDateString()) }}" required>
                        @error('transaction_date')<small class="field-error">{{ $message }}</small>@enderror
                    </div>

                    <div class="field field-wide">
                        <label for="remarks">Remarks</label>
                        <textarea class="field-control" id="remarks" name="remarks" rows="3" maxlength="2000" placeholder="Optional notes about this opening stock setup">{{ old('remarks') }}</textarea>
                        @error('remarks')<small class="field-error">{{ $message }}</small>@enderror
                    </div>
                </div>
                <p class="form-help">A reference number is generated automatically. The ledger also records who created this entry and when.</p>
            </div>

            @if (!$products->count())
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
                        <small>Add an active product before recording initial stock.</small>
                    </div>
                </div>
            @endif

            <div class="form-actions">
                <a class="button button-light" href="{{ route('opening-stock.index') }}">Cancel</a>
                <button class="button button-primary" type="submit" {{ !$products->count() || !$locations->count() ? 'disabled' : '' }}>Save Opening Stock</button>
                <button class="button button-primary" type="submit" {{ !$products->count() ? 'disabled' : '' }}>Save Initial Stock</button>
            </div>
        </form>
    </section>

    <script>
        (function () {
            var categorySelect = document.getElementById('category_id');
            var productSelect = document.getElementById('product_id');
            var quantity = document.getElementById('quantity');
            var rate = document.getElementById('unit_purchase_rate');
            var openingValue = document.getElementById('opening_value');
            var allProductOptions = Array.from(productSelect.querySelectorAll('option[data-category-id]'));
            var currency = new Intl.NumberFormat('en-IN', {
                style: 'currency',
                currency: 'INR',
                minimumFractionDigits: 2
            });

            function populateProductOptions(categoryId, selectedProductId) {
                productSelect.innerHTML = '<option value="">Select a product</option>';
                if (!categoryId) {
                    productSelect.disabled = true;
                    productSelect.value = '';
                    return;
                }

                allProductOptions.forEach(function (option) {
                    if (option.dataset.categoryId === categoryId) {
                        productSelect.appendChild(option.cloneNode(true));
                    }
                });

                productSelect.disabled = false;
                if (selectedProductId) {
                    productSelect.value = selectedProductId;
                }
            }

            function updateOpeningValue() {
                var quantityValue = parseFloat(quantity.value) || 0;
                var rateValue = parseFloat(rate.value) || 0;
                openingValue.value = currency.format(quantityValue * rateValue);
            }

            function updateSelectedProductMeta() {
                var selected = productSelect.options[productSelect.selectedIndex];
                if (!selected || !selected.dataset.purchasePrice) {
                    return;
                }

                var purchasePrice = parseFloat(selected.dataset.purchasePrice) || 0;
                if (purchasePrice > 0) {
                    rate.value = purchasePrice.toFixed(2);
                }
            }

            quantity.addEventListener('input', updateOpeningValue);
            rate.addEventListener('input', updateOpeningValue);
            categorySelect.addEventListener('change', function () {
                productSelect.dataset.selectedProduct = '';
                populateProductOptions(categorySelect.value, '');
            });
            productSelect.addEventListener('change', function () {
                productSelect.dataset.selectedProduct = productSelect.value || '';
                updateSelectedProductMeta();
            });

            populateProductOptions(categorySelect.value || '', '{{ old('product_id') }}');
            updateOpeningValue();
            updateSelectedProductMeta();
        })();
    </script>
@endsection
