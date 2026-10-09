@extends('layouts.app')

@section('title', 'Create Quotation Request')
@section('topbar-title', 'Create quotation request')

@section('content')
    <div class="page-heading"><div><span class="section-kicker">PURCHASING / QUOTATIONS</span><h1>Create Quotation Request</h1><p>Select required products and multiple active suppliers.</p></div><a class="button button-light" href="{{ route('quotations.index') }}">Back to quotations</a></div>
    @if ($errors->any())<div class="form-alert" role="alert"><strong>Please check the form:</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <section class="form-card"><form method="POST" action="{{ route('quotations.store') }}">@csrf
        <div class="form-grid">
            <div class="field"><label for="request_date">Request Date *</label><input class="field-control" type="date" id="request_date" name="request_date" value="{{ old('request_date', now()->toDateString()) }}" required></div>
            <div class="field"><label for="required_date">Required Date</label><input class="field-control" type="date" id="required_date" name="required_date" value="{{ old('required_date') }}"></div>
            <div class="field"><label for="store_id">Receive Into Store / Warehouse *</label><select class="field-control" name="store_id" required><option value="">Select store</option>@foreach ($stores as $store)<option value="{{ $store->id }}" {{ old('store_id') == $store->id ? 'selected' : '' }}>{{ $store->name }}</option>@endforeach</select></div>
            <div class="field field-wide"><label for="remarks">Remarks</label><textarea class="field-control" name="remarks" rows="2">{{ old('remarks') }}</textarea></div>
        </div>
        <div class="form-section"><div class="form-card-heading"><div><h2>Required products</h2><p>Products are restricted to their selected category.</p></div></div>
            <div id="quotation-items">
                <div class="form-grid quotation-item-row">
                    <div class="field"><label>Category *</label><select class="field-control category-select" name="items[0][category_id]" required><option value="">Select category</option>@foreach ($categories as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach</select></div>
                    <div class="field"><label>Product *</label><select class="field-control product-select" name="items[0][product_id]" required><option value="">Select category first</option>@foreach ($products as $product)<option value="{{ $product->id }}" data-category="{{ $product->category_id }}">{{ $product->name }} ({{ $product->product_code }})</option>@endforeach</select></div>
                    <div class="field"><label>Quantity *</label><input class="field-control" type="number" step="0.01" min="0.01" name="items[0][quantity]" required></div>
                </div>
            </div>
            <button class="button button-light" type="button" id="add-quotation-item">+ Add product</button>
        </div>
        <div class="form-section"><div class="form-card-heading"><div><h2>Suppliers *</h2><p>Select all suppliers who should submit a quotation.</p></div></div><div class="form-grid">@foreach ($suppliers as $supplier)<label class="field"><span><input type="checkbox" name="supplier_ids[]" value="{{ $supplier->id }}" {{ in_array($supplier->id, old('supplier_ids', [])) ? 'checked' : '' }}> {{ $supplier->company_name ?: $supplier->name }}</span><small>{{ $supplier->supplier_code }} · {{ $supplier->phone ?: 'No phone' }}</small></label>@endforeach</div></div>
        <div class="form-actions"><a class="button button-light" href="{{ route('quotations.index') }}">Cancel</a><button class="button button-primary" type="submit">Continue to Supplier Quotations</button></div>
    </form></section>
    <script>
        (function () {
            var items = document.getElementById('quotation-items'), index = 1;
            function filterProducts(row) {
                var category = row.querySelector('.category-select').value;
                Array.prototype.forEach.call(row.querySelectorAll('.product-select option[data-category]'), function (option) {
                    option.hidden = category && option.getAttribute('data-category') !== category;
                    if (option.hidden && option.selected) option.selected = false;
                });
            }
            items.addEventListener('change', function (event) { if (event.target.classList.contains('category-select')) filterProducts(event.target.closest('.quotation-item-row')); });
            document.getElementById('add-quotation-item').addEventListener('click', function () {
                var row = items.querySelector('.quotation-item-row').cloneNode(true);
                row.querySelectorAll('input, select').forEach(function (control) { control.name = control.name.replace(/\[\d+\]/, '[' + index + ']'); control.value = ''; });
                row.querySelectorAll('.product-select option').forEach(function (option) { option.hidden = false; });
                items.appendChild(row); index++;
            });
        }());
    </script>
@endsection
