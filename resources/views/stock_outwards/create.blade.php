@extends('layouts.app')

@section('title', 'Add Stock Outward')
@section('topbar-title', 'Add stock outward')

@section('content')
    <div class="page-heading">
        <div><span class="section-kicker">INVENTORY &nbsp; &gt; &nbsp; STOCK OUTWARD</span><h1>Add Stock Outward</h1><p>Record inventory issued to a customer, department or team member.</p></div>
        <a class="button button-light" href="{{ route('stock-outwards.index') }}"><span aria-hidden="true">←</span> Back to Stock Outward</a>
    </div>
    @if ($errors->any())
        <div class="form-alert" role="alert"><strong>Please check the form:</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    <section class="form-card outward-form-card">
        <form action="{{ route('stock-outwards.store') }}" method="POST">
            @csrf
            <div class="form-section">
                <div class="form-card-heading"><span class="form-section-icon"><svg><use href="#icon-calendar"></use></svg></span><div><h2>Outward Information</h2><p>Enter the basic details for this stock outward.</p></div></div>
                <div class="form-grid outward-information-grid">
                    <div class="field"><label for="outward_number">Outward No.</label><input class="field-control" id="outward_number" type="text" value="" readonly placeholder="Auto-generated"><small class="field-hint">Auto-generated</small></div>
                    <div class="field"><label for="outward_date">Outward Date <span class="required-mark">*</span></label><input class="field-control" id="outward_date" type="date" name="outward_date" value="{{ old('outward_date', date('Y-m-d')) }}" required></div>
                    <div class="field"><label for="reference_number">Reference Number</label><input class="field-control" id="reference_number" type="text" name="reference_number" value="{{ old('reference_number') }}" placeholder="e.g. REF-001" maxlength="100"></div>
                    <div class="field"><label for="outward_type">Outward Type <span class="required-mark">*</span></label><select class="field-control" id="outward_type" name="outward_type"><option value="Customer Sale" selected>Customer Sale</option></select></div>
                    <div class="field">
                        <label for="customer_id">Customer / Recipient <span class="required-mark">*</span></label>
                        <select class="field-control" id="customer_id" name="customer_id" required data-customer-select>
                            <option value="">Select customer</option>
                            @foreach ($customers as $customer)
                                <option value="{{ $customer->id }}" data-contact="{{ $customer->contact_person }}" data-phone="{{ $customer->phone }}" data-email="{{ $customer->email }}" {{ old('customer_id') == $customer->id ? 'selected' : '' }}>{{ $customer->name }}</option>
                            @endforeach
                        </select>
                        <small class="field-hint" data-customer-details aria-live="polite"></small>
                        @error('customer_id')<small class="field-error">{{ $message }}</small>@enderror
                        @if (!$customers->count())
                            <small class="field-hint">No active customers are available. <a href="{{ route('customers.create') }}">Add a customer</a> first.</small>
                        @endif
                    </div>
                    <div class="field"><label for="location_id">Location / Store <span class="required-mark">*</span></label><select class="field-control" id="location_id" name="location_id"><option value="">Main Store</option></select></div>
                    <div class="field"><label for="sales_order">Sales Order</label><input class="field-control" id="sales_order" type="text" name="sales_order" value="{{ old('sales_order') }}" placeholder="SO-20261006-001"></div>
                    <div class="field"><label for="invoice_no">Invoice (Optional)</label><select class="field-control" id="invoice_no" name="invoice_no"><option value="">Select invoice</option></select></div>
                    <div class="field"><label for="remarks">Remarks</label><textarea class="field-control" id="remarks" name="remarks" rows="2" placeholder="Enter remarks (optional)...">{{ old('remarks') }}</textarea>@error('remarks')<small class="field-error">{{ $message }}</small>@enderror</div>
                </div>
            </div>
            <div class="form-section">
                <div class="form-card-heading outward-items-heading"><span class="form-section-icon form-icon-violet"><svg><use href="#icon-box"></use></svg></span><div><h2>Products / Items</h2><p>Select category and product, with stock checked automatically.</p></div><button class="button button-secondary outward-add-button" type="button" id="add-product-row"><span aria-hidden="true">+</span> Add Product</button></div>
                <div class="table-wrap outward-items-wrap">
                    <table class="inventory-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Category <span class="required-mark">*</span></th>
                                <th>Product <span class="required-mark">*</span></th>
                                <th>Location <span class="required-mark">*</span></th>
                                <th>Available Stock</th>
                                <th>Quantity <span class="required-mark">*</span></th>
                                <th>Rate (₹)</th>
                                <th>Discount (%)</th>
                                <th>GST (%)</th>
                                <th>Total (₹)</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="stock-outward-rows">
                            <tr class="stock-row" data-row-index="1">
                                <td class="row-number" aria-label="Item number">1</td>
                                <td data-label="Category"><div class="outward-row-field"><label>Category <span class="required-mark">*</span></label><select class="field-control" name="category_id[]" data-row-field="category" required><option value="">Select category</option>@foreach ($categories as $category)<option value="{{ $category->id }}" {{ old('category_id') == $category->id ? 'selected' : '' }}>{{ $category->name }}</option>@endforeach</select></div></td>
                                <td data-label="Product"><div class="outward-row-field"><label>Product <span class="required-mark">*</span></label><select class="field-control" name="product_id[]" data-row-field="product" required><option value="">Select product</option>@foreach ($products as $product)<option value="{{ $product->id }}" data-category="{{ $product->category_id }}" data-stock="{{ $product->current_stock }}" data-price="{{ $product->selling_price }}" data-location="{{ collect([optional($product->hall)->name, optional($product->rack)->name, optional($product->shelf)->name])->filter()->implode(' / ') }}" {{ old('product_id') == $product->id ? 'selected' : '' }}>{{ $product->name }} ({{ $product->product_code }})</option>@endforeach</select></div></td>
                                <td data-label="Location"><div class="outward-row-field"><label>Location</label><div class="outward-location" data-row-field="location">Select product</div></div></td>
                                <td data-label="Available Stock"><div class="outward-row-field"><label>Available Stock</label><div class="stock-badge" data-row-field="available-stock">—</div></div></td>
                                <td data-label="Quantity"><div class="outward-row-field"><label>Quantity <span class="required-mark">*</span></label><input class="field-control" type="number" name="quantity[]" data-row-field="quantity" min="0.01" step="0.01" value="" placeholder="0.00" required></div></td>
                                <td data-label="Rate (₹)"><div class="outward-row-field"><label>Rate (₹) <span class="required-mark">*</span></label><input class="field-control" type="number" name="rate[]" data-row-field="rate" min="0" step="0.01" value="" placeholder="0.00" required></div></td>
                                <td data-label="Discount (%)"><div class="outward-row-field"><label>Discount (%)</label><input class="field-control" type="number" name="discount[]" data-row-field="discount" min="0" max="100" step="0.01" value="" placeholder="0.00"></div></td>
                                <td data-label="GST (%)"><div class="outward-row-field"><label>GST (%)</label><input class="field-control" type="number" name="gst[]" data-row-field="gst" min="0" max="100" step="0.01" value="18" placeholder="0.00"></div></td>
                                <td data-label="Total (₹)"><div class="outward-row-field"><label>Total (₹)</label><input class="field-control outward-calculated" type="number" name="total[]" data-row-field="total" step="0.01" value="" readonly placeholder="0.00"></div></td>
                                <td data-label="Action"><div class="outward-row-field"><label>Action</label><button class="button button-danger button-small outward-delete-button" type="button" aria-label="Delete product row" data-delete-row>Delete</button></div></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="validation-banner success" id="stock-validation-banner"><span class="checkmark">✓</span> <span id="stock-validation-message">Add products to begin stock validation.</span></div>
            </div>
            <div class="outward-summary-grid">
                <div class="summary-card"><h3><span class="form-section-icon"><svg><use href="#icon-calendar"></use></svg></span>Stock Summary</h3><dl><div><dt>Subtotal</dt><dd id="summary-subtotal">₹0.00</dd></div><div><dt>Discount (-)</dt><dd id="summary-discount">₹0.00</dd></div><div><dt>Taxable Amount</dt><dd id="summary-taxable">₹0.00</dd></div><div><dt>CGST (9%)</dt><dd id="summary-cgst">₹0.00</dd></div><div><dt>SGST (9%)</dt><dd id="summary-sgst">₹0.00</dd></div><div class="grand-total"><dt>Total Amount</dt><dd id="summary-total">₹0.00</dd></div></dl></div>
                <div class="validation-card"><h3><span class="outward-validation-icon"><svg><use href="#icon-check"></use></svg></span>Stock Validation</h3><ul class="stock-checks" id="stock-validation-list"><li class="empty-state"><span class="status neutral">Waiting</span><div><strong>No product selected</strong><small>Add a product row to check stock availability.</small></div></li></ul></div>
            </div>
            <div class="form-actions"><a class="button button-light" href="{{ route('stock-outwards.index') }}">Cancel</a><button class="button button-primary" id="saveButton" type="submit" disabled>Save Stock Outward</button></div>
        </form>
    </section>
    @php
        $outwardCategoryOptions = $categories->map(function ($category) {
            return ['id' => (string) $category->id, 'name' => $category->name];
        })->values();
        $outwardProductOptions = $products->map(function ($product) {
            return [
                'id' => (string) $product->id,
                'category' => (string) $product->category_id,
                'stock' => (float) $product->current_stock,
                'price' => (float) $product->selling_price,
                'location' => collect([optional($product->hall)->name, optional($product->rack)->name, optional($product->shelf)->name])->filter()->implode(' / '),
                'label' => $product->name . ' (' . $product->product_code . ')',
            ];
        })->values();
    @endphp
    <script>
    (function () {
        var customerSelect = document.querySelector('[data-customer-select]');
            var customerDetails = document.querySelector('[data-customer-details]');
            var stockTableBody = document.getElementById('stock-outward-rows');
            var addProductButton = document.getElementById('add-product-row');
            var validationBanner = document.getElementById('stock-validation-banner');
            var validationMessage = document.getElementById('stock-validation-message');
            var validationList = document.getElementById('stock-validation-list');
            var saveButton = document.getElementById('saveButton');
            var currencyFormatter = new Intl.NumberFormat('en-IN', {
                style: 'currency',
                currency: 'INR',
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
            var categoryOptions = @json($outwardCategoryOptions);
            var productOptions = @json($outwardProductOptions);

            function formatCurrency(value) {
                return currencyFormatter.format(Number(value || 0));
            }

            function showCustomerDetails() {
                if (!customerSelect || !customerDetails) {
                    return;
                }
                var option = customerSelect.options[customerSelect.selectedIndex];
                if (!option || !option.value) {
                    customerDetails.textContent = '';
                    return;
                }
                customerDetails.textContent = [
                    option.dataset.contact,
                    option.dataset.phone,
                    option.dataset.email
                ].filter(Boolean).join(' · ');
            }

            function filterProducts(row, preserveProduct) {
                var categorySelect = row.querySelector('[data-row-field="category"]');
                var productSelect = row.querySelector('[data-row-field="product"]');
                var selectedProductId = preserveProduct ? productSelect.value : '';
                var categoryId = categorySelect.value;

                Array.prototype.forEach.call(productSelect.options, function (option) {
                    option.hidden = option.value !== ''
                        && categoryId !== ''
                        && option.dataset.category !== categoryId;
                });

                if (selectedProductId && productSelect.options[productSelect.selectedIndex].hidden) {
                    productSelect.value = '';
                }

                updateProductDetails(row);
            }

            function updateProductDetails(row) {
                var productSelect = row.querySelector('[data-row-field="product"]');
                var selectedProduct = productSelect.options[productSelect.selectedIndex];
                var location = row.querySelector('[data-row-field="location"]');
                var rateInput = row.querySelector('[data-row-field="rate"]');

                if (!selectedProduct || !selectedProduct.value) {
                    location.textContent = 'Select product';
                    location.title = '';
                    row.querySelector('[data-row-field="available-stock"]').textContent = '—';
                    return;
                }

                var locationText = selectedProduct.dataset.location || 'No storage location assigned';
                location.textContent = locationText;
                location.title = locationText;
                if (!rateInput.value || rateInput.dataset.autoRate === 'true') {
                    rateInput.value = selectedProduct.dataset.price || '';
                    rateInput.dataset.autoRate = 'true';
                }
            }

            function getRowData(row) {
                var productSelect = row.querySelector('[data-row-field="product"]');
                var categorySelect = row.querySelector('[data-row-field="category"]');
                var quantityInput = row.querySelector('[data-row-field="quantity"]');
                var rateInput = row.querySelector('[data-row-field="rate"]');
                var discountInput = row.querySelector('[data-row-field="discount"]');
                var gstInput = row.querySelector('[data-row-field="gst"]');
                var totalInput = row.querySelector('[data-row-field="total"]');
                var stockDisplay = row.querySelector('[data-row-field="available-stock"]');

                var selectedProduct = productSelect && productSelect.options[productSelect.selectedIndex];
                var availableStock = Number((selectedProduct && selectedProduct.dataset.stock) || 0);
                var rate = Number(rateInput && rateInput.value || 0);
                var quantity = Number(quantityInput && quantityInput.value || 0);
                var discountPercent = Number(discountInput && discountInput.value || 0);
                var gstPercent = Number(gstInput && gstInput.value || 0);
                var taxable = quantity * rate * (1 - discountPercent / 100);
                var gstAmount = taxable * (gstPercent / 100);
                var total = taxable + gstAmount;

                if (totalInput) {
                    totalInput.value = total.toFixed(2);
                }

                var invalid = Boolean(productSelect && productSelect.value
                    && (quantity <= 0 || quantity > availableStock));
                if (stockDisplay) {
                    stockDisplay.textContent = selectedProduct && selectedProduct.value ? availableStock.toLocaleString('en-IN') : '—';
                    stockDisplay.classList.toggle('is-danger', invalid);
                    stockDisplay.classList.toggle('is-valid', Boolean(selectedProduct && selectedProduct.value && !invalid));
                }

                return {
                    row: row,
                    category: categorySelect ? categorySelect.value : '',
                    product: productSelect ? productSelect.value : '',
                    quantity: quantity,
                    rate: rate,
                    discountPercent: discountPercent,
                    gstPercent: gstPercent,
                    availableStock: availableStock,
                    taxable: taxable,
                    gstAmount: gstAmount,
                    total: total,
                    invalid: invalid,
                    productName: selectedProduct ? selectedProduct.textContent.trim() : ''
                };
            }

            function refreshSummary() {
                var rows = Array.prototype.slice.call(document.querySelectorAll('.stock-row'));
                var subtotal = 0;
                var discount = 0;
                var taxableTotal = 0;
                var cgstTotal = 0;
                var sgstTotal = 0;
                var invalidCount = 0;
                var validRows = [];

                rows.forEach(function (row) {
                    var rowData = getRowData(row);
                    if (!rowData.product) {
                        return;
                    }

                    var rowSubtotal = rowData.quantity * rowData.rate;
                    var rowDiscount = rowSubtotal * (rowData.discountPercent / 100);
                    var rowTaxable = rowData.taxable;
                    var rowCgst = rowTaxable * (rowData.gstPercent / 2 / 100);
                    var rowSgst = rowTaxable * (rowData.gstPercent / 2 / 100);

                    subtotal += rowSubtotal;
                    discount += rowDiscount;
                    taxableTotal += rowTaxable;
                    cgstTotal += rowCgst;
                    sgstTotal += rowSgst;

                    if (rowData.invalid) {
                        invalidCount += 1;
                    }

                    validRows.push({
                        name: rowData.productName,
                        available: rowData.availableStock,
                        required: rowData.quantity,
                        remaining: rowData.availableStock - rowData.quantity,
                        valid: !rowData.invalid
                    });
                });

                var totalAmount = taxableTotal + cgstTotal + sgstTotal;
                document.getElementById('summary-subtotal').textContent = formatCurrency(subtotal);
                document.getElementById('summary-discount').textContent = '-' + formatCurrency(discount);
                document.getElementById('summary-taxable').textContent = formatCurrency(taxableTotal);
                document.getElementById('summary-cgst').textContent = formatCurrency(cgstTotal);
                document.getElementById('summary-sgst').textContent = formatCurrency(sgstTotal);
                document.getElementById('summary-total').textContent = formatCurrency(totalAmount);

                if (!validRows.length) {
                    validationList.innerHTML = '<li class="empty-state"><span class="status neutral">Waiting</span><div><strong>No product selected</strong><small>Add a product row to check stock availability.</small></div></li>';
                    validationMessage.textContent = 'Add products to begin stock validation.';
                    validationBanner.className = 'validation-banner neutral';
                    validationBanner.innerHTML = '<span class="checkmark">•</span> <span id="stock-validation-message">Add products to begin stock validation.</span>';
                    saveButton.disabled = true;
                    return;
                }

                validationList.innerHTML = validRows.map(function (item) {
                    var statusLabel = item.valid ? 'Valid' : 'Insufficient';
                    var statusClass = item.valid ? 'valid' : 'invalid';
                    var remainingText = 'Available: ' + item.available + ' | Required: ' + item.required + ' | Remaining: ' + item.remaining;
                    return '<li><span class="status ' + statusClass + '">' + statusLabel + '</span><div><strong>' + (item.name || 'Product') + '</strong><small>' + remainingText + '</small></div></li>';
                }).join('');

                if (invalidCount > 0) {
                    validationMessage.textContent = 'Some products exceed their available stock.';
                    validationBanner.className = 'validation-banner error';
                    validationBanner.innerHTML = '<span class="checkmark">!</span> <span id="stock-validation-message">Some products exceed their available stock.</span>';
                    saveButton.disabled = true;
                    return;
                }

                validationMessage.textContent = 'All products have sufficient stock available.';
                validationBanner.className = 'validation-banner success';
                validationBanner.innerHTML = '<span class="checkmark">✓</span> <span id="stock-validation-message">All products have sufficient stock available.</span>';
                saveButton.disabled = false;
            }

            function setRowListeners(row) {
                row.querySelectorAll('input, select').forEach(function (input) {
                    input.addEventListener('input', refreshSummary);
                    input.addEventListener('change', function () {
                        if (input.matches('[data-row-field="category"]')) {
                            filterProducts(row, true);
                        } else if (input.matches('[data-row-field="product"]')) {
                            var selectedProduct = input.options[input.selectedIndex];
                            var category = row.querySelector('[data-row-field="category"]');
                            if (selectedProduct && selectedProduct.dataset.category) {
                                category.value = selectedProduct.dataset.category;
                                filterProducts(row, true);
                            }
                        } else if (input.matches('[data-row-field="rate"]')) {
                            input.dataset.autoRate = 'false';
                        }
                        refreshSummary();
                    });
                });
            }

            function addRow() {
                var template = document.createElement('tr');
                template.className = 'stock-row';
                var rowIndex = document.querySelectorAll('.stock-row').length + 1;
                template.setAttribute('data-row-index', rowIndex);
                template.innerHTML = '<td class="row-number" aria-label="Item number">' + rowIndex + '</td>' +
                    '<td data-label="Category"><div class="outward-row-field"><label>Category <span class="required-mark">*</span></label><select class="field-control" name="category_id[]" data-row-field="category" required><option value="">Select category</option></select></div></td>' +
                    '<td data-label="Product"><div class="outward-row-field"><label>Product <span class="required-mark">*</span></label><select class="field-control" name="product_id[]" data-row-field="product" required><option value="">Select product</option></select></div></td>' +
                    '<td data-label="Location"><div class="outward-row-field"><label>Location</label><div class="outward-location" data-row-field="location">Select product</div></div></td>' +
                    '<td data-label="Available Stock"><div class="outward-row-field"><label>Available Stock</label><div class="stock-badge" data-row-field="available-stock">—</div></div></td>' +
                    '<td data-label="Quantity"><div class="outward-row-field"><label>Quantity <span class="required-mark">*</span></label><input class="field-control" type="number" name="quantity[]" data-row-field="quantity" min="0.01" step="0.01" placeholder="0.00" required></div></td>' +
                    '<td data-label="Rate (₹)"><div class="outward-row-field"><label>Rate (₹) <span class="required-mark">*</span></label><input class="field-control" type="number" name="rate[]" data-row-field="rate" min="0" step="0.01" placeholder="0.00" required></div></td>' +
                    '<td data-label="Discount (%)"><div class="outward-row-field"><label>Discount (%)</label><input class="field-control" type="number" name="discount[]" data-row-field="discount" min="0" max="100" step="0.01" placeholder="0.00"></div></td>' +
                    '<td data-label="GST (%)"><div class="outward-row-field"><label>GST (%)</label><input class="field-control" type="number" name="gst[]" data-row-field="gst" min="0" max="100" step="0.01" value="18" placeholder="0.00"></div></td>' +
                    '<td data-label="Total (₹)"><div class="outward-row-field"><label>Total (₹)</label><input class="field-control outward-calculated" type="number" name="total[]" data-row-field="total" step="0.01" readonly placeholder="0.00"></div></td>' +
                    '<td data-label="Action"><div class="outward-row-field"><label>Action</label><button class="button button-danger button-small outward-delete-button" type="button" aria-label="Delete product row" data-delete-row>Delete</button></div></td>';
                var categorySelect = template.querySelector('[data-row-field="category"]');
                categoryOptions.forEach(function (item) {
                    categorySelect.add(new Option(item.name, item.id));
                });
                var productSelect = template.querySelector('[data-row-field="product"]');
                productOptions.forEach(function (item) {
                    var option = new Option(item.label, item.id);
                    option.dataset.category = item.category;
                    option.dataset.stock = item.stock;
                    option.dataset.price = item.price;
                    option.dataset.location = item.location;
                    productSelect.add(option);
                });
                stockTableBody.appendChild(template);
                setRowListeners(template);
                updateDeleteButtons();
                refreshSummary();
            }

            function updateDeleteButtons() {
                var rows = Array.prototype.slice.call(document.querySelectorAll('.stock-row'));
                rows.forEach(function (row, index) {
                    row.querySelector('.row-number').textContent = index + 1;
                    row.dataset.rowIndex = index + 1;
                    row.querySelector('[data-delete-row]').disabled = rows.length === 1;
                });
            }

            function removeRow(button) {
                var row = button.closest('.stock-row');
                if (!row || document.querySelectorAll('.stock-row').length === 1) {
                    return;
                }
                row.remove();
                updateDeleteButtons();
                refreshSummary();
            }

            if (customerSelect) {
                customerSelect.addEventListener('change', showCustomerDetails);
                showCustomerDetails();
            }

            document.body.addEventListener('click', function (event) {
                var deleteButton = event.target.closest('[data-delete-row]');
                if (deleteButton) {
                    removeRow(deleteButton);
                }
            });

            if (addProductButton) {
                addProductButton.addEventListener('click', addRow);
            }

            document.querySelectorAll('.stock-row').forEach(function (row) {
                setRowListeners(row);
                filterProducts(row, true);
            });

            updateDeleteButtons();
            refreshSummary();
        }());
    </script>
@endsection
