@extends('layouts.app')

@section('title', 'Record Stock Transfer')
@section('topbar-title', 'Record stock transfer')

@section('content')
    @php
        $productCatalog = $products->map(function ($product) {
            return [
                'id' => (string) $product->id,
                'name' => $product->name,
                'sku' => $product->sku ?: $product->product_code,
                'category' => (string) $product->category_id,
                'unit' => optional($product->unit)->short_name,
            ];
        })->values();
        $categoryOptions = $categories->map(function ($category) {
            return ['id' => (string) $category->id, 'name' => $category->name];
        })->values();
    @endphp
    <style>
        .stock-transfer-page{--transfer-teal:#0e9f9a;--transfer-blue:#2563eb;--transfer-blue-light:#eff6ff;--transfer-green:#16a34a;--transfer-green-light:#ecfdf5;--transfer-red:#dc2626;--transfer-red-light:#fef2f2;--transfer-amber:#d97706;--transfer-amber-light:#fffbeb;--transfer-border:#e2e8f0;--transfer-text:#0f172a;--transfer-secondary:#64748b;--transfer-muted:#94a3b8}
        .stock-transfer-page .page-heading{align-items:center;margin-bottom:16px}
        .stock-transfer-page .page-heading h1{margin:5px 0 3px;color:var(--transfer-text);font-size:23px;letter-spacing:-.55px}
        .stock-transfer-page .page-heading p{color:var(--transfer-secondary);font-size:12px}
        .stock-transfer-page .section-kicker{color:var(--transfer-blue);font-size:10px;letter-spacing:.65px}
        .stock-transfer-page .button{min-height:36px;border-radius:7px;font-size:11px;transition:background-color .15s ease,border-color .15s ease,color .15s ease,box-shadow .15s ease}
        .stock-transfer-page .button:hover{transform:none}
        .stock-transfer-page .button-primary{background:var(--transfer-teal);box-shadow:none}
        .stock-transfer-page .button-primary:hover{background:#0b8581;box-shadow:none}
        .stock-transfer-page .button-light{border:1px solid var(--transfer-border);color:#334155;background:#fff}
        .stock-transfer-page .button-light:hover{border-color:#cbd5e1;color:var(--transfer-text);background:#f8fafc}
        .stock-transfer-page .form-card{padding:0;border:0;background:transparent;box-shadow:none}
        .stock-transfer-page .form-section{margin:0 0 12px;padding:14px;border:1px solid var(--transfer-border);border-radius:10px;background:#fff}
        .stock-transfer-page .form-section:last-of-type{padding-bottom:14px}
        .stock-transfer-page .transfer-card-heading{display:flex;min-height:42px;align-items:center;gap:10px;margin:-2px 0 12px;padding:7px 9px;border-radius:7px;background:var(--transfer-blue-light)}
        .stock-transfer-page .transfer-card-heading .form-section-icon{width:28px;height:28px;flex-basis:28px;border-radius:7px;color:var(--transfer-blue);background:#dbeafe}
        .stock-transfer-page .transfer-card-heading h2{margin:0;color:var(--transfer-blue);font-size:14px;font-weight:700}
        .stock-transfer-page .transfer-card-heading p{margin:2px 0 0;color:var(--transfer-secondary);font-size:10px}
        .stock-transfer-page .transfer-information-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px 14px}
        .stock-transfer-page .transfer-information-grid .field-wide{grid-column:span 2}
        .stock-transfer-page .field label{margin-bottom:5px;color:#334155;font-size:10px;font-weight:600}
        .stock-transfer-page .required-mark{color:var(--transfer-red)}
        .stock-transfer-page .field-control{min-height:37px;padding:8px 10px;border:1px solid #cbd5e1;border-radius:7px;color:var(--transfer-text);background:#fff;font-size:11px}
        .stock-transfer-page .field-control:focus{border-color:var(--transfer-blue);outline:0;box-shadow:0 0 0 3px rgba(37,99,235,.12)}
        .stock-transfer-page .field-control::placeholder{color:var(--transfer-muted)}
        .stock-transfer-page textarea.field-control{min-height:54px;resize:vertical}
        .stock-transfer-page .field-control[readonly],.stock-transfer-page .field-control:disabled{border-color:var(--transfer-border);color:#475569;background:#f8fafc;opacity:1}
        .stock-transfer-page .field-hint{margin-top:3px;color:var(--transfer-muted);font-size:9px}
        .stock-transfer-page .transfer-status-badge{display:inline-flex;min-height:32px;align-items:center;padding:0 10px;border:1px solid #fde68a;border-radius:6px;color:var(--transfer-amber);background:var(--transfer-amber-light);font-size:11px;font-weight:600}
        .stock-transfer-page .transfer-items-section{padding:14px}
        .stock-transfer-page .transfer-items-heading{background:#f8fafc}
        .stock-transfer-page .transfer-items-heading .button{margin-left:auto}
        .stock-transfer-page .transfer-items-heading .button-secondary{border:1px solid var(--transfer-teal);color:#fff;background:var(--transfer-teal)}
        .stock-transfer-page .transfer-items-heading .button-secondary:hover{border-color:#0b8581;background:#0b8581}
        .stock-transfer-page .transfer-items-wrap{overflow-x:auto}
        .stock-transfer-page .transfer-items-table{width:100%;min-width:1040px;border-collapse:collapse;text-align:left}
        .stock-transfer-page .transfer-items-table th{height:33px;padding:5px 6px;border-bottom:1px solid var(--transfer-border);color:#475569;background:#f8fafc;font-size:10px;font-weight:600;white-space:nowrap}
        .stock-transfer-page .transfer-items-table td{padding:5px 5px;border-bottom:1px solid #edf1f5;vertical-align:middle;color:#334155;font-size:10px}
        .stock-transfer-page .transfer-items-table tr:last-child td{border-bottom:0}
        .stock-transfer-page .transfer-items-table .field-control{min-width:66px;min-height:33px;padding:6px 7px;border-radius:6px;font-size:10px}
        .stock-transfer-page .transfer-items-table td:nth-child(2) .field-control{min-width:88px}
        .stock-transfer-page .transfer-items-table td:nth-child(3) .field-control{min-width:142px}
        .stock-transfer-page .transfer-items-table td:nth-child(10) .field-control{min-width:100px}
        .stock-transfer-page .transfer-product-meta{display:block;margin:3px 2px 0;color:var(--transfer-muted);font-size:9px;white-space:nowrap}
        .stock-transfer-page .transfer-stock-value{display:flex;min-width:60px;min-height:37px;flex-direction:column;align-items:center;justify-content:center;padding:3px 5px;border-radius:6px;color:#166534;background:var(--transfer-green-light);font-size:10px;font-weight:700;line-height:1.25;text-align:center}
        .stock-transfer-page .transfer-stock-value small{color:#15803d;font-size:8px;font-weight:500}
        .stock-transfer-page .transfer-stock-value.is-invalid{color:#b91c1c;background:var(--transfer-red-light)}
        .stock-transfer-page .transfer-stock-value.is-invalid small{color:#b91c1c}
        .stock-transfer-page .transfer-unit{display:block;min-width:34px;color:#475569;font-size:10px;text-align:center}
        .stock-transfer-page .transfer-static-value{display:block;min-width:58px;color:var(--transfer-secondary);font-size:9px;text-align:center}
        .stock-transfer-page .transfer-quantity-max{display:block;margin-top:3px;color:var(--transfer-muted);font-size:8px;white-space:nowrap}
        .stock-transfer-page .transfer-delete-button{display:grid;width:29px;height:29px;place-items:center;padding:0;border:1px solid #fca5a5;border-radius:6px;color:var(--transfer-red);background:#fff;cursor:pointer}
        .stock-transfer-page .transfer-delete-button:hover{border-color:var(--transfer-red);background:var(--transfer-red-light)}
        .stock-transfer-page .transfer-delete-button svg{width:14px;height:14px;fill:none;stroke:currentColor;stroke-linecap:round;stroke-linejoin:round;stroke-width:1.7}
        .stock-transfer-page .transfer-validation{display:flex;min-height:29px;align-items:center;gap:7px;margin-top:10px;padding:6px 9px;border:1px solid transparent;border-radius:6px;color:var(--transfer-secondary);background:#f8fafc;font-size:10px;line-height:1.4}
        .stock-transfer-page .transfer-validation.is-invalid{border-color:#fecaca;color:#b91c1c;background:var(--transfer-red-light)}
        .stock-transfer-page .transfer-validation.is-valid{border-color:#bbf7d0;color:#166534;background:var(--transfer-green-light)}
        .stock-transfer-page .transfer-bottom-grid{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:12px;margin-top:12px}
        .stock-transfer-page .transfer-summary-card{min-width:0;padding:12px;border:1px solid var(--transfer-border);border-radius:9px;background:#fff}
        .stock-transfer-page .transfer-summary-card h3{margin:0 0 9px;color:var(--transfer-text);font-size:12px;font-weight:700}
        .stock-transfer-page .transfer-preview-row{display:grid;grid-template-columns:minmax(0,1fr) 20px minmax(0,1fr);align-items:stretch;gap:6px;margin-top:7px}
        .stock-transfer-page .transfer-preview-product{grid-column:1/-1;margin:2px 0 -2px;color:#334155;font-size:10px;font-weight:600}
        .stock-transfer-page .transfer-preview-location{padding:7px;border:1px solid #e2e8f0;border-radius:6px;background:#f8fafc}
        .stock-transfer-page .transfer-preview-location.source{border-color:#fecaca;background:#fff7f7}
        .stock-transfer-page .transfer-preview-location.destination{border-color:#bbf7d0;background:#f0fdf4}
        .stock-transfer-page .transfer-preview-location strong,.stock-transfer-page .transfer-preview-location small{display:block}
        .stock-transfer-page .transfer-preview-location strong{margin-bottom:4px;color:#334155;font-size:9px}
        .stock-transfer-page .transfer-preview-location small{color:#475569;font-size:9px;line-height:1.6}
        .stock-transfer-page .transfer-preview-location small b{color:var(--transfer-text);font-weight:600}
        .stock-transfer-page .transfer-preview-location .transfer-out{color:#b91c1c}
        .stock-transfer-page .transfer-preview-location .transfer-in{color:#15803d}
        .stock-transfer-page .transfer-preview-arrow{align-self:center;color:var(--transfer-blue);font-size:15px;text-align:center}
        .stock-transfer-page .transfer-check-list{margin:0;padding:0;list-style:none}
        .stock-transfer-page .transfer-check-list li{display:flex;align-items:flex-start;gap:7px;margin-top:7px;color:#475569;font-size:10px;line-height:1.4}
        .stock-transfer-page .transfer-check-list li:first-child{margin-top:0}
        .stock-transfer-page .transfer-check-list .check-valid{color:var(--transfer-green);font-weight:700}
        .stock-transfer-page .transfer-check-list .check-invalid{color:var(--transfer-red);font-weight:700}
        .stock-transfer-page .transfer-check-detail{display:block;color:var(--transfer-secondary);font-size:9px}
        .stock-transfer-page .transfer-check-status{flex:0 0 auto;margin-left:auto;padding:3px 7px;border-radius:10px;color:#166534;background:var(--transfer-green-light);font-size:8px;font-weight:700}
        .stock-transfer-page .transfer-check-status.is-invalid{color:#b91c1c;background:var(--transfer-red-light)}
        .stock-transfer-page .transfer-invalid-row td{background:#fff7f7}
        .stock-transfer-page .transfer-actions{display:flex;justify-content:flex-end;gap:8px;margin-top:12px}
        .stock-transfer-page .transfer-actions .button-light{min-width:76px}
        .stock-transfer-page .transfer-actions .button-complete{min-width:142px;background:var(--transfer-teal)}
        .stock-transfer-page .transfer-actions .button-complete:hover{background:#0b8581}
        .stock-transfer-page .form-alert{border-color:#fecaca;color:#b91c1c;background:var(--transfer-red-light);font-size:11px}
        @media(max-width:1100px){.stock-transfer-page .transfer-items-wrap{overflow-x:auto}.stock-transfer-page .transfer-items-table{min-width:1040px}}
        @media(max-width:760px){.stock-transfer-page .page-heading{align-items:flex-start;flex-direction:column;gap:10px}.stock-transfer-page .transfer-information-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.stock-transfer-page .transfer-information-grid .field-wide{grid-column:span 2}.stock-transfer-page .transfer-bottom-grid{grid-template-columns:1fr}}
        @media(max-width:520px){.stock-transfer-page .transfer-information-grid{grid-template-columns:1fr}.stock-transfer-page .transfer-information-grid .field-wide{grid-column:auto}.stock-transfer-page .form-section{padding:11px}.stock-transfer-page .transfer-card-heading{gap:7px;padding:6px}.stock-transfer-page .transfer-card-heading p{font-size:9px}.stock-transfer-page .transfer-actions{justify-content:stretch}.stock-transfer-page .transfer-actions .button{flex:1;padding:0 8px;font-size:10px}}
    </style>

    <div class="stock-transfer-page">
    <div class="page-heading workspace-page-heading">
        <div>
            <span class="section-kicker">INVENTORY &nbsp; › &nbsp; STOCK TRANSFER &nbsp; › &nbsp; RECORD</span>
            <h1>Record Stock Transfer</h1>
            <p>Move existing stock from one location to another location.</p>
        </div>
        <a class="button button-light" href="{{ route('stock-transfers.index') }}"><span aria-hidden="true">←</span> Back to Stock Transfers</a>
    </div>

    @if ($errors->any())
        <div class="form-alert" role="alert">
            <strong>Please check the form:</strong>
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <section class="form-card">
        <form action="{{ route('stock-transfers.store') }}" method="POST" id="stock-transfer-form">
            @csrf
            <input type="hidden" name="submission_key" value="{{ old('submission_key', (string) \Illuminate\Support\Str::uuid()) }}">
            <div class="form-section">
                <div class="transfer-card-heading">
                    <span class="form-section-icon"><svg><use href="#icon-transfer"></use></svg></span>
                    <div><h2>Transfer Information</h2><p>Enter the basic details for this stock transfer.</p></div>
                </div>
                <div class="transfer-information-grid">
                    <div class="field">
                        <label for="transfer_number">Transfer No.</label>
                        <input class="field-control" id="transfer_number" type="text" value="Generated on completion" readonly>
                        <small class="field-hint">System generated</small>
                    </div>
                    <div class="field">
                        <label for="transfer_date">Transfer Date <span class="required-mark">*</span></label>
                        <input class="field-control" id="transfer_date" type="date" name="transfer_date" value="{{ old('transfer_date', now()->toDateString()) }}" required>
                    </div>
                    <div class="field">
                        <label>Status</label>
                        <span class="transfer-status-badge"><span aria-hidden="true">●</span>&nbsp; Ready to complete</span>
                    </div>
                    <div class="field">
                        <label for="from_location">From Location <span class="required-mark">*</span></label>
                        <select class="field-control" id="from_location" name="from_location" required>
                            <option value="">Select source location</option>
                            @foreach ($locations as $location)
                                <option value="{{ $location->name }}" data-store-id="{{ $location->id }}" {{ old('from_location') === $location->name ? 'selected' : '' }}>{{ $location->name }}</option>
                            @endforeach
                        </select>
                        @error('from_location')<small class="field-error">{{ $message }}</small>@enderror
                    </div>
                    <div class="field">
                        <label for="to_location">To Location <span class="required-mark">*</span></label>
                        <select class="field-control" id="to_location" name="to_location" required>
                            <option value="">Select destination location</option>
                            @foreach ($locations as $location)
                                <option value="{{ $location->name }}" data-store-id="{{ $location->id }}" {{ old('to_location') === $location->name ? 'selected' : '' }}>{{ $location->name }}</option>
                            @endforeach
                        </select>
                        @error('to_location')<small class="field-error">{{ $message }}</small>@enderror
                    </div>
                    <div class="field">
                        <label for="transfer_reason">Transfer Reason <span class="required-mark">*</span></label>
                        <select class="field-control" id="transfer_reason" name="transfer_reason" required>
                            <option value="">Select reason</option>
                            @foreach (['Stock Replenishment', 'Department Requirement', 'Hall Requirement', 'Branch Requirement', 'Customer/Project Requirement', 'Overstock Balancing', 'Location Reorganization', 'Other'] as $reason)
                                <option value="{{ $reason }}" {{ old('transfer_reason') === $reason ? 'selected' : '' }}>{{ $reason }}</option>
                            @endforeach
                        </select>
                        @error('transfer_reason')<small class="field-error">{{ $message }}</small>@enderror
                    </div>
                    <div class="field">
                        <label for="requested_by">Requested By</label>
                        <input class="field-control" id="requested_by" type="text" value="{{ auth()->user()->name }}" readonly>
                    </div>
                    <div class="field field-wide">
                        <label for="remarks">Notes <span class="field-label-optional">(Optional)</span></label>
                        <textarea class="field-control" id="remarks" name="remarks" rows="2" maxlength="2000" placeholder="Enter notes (optional)...">{{ old('remarks') }}</textarea>
                        @error('remarks')<small class="field-error">{{ $message }}</small>@enderror
                    </div>
                </div>
            </div>

            <div class="form-section transfer-items-section">
                <div class="transfer-card-heading transfer-items-heading">
                    <span class="form-section-icon form-icon-violet"><svg><use href="#icon-box"></use></svg></span>
                    <div><h2>Transfer Items</h2><p>Add one or more products. Stock is checked for the selected source location.</p></div>
                    <button class="button button-secondary" type="button" id="add-transfer-item">＋ Add Product</button>
                </div>
                <div class="transfer-items-wrap">
                    <table class="transfer-items-table">
                        <thead>
                            <tr><th>#</th><th>Category <span class="required-mark">*</span></th><th>Product <span class="required-mark">*</span></th><th>Batch / Lot</th><th>Serial Numbers</th><th>Available (From)</th><th>Current (To)</th><th>Transfer Qty <span class="required-mark">*</span></th><th>Unit</th><th>Remarks</th><th>Action</th></tr>
                        </thead>
                        <tbody id="transfer-item-rows">
                            @php($oldItems = old('items', [[]]))
                            @foreach ($oldItems as $rowIndex => $oldItem)
                                <tr class="transfer-item-row" data-row-index="{{ $rowIndex }}">
                                    <td class="row-number">{{ $rowIndex + 1 }}</td>
                                    <td>
                                        <select class="field-control transfer-category" name="items[{{ $rowIndex }}][category_id]" required>
                                            <option value="">Select category</option>
                                            @foreach ($categories as $category)
                                                <option value="{{ $category->id }}" {{ ($oldItem['category_id'] ?? '') == $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <select class="field-control transfer-product" name="items[{{ $rowIndex }}][product_id]" required disabled>
                                            <option value="">Select category first</option>
                                            @foreach ($products as $product)
                                                <option value="{{ $product->id }}" data-category="{{ $product->category_id }}" data-sku="{{ $product->sku ?: $product->product_code }}" data-unit="{{ optional($product->unit)->short_name }}" {{ ($oldItem['product_id'] ?? '') == $product->id ? 'selected' : '' }}>{{ $product->name }}</option>
                                            @endforeach
                                        </select>
                                        <small class="transfer-product-meta"></small>
                                    </td>
                                    <td><span class="transfer-static-value">N/A</span></td>
                                    <td><span class="transfer-static-value">Not required</span></td>
                                    <td><span class="transfer-stock-value" data-field="source-stock">—</span></td>
                                    <td><span class="transfer-stock-value" data-field="destination-stock">—</span></td>
                                    <td><input class="field-control transfer-quantity" type="number" name="items[{{ $rowIndex }}][quantity]" value="{{ $oldItem['quantity'] ?? '' }}" min="0.01" step="0.01" placeholder="0.00" required><small class="transfer-quantity-max"></small></td>
                                    <td><span class="transfer-unit" data-field="unit">—</span></td>
                                    <td><input class="field-control" type="text" name="items[{{ $rowIndex }}][remarks]" value="{{ $oldItem['remarks'] ?? '' }}" maxlength="1000" placeholder="Optional"></td>
                                    <td><button class="transfer-delete-button" type="button" aria-label="Remove product row" title="Remove product row" data-delete-transfer-item><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M9 7V4h6v3m3 0-.8 13H6.8L6 7m4 4v5m4-5v5"/></svg></button></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="transfer-bottom-grid">
                <section class="transfer-summary-card">
                    <h3>Stock Preview</h3>
                    <div id="stock-preview"><p class="field-hint">Select a product and locations to preview the stock movement.</p></div>
                </section>
                <section class="transfer-summary-card">
                    <h3>Validation Status</h3>
                    <ul class="transfer-check-list" id="transfer-validation-list"><li><span>•</span><span>Waiting for transfer details.</span></li></ul>
                    <div class="transfer-validation" id="transfer-validation" role="status" aria-live="polite"><strong aria-hidden="true">•</strong><span id="transfer-validation-text">Select a product and locations to validate stock.</span></div>
                </section>
            </div>

            @if (!$products->count() || $locations->count() < 2)
                <div class="inventory-notice notice-out">
                    <span class="notice-symbol" aria-hidden="true">!</span>
                    <div><strong>Setup required</strong><small>@if (!$products->count() && $locations->count() < 2) Add an active product and two active locations before recording a transfer. @elseif (!$products->count()) Add an active product before recording a transfer. @else Add at least two active locations before recording a transfer. @endif</small></div>
                </div>
            @endif

            <div class="transfer-actions">
                <a class="button button-light" href="{{ route('stock-transfers.index') }}">Cancel</a>
                <button class="button button-primary button-complete" id="complete-transfer" type="submit" disabled><svg viewBox="0 0 24 24" aria-hidden="true" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"><path d="m5 12 4 4L19 6"/></svg> Complete Transfer</button>
            </div>
        </form>
    </section>
    </div>

    <script>
        (function () {
            var productCatalog = @json($productCatalog, 15);
            var stockBalances = @json($stockBalances);
            var rowsContainer = document.getElementById('transfer-item-rows');
            var addButton = document.getElementById('add-transfer-item');
            var sourceSelect = document.getElementById('from_location');
            var destinationSelect = document.getElementById('to_location');
            var preview = document.getElementById('stock-preview');
            var validationList = document.getElementById('transfer-validation-list');
            var validation = document.getElementById('transfer-validation');
            var validationText = document.getElementById('transfer-validation-text');
            var completeButton = document.getElementById('complete-transfer');
            var form = document.getElementById('stock-transfer-form');
            var duplicateSubmission = false;

            function escapeHtml(value) {
                return String(value).replace(/[&<>"']/g, function (character) {
                    return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'}[character];
                });
            }

            function selectedStoreId(select) {
                var option = select.options[select.selectedIndex];
                return option && option.value ? option.dataset.storeId : '';
            }

            function balance(productId, storeId) {
                var productBalances = stockBalances[productId] || {};
                var value = productBalances[storeId];
                return Number(value || 0);
            }

            function displayQuantity(value) {
                return Number(value || 0).toLocaleString('en-IN', {
                    minimumFractionDigits: Number(value) % 1 === 0 ? 0 : 2,
                    maximumFractionDigits: 2
                });
            }

            function populateProducts(row) {
                var categorySelect = row.querySelector('.transfer-category');
                var productSelect = row.querySelector('.transfer-product');
                var previousProduct = productSelect.value;
                var categoryId = categorySelect.value;
                productSelect.innerHTML = '<option value="">' + (categoryId ? 'Select product' : 'Select category first') + '</option>';
                productCatalog.forEach(function (product) {
                    if (product.category !== categoryId) {
                        return;
                    }
                    var option = document.createElement('option');
                    option.value = product.id;
                    option.textContent = product.name;
                    option.dataset.category = product.category;
                    option.dataset.sku = product.sku;
                    option.dataset.unit = product.unit || '';
                    productSelect.appendChild(option);
                });
                productSelect.disabled = !categoryId;
                if (previousProduct && productSelect.querySelector('option[value="' + previousProduct + '"]')) {
                    productSelect.value = previousProduct;
                }
            }

            function renderPreview(items) {
                if (!items.length) {
                    preview.innerHTML = '<p class="field-hint">Select a product and locations to preview the stock movement.</p>';
                    return;
                }
                preview.innerHTML = items.map(function (item) {
                    var afterSource = item.source - item.quantity;
                    var afterDestination = item.destination + item.quantity;
                    return '<div class="transfer-preview-row">' +
                        '<strong class="transfer-preview-product">' + escapeHtml(item.name) + '</strong>' +
                        '<div class="transfer-preview-location source"><strong>From: ' + escapeHtml(sourceSelect.value || '—') + '</strong><small>Current Stock <b>' + displayQuantity(item.source) + '</b><br><span class="transfer-out">Transfer Out <b>−' + displayQuantity(item.quantity) + '</b></span><br>After Transfer <b>' + displayQuantity(afterSource) + '</b></small></div>' +
                        '<span class="transfer-preview-arrow" aria-hidden="true">→</span>' +
                        '<div class="transfer-preview-location destination"><strong>To: ' + escapeHtml(destinationSelect.value || '—') + '</strong><small>Current Stock <b>' + displayQuantity(item.destination) + '</b><br><span class="transfer-in">Transfer In <b>+' + displayQuantity(item.quantity) + '</b></span><br>After Transfer <b>' + displayQuantity(afterDestination) + '</b></small></div>' +
                        '</div>';
                }).join('');
            }

            function refreshValidation() {
                var rows = Array.prototype.slice.call(rowsContainer.querySelectorAll('.transfer-item-row'));
                var sourceId = selectedStoreId(sourceSelect);
                var destinationId = selectedStoreId(destinationSelect);
                var sameLocation = Boolean(sourceId && destinationId && sourceId === destinationId);
                var selectedProducts = {};
                var items = [];
                var validationItems = [];
                var errors = [];

                rows.forEach(function (row, index) {
                    var categorySelect = row.querySelector('.transfer-category');
                    var productSelect = row.querySelector('.transfer-product');
                    var quantityInput = row.querySelector('.transfer-quantity');
                    var selectedProduct = productSelect.options[productSelect.selectedIndex];
                    var meta = row.querySelector('.transfer-product-meta');
                    var unitField = row.querySelector('[data-field="unit"]');
                    var sourceField = row.querySelector('[data-field="source-stock"]');
                    var destinationField = row.querySelector('[data-field="destination-stock"]');
                    var productId = productSelect.value;
                    var quantity = Number(quantityInput.value || 0);
                    var source = productId && sourceId ? balance(productId, sourceId) : 0;
                    var destination = productId && destinationId ? balance(productId, destinationId) : 0;
                    var maxLabel = row.querySelector('.transfer-quantity-max');
                    var rowErrors = [];

                    row.classList.remove('transfer-invalid-row');
                    if (productId) {
                        meta.textContent = (selectedProduct.dataset.sku ? 'SKU: ' + selectedProduct.dataset.sku : '') +
                            (selectedProduct.dataset.unit ? ' | Unit: ' + selectedProduct.dataset.unit : '');
                        unitField.textContent = selectedProduct.dataset.unit || '—';
                        sourceField.innerHTML = '<strong>' + displayQuantity(source) + '</strong><small>Available</small>';
                        destinationField.innerHTML = '<strong>' + displayQuantity(destination) + '</strong><small>At destination</small>';
                        maxLabel.textContent = sourceId ? 'Max: ' + displayQuantity(source) : 'Select source';
                        if (selectedProducts[productId]) {
                            rowErrors.push('Duplicate product row');
                        }
                        selectedProducts[productId] = true;
                        if (!sourceId || !destinationId) {
                            rowErrors.push('Select both locations');
                        } else if (!sameLocation && source <= 0) {
                            rowErrors.push('No stock at source location');
                        }
                        if (quantity <= 0) {
                            rowErrors.push('Enter a transfer quantity');
                        } else if (quantity > source) {
                            rowErrors.push('Available: ' + displayQuantity(source) + ', requested: ' + displayQuantity(quantity) + ', shortage: ' + displayQuantity(quantity - source));
                        }
                        if (sameLocation) {
                            rowErrors.push('From and To locations must be different');
                        }
                        items.push({name: selectedProduct.textContent, source: source, destination: destination, quantity: quantity});
                        validationItems.push({
                            name: selectedProduct.textContent,
                            available: source,
                            requested: quantity,
                            remaining: source - quantity,
                            shortage: Math.max(quantity - source, 0),
                            valid: rowErrors.length === 0,
                            locationsSelected: Boolean(sourceId && destinationId)
                        });
                    } else {
                        meta.textContent = '';
                        unitField.textContent = '—';
                        sourceField.textContent = '—';
                        destinationField.textContent = '—';
                        maxLabel.textContent = '';
                        if (categorySelect.value) {
                            rowErrors.push('Select a product');
                        }
                    }

                    if (rowErrors.length) {
                        row.classList.add('transfer-invalid-row');
                        row.querySelectorAll('[data-field="source-stock"], [data-field="destination-stock"]').forEach(function (field) {
                            field.classList.add('is-invalid');
                        });
                        row.querySelectorAll('.transfer-stock-value').forEach(function (field) {
                            field.classList.add('is-invalid');
                        });
                        errors.push('Item ' + (index + 1) + ': ' + rowErrors.join(', '));
                    } else {
                        row.querySelectorAll('.transfer-stock-value').forEach(function (field) {
                            field.classList.remove('is-invalid');
                        });
                    }
                });

                renderPreview(items);
                validationList.innerHTML = validationItems.length
                    ? validationItems.map(function (item) {
                        var detail = item.locationsSelected
                            ? 'Available: ' + displayQuantity(item.available) + ' | Requested: ' + displayQuantity(item.requested) +
                                (item.valid ? ' | Remaining: ' + displayQuantity(item.remaining) : ' | Shortage: ' + displayQuantity(item.shortage))
                            : 'Select source and destination locations to validate stock.';
                        var status = item.valid ? 'Valid' : 'Invalid';
                        var statusClass = item.valid ? '' : ' is-invalid';
                        return '<li><span class="' + (item.valid ? 'check-valid' : 'check-invalid') + '">' + (item.valid ? '✓' : '✕') + '</span>' +
                            '<span><strong>' + escapeHtml(item.name) + '</strong><small class="transfer-check-detail">' + escapeHtml(detail) + '</small></span>' +
                            '<span class="transfer-check-status' + statusClass + '">' + status + '</span></li>';
                    }).join('')
                    : '<li><span class="check-valid">•</span><span>Select a product to see stock validation.</span></li>';

                var rowsComplete = rows.length > 0 && rows.every(function (row) {
                    return Boolean(row.querySelector('.transfer-category').value
                        && row.querySelector('.transfer-product').value
                        && Number(row.querySelector('.transfer-quantity').value || 0) > 0);
                });
                var reasonSelected = Boolean(document.getElementById('transfer_reason').value);
                var dateSelected = Boolean(document.getElementById('transfer_date').value);
                var allValid = validationItems.length > 0 && validationItems.every(function (item) { return item.valid; });
                var requiredDetailsSelected = Boolean(sourceId && destinationId && !sameLocation && reasonSelected && dateSelected);
                var waiting = !items.length || !sourceId || !destinationId || !reasonSelected || !dateSelected;

                validation.classList.toggle('is-invalid', errors.length > 0);
                validation.classList.toggle('is-valid', allValid && requiredDetailsSelected && rowsComplete);
                if (errors.length > 0) {
                    validationText.textContent = sameLocation
                        ? 'From Location and To Location must be different.'
                        : errors[0].replace(/^Item \d+: /, '');
                } else if (allValid && requiredDetailsSelected && rowsComplete) {
                    validationText.textContent = 'All products have sufficient stock for transfer.';
                } else if (waiting) {
                    validationText.textContent = 'Select locations and products to validate stock.';
                } else {
                    validationText.textContent = 'Complete each product row to enable transfer.';
                }

                completeButton.disabled = Boolean(errors.length || !allValid || !rowsComplete
                    || !requiredDetailsSelected || duplicateSubmission);
            }

            function renumberRows() {
                Array.prototype.forEach.call(rowsContainer.querySelectorAll('.transfer-item-row'), function (row, index) {
                    row.querySelector('.row-number').textContent = index + 1;
                    row.dataset.rowIndex = index;
                    row.querySelectorAll('[name]').forEach(function (field) {
                        field.name = field.name.replace(/items\[\d+\]/, 'items[' + index + ']');
                    });
                });
            }

            function addRow() {
                var rowIndex = rowsContainer.querySelectorAll('.transfer-item-row').length;
                var categories = @json($categoryOptions, 15);
                var categoryOptions = categories.map(function (category) {
                    return '<option value="' + escapeHtml(category.id) + '">' + escapeHtml(category.name) + '</option>';
                }).join('');
                var row = document.createElement('tr');
                row.className = 'transfer-item-row';
                row.dataset.rowIndex = rowIndex;
                row.innerHTML = '<td class="row-number">' + (rowIndex + 1) + '</td>' +
                    '<td><select class="field-control transfer-category" name="items[' + rowIndex + '][category_id]" required><option value="">Select category</option>' + categoryOptions + '</select></td>' +
                    '<td><select class="field-control transfer-product" name="items[' + rowIndex + '][product_id]" required disabled><option value="">Select category first</option></select><small class="transfer-product-meta"></small></td>' +
                    '<td><span class="transfer-static-value">N/A</span></td><td><span class="transfer-static-value">Not required</span></td>' +
                    '<td><span class="transfer-stock-value" data-field="source-stock">—</span></td><td><span class="transfer-stock-value" data-field="destination-stock">—</span></td>' +
                    '<td><input class="field-control transfer-quantity" type="number" name="items[' + rowIndex + '][quantity]" min="0.01" step="0.01" placeholder="0.00" required><small class="transfer-quantity-max"></small></td>' +
                    '<td><span class="transfer-unit" data-field="unit">—</span></td>' +
                    '<td><input class="field-control" type="text" name="items[' + rowIndex + '][remarks]" maxlength="1000" placeholder="Optional"></td>' +
                    '<td><button class="transfer-delete-button" type="button" aria-label="Remove product row" title="Remove product row" data-delete-transfer-item><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M9 7V4h6v3m3 0-.8 13H6.8L6 7m4 4v5m4-5v5"/></svg></button></td>';
                rowsContainer.appendChild(row);
                bindRow(row);
                refreshValidation();
            }

            function bindRow(row) {
                row.querySelector('.transfer-category').addEventListener('change', function () {
                    populateProducts(row);
                    refreshValidation();
                });
                row.querySelector('.transfer-product').addEventListener('change', refreshValidation);
                row.querySelector('.transfer-quantity').addEventListener('input', refreshValidation);
            }

            rowsContainer.addEventListener('click', function (event) {
                var button = event.target.closest('[data-delete-transfer-item]');
                if (!button) {
                    return;
                }
                var rows = rowsContainer.querySelectorAll('.transfer-item-row');
                if (rows.length === 1) {
                    var row = rows[0];
                    row.querySelector('.transfer-category').value = '';
                    populateProducts(row);
                    row.querySelector('.transfer-quantity').value = '';
                    row.querySelector('input[type="text"]').value = '';
                } else {
                    button.closest('.transfer-item-row').remove();
                    renumberRows();
                }
                refreshValidation();
            });

            Array.prototype.forEach.call(rowsContainer.querySelectorAll('.transfer-item-row'), function (row) {
                bindRow(row);
                populateProducts(row);
            });
            sourceSelect.addEventListener('change', refreshValidation);
            destinationSelect.addEventListener('change', refreshValidation);
            document.getElementById('transfer_reason').addEventListener('change', refreshValidation);
            document.getElementById('transfer_date').addEventListener('change', refreshValidation);
            addButton.addEventListener('click', addRow);
            form.addEventListener('submit', function (event) {
                refreshValidation();
                if (completeButton.disabled) {
                    event.preventDefault();
                    return;
                }
                duplicateSubmission = true;
                completeButton.disabled = true;
                completeButton.textContent = 'Completing…';
            });
            refreshValidation();
        }());
    </script>
@endsection
