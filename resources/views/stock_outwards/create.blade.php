@extends('layouts.app')

@section('title', 'Add Stock Outward')
@section('topbar-title', 'Add stock outward')

@section('content')
    <div class="page-heading">
        <div><span class="section-kicker">STOCK MOVEMENT / OUTWARD</span><h1>Add Stock Outward</h1><p>Record inventory issued to a customer, department or team member.</p></div>
        <a class="button button-light" href="{{ route('stock-outwards.index') }}">Back to stock outward</a>
    </div>
    <div class="inventory-notice notice-out"><span class="notice-symbol">−</span><span><strong>Issuing stock decreases available inventory.</strong><small>Available stock is checked here and validated again by the server when you save.</small></span></div>
    @if ($errors->any())
        <div class="form-alert" role="alert"><strong>Please check the form:</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    <section class="form-card">
        <form action="{{ route('stock-outwards.store') }}" method="POST">
            @csrf
            <div class="form-section">
                <div class="form-card-heading"><span class="form-section-icon"><svg><use href="#icon-calendar"></use></svg></span><div><h2>Transaction information</h2><p>Set the issue date and optional reference.</p></div></div>
                <div class="form-grid">
                    <div class="field"><label for="outward_date">Outward date <span class="required-mark">*</span></label><input class="field-control" id="outward_date" type="date" name="outward_date" value="{{ old('outward_date', date('Y-m-d')) }}" required>@error('outward_date')<small class="field-error">{{ $message }}</small>@enderror</div>
                    <div class="field"><label for="reference_number">Reference number</label><input class="field-control" id="reference_number" type="text" name="reference_number" value="{{ old('reference_number') }}" placeholder="e.g. OUT-001" maxlength="100">@error('reference_number')<small class="field-error">{{ $message }}</small>@enderror</div>
                    <div class="field"><label for="issued_to">Issued to</label><input class="field-control" id="issued_to" type="text" name="issued_to" value="{{ old('issued_to') }}" placeholder="Customer, department or employee" maxlength="255">@error('issued_to')<small class="field-error">{{ $message }}</small>@enderror</div>
                </div>
            </div>
            <div class="form-section">
                <div class="form-card-heading"><span class="form-section-icon form-icon-violet"><svg><use href="#icon-box"></use></svg></span><div><h2>Product & quantity</h2><p>Choose a product with available stock and enter the quantity to issue.</p></div></div>
                <div class="form-grid">
                    <div class="field"><label for="product_id">Product <span class="required-mark">*</span></label><select class="field-control" name="product_id" id="product_id" required><option value="">Select product</option>@foreach ($products as $product)<option value="{{ $product->id }}" data-stock="{{ $product->current_stock }}" data-price="{{ $product->selling_price }}" {{ old('product_id') == $product->id ? 'selected' : '' }}>{{ $product->name }} ({{ $product->product_code }})</option>@endforeach</select>@error('product_id')<small class="field-error">{{ $message }}</small>@enderror</div>
                    <div class="field"><label for="available_stock">Available stock</label><div class="stock-availability"><input class="field-control" id="available_stock" type="text" value="0" readonly><span>units available</span></div><small id="stock_warning" class="field-error" role="alert" hidden>Quantity cannot exceed available stock.</small></div>
                    <div class="field"><label for="quantity">Quantity <span class="required-mark">*</span></label><input class="field-control" id="quantity" type="number" name="quantity" value="{{ old('quantity') }}" min="0.01" step="0.01" placeholder="0.00" required>@error('quantity')<small class="field-error">{{ $message }}</small>@enderror</div>
                    <div class="field"><label for="selling_price">Selling price per unit <span class="required-mark">*</span></label><div class="input-prefix"><span>₹</span><input class="field-control" id="selling_price" type="number" name="selling_price" value="{{ old('selling_price') }}" min="0" step="0.01" placeholder="0.00" required></div>@error('selling_price')<small class="field-error">{{ $message }}</small>@enderror</div>
                    <div class="field field-wide"><label for="total_amount">Total amount</label><div class="total-preview total-preview-out"><span>Issue total</span><strong>₹<output id="total_amount">0.00</output></strong></div><small class="field-hint">Calculated from quantity × selling price. The server remains authoritative.</small></div>
                    <div class="field field-wide"><label for="remarks">Remarks</label><textarea class="field-control" id="remarks" name="remarks" rows="3" placeholder="Optional notes about this issue">{{ old('remarks') }}</textarea>@error('remarks')<small class="field-error">{{ $message }}</small>@enderror</div>
                </div>
            </div>
            <div class="form-actions"><a class="button button-light" href="{{ route('stock-outwards.index') }}">Cancel</a><button class="button button-primary" id="saveButton" type="submit">Save Stock Outward</button></div>
        </form>
    </section>
    <script>
        (function () {
            var productSelect = document.getElementById('product_id');
            var stockField = document.getElementById('available_stock');
            var priceField = document.getElementById('selling_price');
            var quantityField = document.getElementById('quantity');
            var totalField = document.getElementById('total_amount');
            var warning = document.getElementById('stock_warning');
            var saveButton = document.getElementById('saveButton');

            function calculateTotal() {
                totalField.value = ((parseFloat(quantityField.value) || 0) * (parseFloat(priceField.value) || 0)).toFixed(2);
            }

            function validateQuantity() {
                var exceedsStock = (parseFloat(quantityField.value) || 0) > (parseFloat(stockField.value) || 0);
                warning.hidden = !exceedsStock;
                saveButton.disabled = exceedsStock;
            }

            function updateProductDetails() {
                var option = productSelect.options[productSelect.selectedIndex];
                if (!option || !option.value) {
                    stockField.value = '0';
                    validateQuantity();
                    calculateTotal();
                    return;
                }
                stockField.value = option.dataset.stock || '0';
                priceField.value = (parseFloat(option.dataset.price) || 0).toFixed(2);
                calculateTotal();
                validateQuantity();
            }

            productSelect.addEventListener('change', updateProductDetails);
            quantityField.addEventListener('input', function () {
                calculateTotal();
                validateQuantity();
            });
            priceField.addEventListener('input', calculateTotal);
            updateProductDetails();
        }());
    </script>
@endsection
