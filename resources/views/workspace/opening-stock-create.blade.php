@extends('layouts.app')

@section('title', 'Add Opening Stock')
@section('topbar-title', 'Opening Stock')

@section('content')
    @php
        $oldOpeningItems = old('items');
        $initialItems = is_array($oldOpeningItems) && count($oldOpeningItems)
            ? array_values($oldOpeningItems)
            : [[
            'category_id' => '',
            'product_id' => '',
            'quantity' => '',
            'unit_purchase_cost' => '',
            'batch_lot' => '',
            'serial_numbers' => '',
            'remarks' => '',
        ]];
    @endphp
    <div class="page-heading opening-stock-heading">
        <div>
            <span class="section-kicker">INVENTORY / OPENING STOCK</span>
            <h1>Opening Stock</h1>
            <p>Set the initial physical inventory balance for a selected location.</p>
        </div>
        <a class="button button-light" href="{{ route('opening-stock.index') }}">Back to Opening Stock</a>
    </div>

    <div class="opening-stock-info-strip" role="note">
        <span aria-hidden="true">i</span>
        <div><strong>One-time initial setup</strong><small>Opening Stock is used only for initial setup. Use Stock Inward for future stock receipts.</small></div>
    </div>

    @if ($errors->any())
        <div class="form-alert" role="alert">
            <strong>Please review the form:</strong>
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <form action="{{ route('opening-stock.store') }}" method="POST" id="opening-stock-form">
        @csrf
        <section class="panel opening-stock-card">
            <div class="opening-stock-card-heading">
                <div><span class="section-kicker">DOCUMENT DETAILS</span><h2>Opening Stock Information</h2></div>
                <span class="opening-stock-status">Will be posted</span>
            </div>
            <div class="opening-stock-information-grid">
                <div class="opening-stock-document-number"><span>Opening Stock No.</span><strong id="opening-stock-number">Generated when posted</strong></div>
                <div class="field"><label for="transaction_date">Opening Date <span class="required-mark">*</span></label><input class="field-control" id="transaction_date" type="date" name="transaction_date" value="{{ old('transaction_date', now()->toDateString()) }}" required>@error('transaction_date')<small class="field-error">{{ $message }}</small>@enderror</div>
                <div class="field"><label for="store_id">Location <span class="required-mark">*</span></label><select class="field-control" id="store_id" name="store_id" required><option value="">Select location</option>@foreach ($locations as $location)<option value="{{ $location->id }}" {{ (string) old('store_id') === (string) $location->id ? 'selected' : '' }}>{{ $location->name }} ({{ $location->code }})</option>@endforeach</select>@error('store_id')<small class="field-error">{{ $message }}</small>@enderror</div>
                <div class="opening-stock-entered-by"><span>Entered By</span><strong>{{ optional(auth()->user())->name ?: 'Signed-in user' }}</strong><small>Set automatically from your account</small></div>
            </div>
        </section>

        <section class="panel opening-stock-card opening-stock-items-card">
            <div class="opening-stock-card-heading">
                <div><span class="section-kicker">PHYSICAL COUNT</span><h2>Opening Stock Items</h2><p>Enter the quantity physically available at this location for each product.</p></div>
                <button class="button button-primary opening-stock-add-button" type="button" id="add-opening-stock-item"><span class="button-plus">+</span> Add Product</button>
            </div>
            <div class="opening-stock-table-wrap" tabindex="0" role="region" aria-label="Opening stock items. Scroll horizontally to view all columns.">
                <table class="opening-stock-table">
                    <thead><tr><th>#</th><th>Category <b>*</b></th><th>Product <b>*</b></th><th>SKU</th><th>Physical Opening Qty <b>*</b></th><th>Unit</th><th>Unit Purchase Cost <b>*</b></th><th>Opening Value</th><th>Batch / Lot</th><th>Serial Numbers</th><th>Remarks</th><th></th></tr></thead>
                    <tbody id="opening-stock-items">
                        @foreach ($initialItems as $index => $initialItem)
                            <tr class="opening-stock-row">
                                <td class="opening-stock-row-number">{{ $index + 1 }}</td>
                                <td><select class="field-control opening-stock-category" name="items[{{ $index }}][category_id]" required><option value="">Select category</option>@foreach ($categories as $category)<option value="{{ $category->id }}" {{ (string) data_get($initialItem, 'category_id') === (string) $category->id ? 'selected' : '' }}>{{ $category->name }}</option>@endforeach</select></td>
                                <td><select class="field-control opening-stock-product" name="items[{{ $index }}][product_id]" required disabled><option value="">Select category first</option></select><small class="field-error opening-stock-row-error" hidden></small></td>
                                <td><span class="opening-stock-sku">—</span></td>
                                <td><input class="field-control opening-stock-quantity" type="number" name="items[{{ $index }}][quantity]" min="0.01" step="0.01" value="{{ data_get($initialItem, 'quantity') }}" placeholder="0" required><small class="field-hint">Physical count</small></td>
                                <td><span class="opening-stock-unit">—</span></td>
                                <td><input class="field-control opening-stock-cost" type="number" name="items[{{ $index }}][unit_purchase_cost]" min="0" step="0.01" value="{{ data_get($initialItem, 'unit_purchase_cost') }}" placeholder="0.00" required></td>
                                <td><strong class="opening-stock-value">₹0.00</strong></td>
                                <td><input class="field-control opening-stock-batch" type="text" name="items[{{ $index }}][batch_lot]" value="{{ data_get($initialItem, 'batch_lot') }}" maxlength="191" placeholder="N/A" disabled></td>
                                <td><textarea class="field-control opening-stock-serials" name="items[{{ $index }}][serial_numbers]" rows="2" placeholder="N/A" disabled>{{ data_get($initialItem, 'serial_numbers') }}</textarea></td>
                                <td><input class="field-control" type="text" name="items[{{ $index }}][remarks]" value="{{ data_get($initialItem, 'remarks') }}" maxlength="1000" placeholder="Optional"></td>
                                <td><button class="opening-stock-delete" type="button" aria-label="Delete product row" title="Delete product row">Delete</button></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="opening-stock-table-note">Scroll horizontally to view all item fields. Products can appear once per document; batch-tracked products may use separate rows for distinct batches.</p>
        </section>

        <div class="opening-stock-summary-grid">
            <section class="panel opening-stock-summary-card">
                <div class="opening-stock-card-heading"><div><span class="section-kicker">REVIEW</span><h2>Stock Preview</h2></div></div>
                <div id="opening-stock-preview" class="opening-stock-preview"><p>Select a location and products to preview stock balances.</p></div>
                <div id="opening-stock-existing-warning" class="opening-stock-warning" hidden>
                    <strong>Existing stock found</strong>
                    <p>Stock already exists for at least one product. Opening Stock will be added to the balance and will not overwrite it. Review the current balance before proceeding.</p>
                    <label><input type="checkbox" name="confirm_existing_stock" value="1" {{ old('confirm_existing_stock') ? 'checked' : '' }}> I reviewed the existing stock warning and want to continue.</label>
                </div>
            </section>
            <section class="panel opening-stock-summary-card">
                <div class="opening-stock-card-heading"><div><span class="section-kicker">LIVE CHECKS</span><h2>Validation Status</h2></div></div>
                <ul class="opening-stock-validation-list" id="opening-stock-validation">
                    <li data-check="location"><span>○</span> Location selected</li>
                    <li data-check="products"><span>○</span> Products selected and valid</li>
                    <li data-check="quantities"><span>○</span> Quantities and costs valid</li>
                    <li data-check="duplicates"><span>○</span> No duplicate products</li>
                    <li data-check="tracking"><span>○</span> Batch / serial tracking complete</li>
                </ul>
            </section>
        </div>

        <section class="opening-stock-total-bar">
            <div><span>Item rows</span><strong id="opening-stock-item-count">0</strong></div>
            <div><span>Quantity by unit</span><strong id="opening-stock-total-quantity">—</strong></div>
            <div><span>Total Opening Value</span><strong id="opening-stock-total-value">₹0.00</strong></div>
        </section>

        @if (!$products->count() || !$locations->count())
            <div class="inventory-notice notice-out"><span class="notice-symbol" aria-hidden="true">!</span><div><strong>Setup required</strong><small>{{ !$products->count() && !$locations->count() ? 'Add an active product and an active location before recording opening stock.' : (!$products->count() ? 'Add an active product before recording opening stock.' : 'Add an active location before recording opening stock.') }}</small></div></div>
        @endif
        <div class="form-actions opening-stock-actions">
            <a class="button button-light" href="{{ route('opening-stock.index') }}">Cancel</a>
            <button class="button button-primary" type="submit" id="post-opening-stock" {{ !$products->count() || !$locations->count() ? 'disabled' : '' }}>Post Opening Stock</button>
        </div>
    </form>
@endsection

@section('scripts')
    <script>
        (function () {
            var products = @json($productData);
            var oldItems = @json($initialItems);
            var body = document.getElementById('opening-stock-items');
            var addButton = document.getElementById('add-opening-stock-item');
            var storeSelect = document.getElementById('store_id');
            var form = document.getElementById('opening-stock-form');
            var postButton = document.getElementById('post-opening-stock');
            var currency = new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR', minimumFractionDigits: 2 });

            function escapeHtml(value) {
                return String(value).replace(/[&<>"']/g, function (character) {
                    return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[character];
                });
            }

            function productById(id) {
                return products.find(function (product) { return String(product.id) === String(id); });
            }

            function productRowMarkup(index) {
                var categories = @json($categoryData);
                var categoryOptions = '<option value="">Select category</option>' + categories.map(function (category) {
                    return '<option value="' + category.id + '">' + escapeHtml(category.name) + '</option>';
                }).join('');
                return '<tr class="opening-stock-row">' +
                    '<td class="opening-stock-row-number">' + (index + 1) + '</td>' +
                    '<td><select class="field-control opening-stock-category" name="items[' + index + '][category_id]" required>' + categoryOptions + '</select></td>' +
                    '<td><select class="field-control opening-stock-product" name="items[' + index + '][product_id]" required disabled><option value="">Select category first</option></select><small class="field-error opening-stock-row-error" hidden></small></td>' +
                    '<td><span class="opening-stock-sku">—</span></td>' +
                    '<td><input class="field-control opening-stock-quantity" type="number" name="items[' + index + '][quantity]" min="0.01" step="0.01" placeholder="0" required><small class="field-hint">Physical count</small></td>' +
                    '<td><span class="opening-stock-unit">—</span></td>' +
                    '<td><input class="field-control opening-stock-cost" type="number" name="items[' + index + '][unit_purchase_cost]" min="0" step="0.01" placeholder="0.00" required></td>' +
                    '<td><strong class="opening-stock-value">₹0.00</strong></td>' +
                    '<td><input class="field-control opening-stock-batch" type="text" name="items[' + index + '][batch_lot]" maxlength="191" placeholder="N/A" disabled></td>' +
                    '<td><textarea class="field-control opening-stock-serials" name="items[' + index + '][serial_numbers]" rows="2" placeholder="N/A" disabled></textarea></td>' +
                    '<td><input class="field-control" type="text" name="items[' + index + '][remarks]" maxlength="1000" placeholder="Optional"></td>' +
                    '<td><button class="opening-stock-delete" type="button" aria-label="Delete product row" title="Delete product row">Delete</button></td></tr>';
            }

            function setProductOptions(row, selectedId) {
                var categorySelect = row.querySelector('.opening-stock-category');
                var productSelect = row.querySelector('.opening-stock-product');
                var categoryId = categorySelect.value;
                var available = products.filter(function (product) {
                    return String(product.category_id) === String(categoryId);
                });
                productSelect.innerHTML = '<option value="">' + (categoryId ? 'Select product' : 'Select category first') + '</option>';
                available.forEach(function (product) {
                    var option = document.createElement('option');
                    option.value = product.id;
                    option.textContent = product.name + ' (' + product.sku + ')';
                    productSelect.appendChild(option);
                });
                productSelect.disabled = !categoryId;
                if (selectedId) productSelect.value = String(selectedId);
            }

            function setTrackingFields(row, product) {
                var batch = row.querySelector('.opening-stock-batch');
                var serials = row.querySelector('.opening-stock-serials');
                batch.disabled = !product || !product.track_batch;
                batch.required = !!(product && product.track_batch);
                batch.placeholder = product && product.track_batch ? 'Enter batch / lot' : 'N/A';
                serials.disabled = !product || !product.track_serial;
                serials.required = !!(product && product.track_serial);
                serials.placeholder = product && product.track_serial ? 'One serial per line or comma' : 'N/A';
            }

            function updateProductMeta(row) {
                var selectedProduct = productById(row.querySelector('.opening-stock-product').value);
                row.querySelector('.opening-stock-sku').textContent = selectedProduct ? selectedProduct.sku : '—';
                row.querySelector('.opening-stock-unit').textContent = selectedProduct ? selectedProduct.unit : '—';
                var cost = row.querySelector('.opening-stock-cost');
                if (selectedProduct && !cost.dataset.edited) cost.value = Number(selectedProduct.purchase_price || 0).toFixed(2);
                if (!selectedProduct) {
                    cost.value = '';
                    delete cost.dataset.edited;
                }
                setTrackingFields(row, selectedProduct);
                updateRowValue(row);
            }

            function updateRowValue(row) {
                var quantity = parseFloat(row.querySelector('.opening-stock-quantity').value) || 0;
                var cost = parseFloat(row.querySelector('.opening-stock-cost').value) || 0;
                row.querySelector('.opening-stock-value').textContent = currency.format(quantity * cost);
            }

            function locationBalance(product) {
                var balance = product && product.balances ? Number(product.balances[storeSelect.value] || 0) : 0;
                return isFinite(balance) ? balance : 0;
            }

            function openingAlreadyPosted(product) {
                return !!(product && product.opening_stores.indexOf(Number(storeSelect.value)) !== -1);
            }

            function setCheck(name, valid, message) {
                var item = document.querySelector('[data-check="' + name + '"]');
                item.classList.toggle('is-valid', valid);
                item.classList.toggle('is-invalid', !valid);
                item.querySelector('span').textContent = valid ? '✓' : '○';
                if (message) item.lastChild.textContent = ' ' + message;
            }

            function updateFormStatus() {
                var rows = Array.prototype.slice.call(body.querySelectorAll('.opening-stock-row'));
                var selected = rows.map(function (row) { return productById(row.querySelector('.opening-stock-product').value); });
                var locationSelected = !!storeSelect.value;
                var productsValid = rows.length > 0 && rows.every(function (row) {
                    return !!row.querySelector('.opening-stock-category').value && !!row.querySelector('.opening-stock-product').value;
                });
                var quantitiesValid = rows.every(function (row) {
                    var quantity = parseFloat(row.querySelector('.opening-stock-quantity').value);
                    var cost = parseFloat(row.querySelector('.opening-stock-cost').value);
                    return quantity > 0 && isFinite(quantity) && cost >= 0 && isFinite(cost);
                });
                var duplicatesValid = true;
                var byProduct = {};
                rows.forEach(function (row, index) {
                    var product = selected[index];
                    if (!product) return;
                    byProduct[product.id] = byProduct[product.id] || [];
                    byProduct[product.id].push({ batch: row.querySelector('.opening-stock-batch').value.trim(), tracksBatch: product.track_batch });
                    if (openingAlreadyPosted(product)) duplicatesValid = false;
                });
                Object.keys(byProduct).forEach(function (id) {
                    var entries = byProduct[id];
                    if (entries.length > 1 && (!entries.every(function (entry) { return entry.tracksBatch && entry.batch; })
                        || new Set(entries.map(function (entry) { return entry.batch.toLowerCase(); })).size !== entries.length)) {
                        duplicatesValid = false;
                    }
                });
                var trackingValid = rows.every(function (row, index) {
                    var product = selected[index];
                    if (!product) return false;
                    var quantity = Number(row.querySelector('.opening-stock-quantity').value);
                    var batch = row.querySelector('.opening-stock-batch').value.trim();
                    var serialNumbers = row.querySelector('.opening-stock-serials').value.split(/[\r\n,]+/).map(function (value) { return value.trim(); }).filter(Boolean);
                    if (product.track_batch && !batch) return false;
                    if (product.track_serial && (Math.floor(quantity) !== quantity || serialNumbers.length !== quantity)) return false;
                    if (!product.track_serial && serialNumbers.length) return false;
                    return true;
                });
                setCheck('location', locationSelected);
                setCheck('products', productsValid);
                setCheck('quantities', quantitiesValid);
                setCheck('duplicates', duplicatesValid);
                setCheck('tracking', trackingValid);

                var preview = document.getElementById('opening-stock-preview');
                var warning = document.getElementById('opening-stock-existing-warning');
                var existingItems = [];
                var previewProducts = {};
                var unitTotals = {};
                var totalValue = 0;
                rows.forEach(function (row, index) {
                    var product = selected[index];
                    if (!product) return;
                    var quantity = parseFloat(row.querySelector('.opening-stock-quantity').value) || 0;
                    var cost = parseFloat(row.querySelector('.opening-stock-cost').value) || 0;
                    var value = quantity * cost;
                    var balance = locationBalance(product);
                    var duplicateOpening = openingAlreadyPosted(product);
                    if ((Math.abs(balance) > 0.00001 || Math.abs(product.current_stock) > 0.00001 || duplicateOpening) && existingItems.indexOf(product.name) === -1) existingItems.push(product.name);
                    previewProducts[product.id] = previewProducts[product.id] || { product: product, quantity: 0 };
                    previewProducts[product.id].quantity += quantity;
                    unitTotals[product.unit] = (unitTotals[product.unit] || 0) + quantity;
                    totalValue += value;
                });
                warning.hidden = existingItems.length === 0;
                if (!existingItems.length) {
                    var confirmBox = warning.querySelector('input[name="confirm_existing_stock"]');
                    confirmBox.checked = false;
                }
                if (!locationSelected || !Object.keys(previewProducts).length) {
                    preview.innerHTML = '<p>Select a location and products to preview stock balances.</p>';
                } else {
                    preview.innerHTML = Object.keys(previewProducts).map(function (productId) {
                        var previewItem = previewProducts[productId];
                        var product = previewItem.product;
                        var balance = locationBalance(product);
                        return '<div class="opening-stock-preview-line"><span><strong>' + escapeHtml(product.name) + '</strong><small>' + escapeHtml(storeSelect.options[storeSelect.selectedIndex].text) + (openingAlreadyPosted(product) ? ' · Opening Stock already posted' : '') + '</small></span><strong>' + balance.toLocaleString('en-IN') + ' → ' + (balance + previewItem.quantity).toLocaleString('en-IN') + ' ' + escapeHtml(product.unit) + '</strong></div>';
                    }).join('');
                }
                document.getElementById('opening-stock-item-count').textContent = rows.length;
                document.getElementById('opening-stock-total-quantity').textContent = Object.keys(unitTotals).length
                    ? Object.keys(unitTotals).map(function (unit) { return unitTotals[unit].toLocaleString('en-IN') + ' ' + unit; }).join(' · ')
                    : '—';
                document.getElementById('opening-stock-total-value').textContent = currency.format(totalValue);

                var confirmationRequired = existingItems.length > 0;
                var confirmationChecked = !!warning.querySelector('input[name="confirm_existing_stock"]').checked;
                var ready = locationSelected && productsValid && quantitiesValid && duplicatesValid && trackingValid
                    && (!confirmationRequired || confirmationChecked);
                postButton.disabled = !ready;
            }

            function renumberRows() {
                Array.prototype.forEach.call(body.querySelectorAll('.opening-stock-row'), function (row, index) {
                    row.querySelector('.opening-stock-row-number').textContent = index + 1;
                    row.querySelectorAll('[name]').forEach(function (field) {
                        field.name = field.name.replace(/items\[\d+\]/, 'items[' + index + ']');
                    });
                });
            }

            function bindRow(row, oldItem) {
                var category = row.querySelector('.opening-stock-category');
                var product = row.querySelector('.opening-stock-product');
                var cost = row.querySelector('.opening-stock-cost');
                setProductOptions(row, oldItem && oldItem.product_id);
                category.addEventListener('change', function () {
                    setProductOptions(row, '');
                    updateProductMeta(row);
                    updateFormStatus();
                });
                product.addEventListener('change', function () {
                    delete cost.dataset.edited;
                    updateProductMeta(row);
                    updateFormStatus();
                });
                row.addEventListener('input', function (event) {
                    if (event.target === cost) cost.dataset.edited = '1';
                    updateRowValue(row);
                    updateFormStatus();
                });
                row.addEventListener('change', updateFormStatus);
                row.querySelector('.opening-stock-delete').addEventListener('click', function () {
                    row.remove();
                    renumberRows();
                    updateFormStatus();
                });
                updateProductMeta(row);
                if (oldItem && typeof oldItem.unit_purchase_cost !== 'undefined' && oldItem.unit_purchase_cost !== '') {
                    cost.value = oldItem.unit_purchase_cost;
                    cost.dataset.edited = '1';
                }
                updateRowValue(row);
            }

            addButton.addEventListener('click', function () {
                var index = body.querySelectorAll('.opening-stock-row').length;
                body.insertAdjacentHTML('beforeend', productRowMarkup(index));
                bindRow(body.lastElementChild, {});
                updateFormStatus();
            });
            storeSelect.addEventListener('change', updateFormStatus);
            document.querySelector('input[name="confirm_existing_stock"]').addEventListener('change', updateFormStatus);
            document.getElementById('transaction_date').addEventListener('change', function () {
                document.getElementById('opening-stock-number').textContent = 'Generated when posted';
            });

            Array.prototype.forEach.call(body.querySelectorAll('.opening-stock-row'), function (row, index) {
                bindRow(row, oldItems[index] || {});
            });
            updateFormStatus();
        })();
    </script>
@endsection
