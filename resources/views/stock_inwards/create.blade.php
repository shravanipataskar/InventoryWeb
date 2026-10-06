@extends('layouts.app')

@section('title', 'Add Stock Inward')
@section('topbar-title', 'Add stock inward')

@section('content')
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
                    <div class="field"><label for="product_id">Product <span class="required-mark">*</span></label><select class="field-control" id="product_id" name="product_id" required><option value="">Select product</option>@foreach ($products as $product)<option value="{{ $product->id }}" {{ old('product_id') == $product->id ? 'selected' : '' }}>{{ $product->name }} ({{ $product->product_code }})</option>@endforeach</select>@error('product_id')<small class="field-error">{{ $message }}</small>@enderror</div>
                    <div class="field"><label for="supplier_id">Supplier <span class="required-mark">*</span></label><select class="field-control" id="supplier_id" name="supplier_id" required><option value="">Select supplier</option>@foreach ($suppliers as $supplier)<option value="{{ $supplier->id }}" {{ old('supplier_id') == $supplier->id ? 'selected' : '' }}>{{ $supplier->name }}</option>@endforeach</select>@error('supplier_id')<small class="field-error">{{ $message }}</small>@enderror</div>
                </div>
            </div>
            <div class="form-section">
                <div class="form-card-heading"><span class="form-section-icon form-icon-gold"><svg><use href="#icon-arrow-down"></use></svg></span><div><h2>Quantity & pricing</h2><p>Enter the received amount and purchase price per unit.</p></div></div>
                <div class="form-grid">
                    <div class="field"><label for="quantity">Quantity <span class="required-mark">*</span></label><input class="field-control" id="quantity" type="number" name="quantity" value="{{ old('quantity') }}" min="0.01" step="0.01" placeholder="0.00" required>@error('quantity')<small class="field-error">{{ $message }}</small>@enderror</div>
                    <div class="field"><label for="purchase_price">Purchase price per unit <span class="required-mark">*</span></label><div class="input-prefix"><span>₹</span><input class="field-control" id="purchase_price" type="number" name="purchase_price" value="{{ old('purchase_price') }}" min="0" step="0.01" placeholder="0.00" required></div>@error('purchase_price')<small class="field-error">{{ $message }}</small>@enderror</div>
                    <div class="field field-wide"><label for="total_amount">Total amount</label><div class="total-preview"><span>Receipt total</span><strong>₹<output id="total_amount">0.00</output></strong></div><small class="field-hint">Calculated from quantity × purchase price. The server remains authoritative.</small></div>
                    <div class="field field-wide"><label for="remarks">Remarks</label><textarea class="field-control" id="remarks" name="remarks" rows="3" placeholder="Optional notes about this receipt">{{ old('remarks') }}</textarea>@error('remarks')<small class="field-error">{{ $message }}</small>@enderror</div>
                </div>
            </div>
            <div class="form-actions"><a class="button button-light" href="{{ route('stock-inwards.index') }}">Cancel</a><button class="button button-primary" type="submit">Save Stock Inward</button></div>
        </form>
    </section>
    <script>
        (function () {
            var quantity = document.getElementById('quantity');
            var price = document.getElementById('purchase_price');
            var total = document.getElementById('total_amount');
            function updateTotal() {
                total.value = ((parseFloat(quantity.value) || 0) * (parseFloat(price.value) || 0)).toFixed(2);
            }
            quantity.addEventListener('input', updateTotal);
            price.addEventListener('input', updateTotal);
            updateTotal();
        }());
    </script>
@endsection
