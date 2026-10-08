@extends('layouts.app')

@section('title', 'New Purchase Order')
@section('topbar-title', 'New purchase order')

@section('content')
    <div class="page-heading">
        <div><span class="section-kicker">PURCHASING</span><h1>New Purchase Order</h1><p>Record the products and quantities expected from your supplier.</p></div>
        <a class="button button-light" href="{{ route('purchase-orders.index') }}">Back to purchase orders</a>
    </div>
    @if ($errors->any())
        <div class="form-alert" role="alert"><strong>Please check the form:</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    <section class="form-card">
        <form action="{{ route('purchase-orders.store') }}" method="POST">
            @csrf
            <div class="form-section">
                <div class="form-grid">
                    <div class="field"><label for="supplier_id">Supplier <span class="required-mark">*</span></label><select class="field-control" id="supplier_id" name="supplier_id" required><option value="">Select supplier</option>@foreach ($suppliers as $supplier)<option value="{{ $supplier->id }}" {{ old('supplier_id') == $supplier->id ? 'selected' : '' }}>{{ $supplier->name }}</option>@endforeach</select></div>
                    <div class="field"><label for="store_id">Order location <span class="required-mark">*</span></label><select class="field-control" id="store_id" name="store_id" required><option value="">Select location</option>@foreach ($stores as $store)<option value="{{ $store->id }}" {{ old('store_id', $prefillStoreId) == $store->id ? 'selected' : '' }}>{{ $store->name }}</option>@endforeach</select></div>
                    <div class="field"><label for="order_date">Order date <span class="required-mark">*</span></label><input class="field-control" id="order_date" name="order_date" type="date" value="{{ old('order_date', date('Y-m-d')) }}" required></div>
                    <div class="field"><label for="expected_date">Expected date</label><input class="field-control" id="expected_date" name="expected_date" type="date" value="{{ old('expected_date') }}"></div>
                </div>
            </div>
            <div class="form-section">
                <div class="form-card-heading"><span class="form-section-icon form-icon-violet"><svg><use href="#icon-box"></use></svg></span><div><h2>Products</h2><p>Enter the ordered quantity and purchase rate for each product.</p></div></div>
                <div id="purchase-order-lines">
                    @foreach (old('items', $prefillItems ?: [['product_id' => '', 'ordered_quantity' => '', 'purchase_rate' => '']]) as $index => $oldItem)
                        @php
                            $selectedProduct = $products->firstWhere('id', $oldItem['product_id'] ?? null);
                            $selectedCategoryId = $oldItem['category_id'] ?? optional($selectedProduct)->category_id;
                        @endphp
                        <div class="purchase-order-line">
                            <div class="field"><label>Category</label><select class="field-control purchase-order-category"><option value="">All categories</option>@foreach ($categories as $category)<option value="{{ $category->id }}" {{ $selectedCategoryId == $category->id ? 'selected' : '' }}>{{ $category->name }}</option>@endforeach</select></div>
                            <div class="field"><label>Product <span class="required-mark">*</span></label><select class="field-control purchase-order-product" name="items[{{ $index }}][product_id]" required><option value="">Select product</option>@foreach ($products as $product)<option value="{{ $product->id }}" data-category-id="{{ $product->category_id }}" {{ ($oldItem['product_id'] ?? '') == $product->id ? 'selected' : '' }}>{{ $product->name }} ({{ $product->product_code }})</option>@endforeach</select></div>
                            <div class="field"><label>Ordered quantity <span class="required-mark">*</span></label><input class="field-control" type="number" name="items[{{ $index }}][ordered_quantity]" min="0.01" step="0.01" value="{{ $oldItem['ordered_quantity'] ?? '' }}" required></div>
                            <div class="field"><label>Unit purchase rate (₹) <span class="required-mark">*</span></label><input class="field-control" type="number" name="items[{{ $index }}][purchase_rate]" min="0" step="0.01" value="{{ $oldItem['purchase_rate'] ?? '' }}" required></div>
                            <button class="button button-danger button-small purchase-order-line-remove" type="button" aria-label="Delete this product">Delete</button>
                        </div>
                    @endforeach
                </div>
                <template id="purchase-order-line-template">
                    <div class="purchase-order-line">
                        <div class="field"><label>Category</label><select class="field-control purchase-order-category"><option value="">All categories</option>@foreach ($categories as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach</select></div>
                        <div class="field"><label>Product <span class="required-mark">*</span></label><select class="field-control purchase-order-product" name="items[__INDEX__][product_id]" required><option value="">Select product</option>@foreach ($products as $product)<option value="{{ $product->id }}" data-category-id="{{ $product->category_id }}">{{ $product->name }} ({{ $product->product_code }})</option>@endforeach</select></div>
                        <div class="field"><label>Ordered quantity <span class="required-mark">*</span></label><input class="field-control" type="number" name="items[__INDEX__][ordered_quantity]" min="0.01" step="0.01" required></div>
                        <div class="field"><label>Unit purchase rate (₹) <span class="required-mark">*</span></label><input class="field-control" type="number" name="items[__INDEX__][purchase_rate]" min="0" step="0.01" required></div>
                        <button class="button button-danger button-small purchase-order-line-remove" type="button" aria-label="Delete this product">Delete</button>
                    </div>
                </template>
                <button class="button button-light purchase-order-add" id="add-purchase-order-line" type="button"><span aria-hidden="true">+</span> Add product</button>
            </div>
            <div class="form-actions"><a class="button button-light" href="{{ route('purchase-orders.index') }}">Cancel</a><button class="button button-primary" type="submit" {{ !$stores->count() ? 'disabled' : '' }}>Create Purchase Order</button></div>
        </form>
    </section>
    <script>
        (function () {
            var lines = document.getElementById('purchase-order-lines');
            var template = document.getElementById('purchase-order-line-template');
            var productOptions = Array.prototype.slice.call(
                template.content.querySelector('.purchase-order-product').options
            ).slice(1).map(function (option) {
                return option.cloneNode(true);
            });
            var nextIndex = Array.prototype.reduce.call(
                lines.querySelectorAll('[name^="items["]'),
                function (highest, input) {
                    var match = input.name.match(/^items\[(\d+)\]/);
                    return match ? Math.max(highest, Number(match[1]) + 1) : highest;
                },
                0
            );

            function filterProducts(line) {
                var category = line.querySelector('.purchase-order-category');
                var product = line.querySelector('.purchase-order-product');
                var selectedProductId = product.value;
                var selectedCategoryId = category.value;

                product.innerHTML = '<option value="">Select product</option>';
                productOptions.forEach(function (option) {
                    if (!selectedCategoryId || option.dataset.categoryId === selectedCategoryId) {
                        product.appendChild(option.cloneNode(true));
                    }
                });

                if (Array.prototype.some.call(product.options, function (option) {
                    return option.value === selectedProductId;
                })) {
                    product.value = selectedProductId;
                }
            }

            function updateDeleteButtons() {
                Array.prototype.forEach.call(
                    lines.querySelectorAll('.purchase-order-line-remove'),
                    function (button) {
                        button.disabled = lines.querySelectorAll('.purchase-order-line').length === 1;
                    }
                );
            }

            Array.prototype.forEach.call(lines.querySelectorAll('.purchase-order-line'), function (line) {
                var product = line.querySelector('.purchase-order-product');
                var category = line.querySelector('.purchase-order-category');
                var selectedOption = product.options[product.selectedIndex];

                if (!category.value && selectedOption && selectedOption.dataset.categoryId) {
                    category.value = selectedOption.dataset.categoryId;
                }

                filterProducts(line);
            });

            document.getElementById('add-purchase-order-line').addEventListener('click', function () {
                var row = template.content.cloneNode(true);
                Array.prototype.forEach.call(row.querySelectorAll('[name]'), function (input) {
                    input.name = input.name.replace('__INDEX__', nextIndex);
                });
                lines.appendChild(row);
                filterProducts(lines.lastElementChild);
                nextIndex += 1;
                updateDeleteButtons();
            });

            lines.addEventListener('change', function (event) {
                var line = event.target.closest('.purchase-order-line');
                if (!line) {
                    return;
                }

                if (event.target.matches('.purchase-order-category')) {
                    filterProducts(line);
                } else if (event.target.matches('.purchase-order-product')) {
                    var selectedOption = event.target.options[event.target.selectedIndex];
                    if (selectedOption && selectedOption.dataset.categoryId) {
                        line.querySelector('.purchase-order-category').value = selectedOption.dataset.categoryId;
                    }
                }
            });

            lines.addEventListener('click', function (event) {
                var button = event.target.closest('.purchase-order-line-remove');
                if (button && !button.disabled) {
                    button.closest('.purchase-order-line').remove();
                    updateDeleteButtons();
                }
            });

            updateDeleteButtons();
        }());
    </script>
@endsection
