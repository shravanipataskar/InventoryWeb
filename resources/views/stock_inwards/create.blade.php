@extends('layouts.app')

@section('title', 'Add Stock Inward')
@section('topbar-title', 'Add stock inward')

@section('content')
    <style>
        .calculated-control { background-color: #f5f7fa; font-weight: 600; }
    </style>
    <div class="page-heading">
        <div><span class="section-kicker">STOCK MOVEMENT / INWARD</span><h1>Add Stock Inward</h1><p>Record goods received from a supplier.</p></div>
        <a class="button button-light" href="{{ route('stock-inwards.index') }}">Back to stock inward</a>
    </div>
    <div class="inventory-notice notice-in"><span class="notice-symbol">+</span><span><strong>Receiving stock increases available inventory.</strong><small>Saving this receipt adds the entered quantity to the product's current stock.</small></span></div>
    @if ($errors->any())
        <div class="form-alert" role="alert"><strong>Please check the form:</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    <section class="form-card">
        <form action="{{ route('stock-inwards.store') }}" method="POST" data-inward-form>
            @csrf
            <div class="form-section">
                <div class="form-card-heading"><span class="form-section-icon"><svg><use href="#icon-calendar"></use></svg></span><div><h2>Transaction information</h2><p>Set the receiving date and supplier invoice reference.</p></div></div>
                <div class="form-grid">
                    <div class="field"><label for="inward_date">Inward date <span class="required-mark">*</span></label><input class="field-control" id="inward_date" type="date" name="inward_date" value="{{ old('inward_date', date('Y-m-d')) }}" required>@error('inward_date')<small class="field-error">{{ $message }}</small>@enderror</div>
                    <div class="field"><label for="invoice_number">Invoice number</label><input class="field-control" id="invoice_number" type="text" name="invoice_number" value="{{ old('invoice_number') }}" placeholder="e.g. INV-001" maxlength="100">@error('invoice_number')<small class="field-error">{{ $message }}</small>@enderror</div>
                </div>
            </div>
            <div class="form-section">
                <div class="form-card-heading"><span class="form-section-icon form-icon-violet"><svg><use href="#icon-box"></use></svg></span><div><h2>Product & supplier</h2><p>Choose the stock item and the supplier it came from.</p></div></div>
                <div class="form-grid">
                    <div class="field"><label for="category_id">Category <span class="required-mark">*</span></label><select class="field-control" id="category_id" name="category_id" required><option value="">Select category</option>@foreach ($categories as $category)<option value="{{ $category->id }}" {{ old('category_id') == $category->id ? 'selected' : '' }}>{{ $category->name }}</option>@endforeach</select>@error('category_id')<small class="field-error">{{ $message }}</small>@enderror</div>
                    <div class="field"><label for="product_id">Product <span class="required-mark">*</span></label><select class="field-control" id="product_id" name="product_id" required disabled><option value="">Select a category first</option></select>@error('product_id')<small class="field-error">{{ $message }}</small>@enderror</div>
                    <div class="field"><label for="supplier_id">Supplier <span class="required-mark">*</span></label><select class="field-control" id="supplier_id" name="supplier_id" required><option value="">Select supplier</option>@foreach ($suppliers as $supplier)<option value="{{ $supplier->id }}" {{ old('supplier_id') == $supplier->id ? 'selected' : '' }}>{{ $supplier->name }}</option>@endforeach</select>@error('supplier_id')<small class="field-error">{{ $message }}</small>@enderror</div>
                </div>
            </div>
            <div class="form-section">
                <div class="form-card-heading"><span class="form-section-icon form-icon-gold"><svg><use href="#icon-arrow-down"></use></svg></span><div><h2>Quantity & pricing</h2><p>Enter the received amount and purchase price per unit.</p></div></div>
                <div class="form-grid">
                    <div class="field"><label for="quantity">Quantity <span class="required-mark">*</span></label><input class="field-control" id="quantity" type="number" name="quantity" value="{{ old('quantity') }}" min="0.01" step="0.01" placeholder="0.00" required>@error('quantity')<small class="field-error">{{ $message }}</small>@enderror</div>
                    <div class="field"><label for="purchase_price">Purchase price per unit <span class="required-mark">*</span></label><div class="input-prefix"><span>₹</span><input class="field-control" id="purchase_price" type="number" name="purchase_price" value="{{ old('purchase_price') }}" min="0" step="0.01" placeholder="0.00" required></div>@error('purchase_price')<small class="field-error">{{ $message }}</small>@enderror</div>
                    <div class="field"><label for="sgst_rate">SGST (%) <span class="required-mark">*</span></label><input class="field-control" id="sgst_rate" type="number" name="sgst_rate" value="{{ old('sgst_rate') }}" min="0" max="100" step="0.01" placeholder="0.00" required>@error('sgst_rate')<small class="field-error">{{ $message }}</small>@enderror</div>
                    <div class="field"><label for="cgst_rate">CGST (%) <span class="required-mark">*</span></label><input class="field-control" id="cgst_rate" type="number" name="cgst_rate" value="{{ old('cgst_rate') }}" min="0" max="100" step="0.01" placeholder="0.00" required>@error('cgst_rate')<small class="field-error">{{ $message }}</small>@enderror</div>
                    <div class="field"><label for="subtotal">Subtotal</label><div class="input-prefix"><span>₹</span><input class="field-control calculated-control" id="subtotal" type="text" value="0.00" readonly></div></div>
                    <div class="field"><label for="sgst_amount">SGST Amount</label><div class="input-prefix"><span>₹</span><input class="field-control calculated-control" id="sgst_amount" type="text" value="0.00" readonly></div></div>
                    <div class="field"><label for="cgst_amount">CGST Amount</label><div class="input-prefix"><span>₹</span><input class="field-control calculated-control" id="cgst_amount" type="text" value="0.00" readonly></div></div>
                    <div class="field"><label for="tax_total">Tax Total</label><div class="input-prefix"><span>₹</span><input class="field-control calculated-control" id="tax_total" type="text" value="0.00" readonly></div></div>
                    <div class="field"><label for="grand_total">Grand Total</label><div class="input-prefix"><span>₹</span><input class="field-control calculated-control" id="grand_total" type="text" value="0.00" readonly></div></div>
                    <div class="field field-wide"><small class="field-hint">Calculated automatically from quantity, purchase price, SGST and CGST. The server recalculates all amounts when you save.</small></div>
                    <div class="field field-wide"><label for="remarks">Remarks</label><textarea class="field-control" id="remarks" name="remarks" rows="3" placeholder="Optional notes about this receipt">{{ old('remarks') }}</textarea>@error('remarks')<small class="field-error">{{ $message }}</small>@enderror</div>
                </div>
            </div>
            <div class="form-actions"><a class="button button-light" href="{{ route('stock-inwards.index') }}">Cancel</a><button class="button button-primary" type="submit">Save Stock Inward</button></div>
        </form>
    </section>
    <script>
        (function () {
            var category = document.getElementById('category_id');
            var product = document.getElementById('product_id');
            var quantity = document.getElementById('quantity');
            var price = document.getElementById('purchase_price');
            var sgstRate = document.getElementById('sgst_rate');
            var cgstRate = document.getElementById('cgst_rate');
            var products = @json($productOptions, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);

            function filterProducts(keepSelection) {
                var selectedProductId = keepSelection ? product.value : '';
                product.innerHTML = '';
                product.add(new Option(category.value ? 'Select product' : 'Select a category first', ''));
                product.disabled = !category.value;

                products.forEach(function (item) {
                    if (item.category_id === category.value) {
                        product.add(new Option(item.label, item.id));
                    }
                });

                if (keepSelection && products.some(function (item) {
                    return item.id === selectedProductId && item.category_id === category.value;
                })) {
                    product.value = selectedProductId;
                }
            }

            function roundAmount(amount) {
                return Math.round((amount + Number.EPSILON) * 100) / 100;
            }

            function updateTotals() {
                var quantityValue = roundAmount(parseFloat(quantity.value) || 0);
                var priceValue = roundAmount(parseFloat(price.value) || 0);
                var sgstRateValue = roundAmount(parseFloat(sgstRate.value) || 0);
                var cgstRateValue = roundAmount(parseFloat(cgstRate.value) || 0);
                var subtotal = roundAmount(quantityValue * priceValue);
                var sgstAmount = roundAmount(subtotal * sgstRateValue / 100);
                var cgstAmount = roundAmount(subtotal * cgstRateValue / 100);
                var taxTotal = roundAmount(sgstAmount + cgstAmount);

                document.getElementById('subtotal').value = subtotal.toFixed(2);
                document.getElementById('sgst_amount').value = sgstAmount.toFixed(2);
                document.getElementById('cgst_amount').value = cgstAmount.toFixed(2);
                document.getElementById('tax_total').value = taxTotal.toFixed(2);
                document.getElementById('grand_total').value = roundAmount(subtotal + taxTotal).toFixed(2);
            }

            category.addEventListener('change', function () {
                filterProducts(false);
            });
            [quantity, price, sgstRate, cgstRate].forEach(function (input) {
                input.addEventListener('input', updateTotals);
            });

            filterProducts(true);
            updateTotals();
        }());
    </script>
@endsection
