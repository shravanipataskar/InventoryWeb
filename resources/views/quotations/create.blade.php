@extends('layouts.app')

@section('title', 'Create Quotation Request')
@section('topbar-title', 'Create quotation request')

@section('content')
    @php
        $selectedSupplierIds = array_map('strval', (array) old('supplier_ids', []));
        $quotationItems = old('items', [['category_id' => '', 'product_id' => '', 'quantity' => '']]);
    @endphp
    <style>
        .quotation-create-page{max-width:1280px;margin:0 auto;padding-bottom:24px}
        .quotation-create-heading{display:flex;align-items:flex-end;justify-content:space-between;gap:18px;margin-bottom:15px}
        .quotation-create-heading h1{margin:3px 0 4px;color:#142b4e;font-size:24px}
        .quotation-create-heading p{margin:0;color:#687b92;font-size:12px}
        .quotation-create-form{padding:0 0 74px}
        .quotation-create-card{margin-bottom:12px;padding:14px 16px;border:1px solid #e7edf3;border-radius:10px;background:#fff;box-shadow:0 2px 10px rgba(31,55,84,.035)}
        .quotation-create-card-heading{display:flex;align-items:center;gap:9px;margin-bottom:12px}
        .quotation-create-card-heading h2{margin:0;color:#17375f;font-size:13px}
        .quotation-create-card-heading p{margin:3px 0 0;color:#7b8a9b;font-size:10px}
        .quotation-create-icon{display:grid;width:29px;height:29px;flex:0 0 29px;place-items:center;border-radius:8px;background:#e7f7f4;color:#128b87}
        .quotation-create-icon.blue{background:#eaf3ff;color:#287ed1}
        .quotation-create-icon svg{width:16px;height:16px;fill:none;stroke:currentColor;stroke-linecap:round;stroke-linejoin:round;stroke-width:1.8}
        .quotation-request-fields{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px 16px}
        .quotation-request-fields .field{min-width:0}
        .quotation-request-fields .field-wide{grid-column:1/-1}
        .quotation-create-page .field>label{display:block;margin-bottom:5px;color:#334960;font-size:10px;font-weight:600}
        .quotation-create-page .field-control{min-height:34px;border-color:#dfe6ed;border-radius:6px;font-size:11px}
        .quotation-create-page textarea.field-control{min-height:56px;resize:vertical}
        .quotation-items-table-wrap{overflow-x:auto}
        .quotation-items-table{width:100%;min-width:750px;border-collapse:collapse}
        .quotation-items-table th{padding:8px 7px;background:#f7f9fc;color:#718198;font-size:8px;font-weight:700;text-align:left;white-space:nowrap}
        .quotation-items-table td{padding:5px 6px;border-bottom:1px solid #edf1f5;vertical-align:middle}
        .quotation-items-table tr:last-child td{border-bottom:0}
        .quotation-items-table .field-control{width:100%;min-width:0;height:33px;padding:0 8px}
        .quotation-item-number{width:33px;color:#53677d;font-size:10px;text-align:center}
        .quotation-item-unit{background:#f3f6f9!important;color:#67788c}
        .quotation-item-actions{width:48px;text-align:center}
        .quotation-item-remove{display:inline-grid;width:30px;height:30px;place-items:center;border:1px solid #f4d4d2;border-radius:6px;background:#fff;color:#c74b43;cursor:pointer}
        .quotation-item-remove:hover{background:#fff1f0}
        .quotation-item-remove svg{width:14px;height:14px;fill:none;stroke:currentColor;stroke-linecap:round;stroke-linejoin:round;stroke-width:1.8}
        .quotation-items-footer{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-top:9px}
        .quotation-items-footer small{color:#7b8a9b;font-size:10px}
        .quotation-supplier-picker{position:relative;max-width:100%}
        .quotation-supplier-picker-label{display:block;margin-bottom:3px;color:#243746;font-size:10px;font-weight:600}
        .quotation-supplier-helper{display:block;margin-bottom:8px;color:#748399;font-size:10px}
        .quotation-supplier-trigger{display:flex;width:100%;min-height:38px;align-items:center;gap:8px;padding:5px 9px;border:1px solid #dce3ea;border-radius:7px;background:#fff;color:#26384b;text-align:left;cursor:pointer;transition:border-color .15s ease,box-shadow .15s ease}
        .quotation-supplier-trigger:hover,.quotation-supplier-picker.is-open .quotation-supplier-trigger{border-color:#8baeb5}
        .quotation-supplier-trigger:focus-visible{outline:0;border-color:#159293;box-shadow:0 0 0 3px rgba(21,146,147,.14)}
        .quotation-supplier-placeholder{flex:1;color:#8592a2;font-size:11px}
        .quotation-supplier-chips{display:flex;min-width:0;flex:1;align-items:center;gap:5px;overflow:hidden}
        .quotation-supplier-chip{display:inline-flex;max-width:190px;align-items:center;gap:5px;padding:4px 7px;border:1px solid #d9e4f6;border-radius:6px;background:#eef4ff;color:#29466d;font-size:9px;white-space:nowrap}
        .quotation-supplier-chip span{overflow:hidden;text-overflow:ellipsis}
        .quotation-supplier-chip button{display:grid;width:14px;height:14px;flex:0 0 14px;place-items:center;padding:0;border:0;border-radius:50%;background:transparent;color:#58708e;font-size:13px;line-height:1;cursor:pointer}
        .quotation-supplier-chip button:hover{background:#dfe9f8;color:#123e42}
        .quotation-supplier-selected-count{flex:0 0 auto;color:#66788c;font-size:9px;white-space:nowrap}
        .quotation-supplier-chevron{margin-left:2px;color:#718198;font-size:10px;transition:transform .15s ease}
        .quotation-supplier-picker.is-open .quotation-supplier-chevron{transform:rotate(180deg)}
        .quotation-supplier-menu{position:relative;z-index:5;margin-top:4px;overflow:hidden;border:1px solid #e1e7ed;border-radius:7px;background:#fff;box-shadow:0 5px 14px rgba(25,43,54,.08)}
        .quotation-supplier-menu[hidden]{display:none}
        .quotation-supplier-search-wrap{position:relative;padding:6px;border-bottom:1px solid #edf1f5;background:#fbfcfd}
        .quotation-supplier-search-wrap:before{position:absolute;top:13px;left:16px;color:#8592a2;content:"⌕";font-size:16px;line-height:1}
        .quotation-supplier-search{width:100%;height:28px;padding:0 9px 0 27px;border:1px solid #e0e6ed;border-radius:5px;background:#fff;color:#26384b;font:inherit;font-size:10px}
        .quotation-supplier-search:focus{outline:0;border-color:#159293;box-shadow:0 0 0 2px rgba(21,146,147,.1)}
        .quotation-supplier-options{max-height:166px;overflow-y:auto;padding:3px}
        .quotation-supplier-option{display:flex;align-items:flex-start;gap:8px;padding:6px 7px;border-radius:5px;cursor:pointer}
        .quotation-supplier-option:hover{background:#f5f8fb}
        .quotation-supplier-option[hidden]{display:none}
        .quotation-supplier-option input{width:14px;height:14px;flex:0 0 14px;margin:2px 0 0;accent-color:#138b8b}
        .quotation-supplier-option-copy{display:block;min-width:0;flex:1}
        .quotation-supplier-option-name{display:block;overflow:hidden;color:#20364d;font-size:10px;font-weight:600;text-overflow:ellipsis;white-space:nowrap}
        .quotation-supplier-option-meta{display:block;margin-top:2px;color:#77869a;font-size:9px}
        .quotation-supplier-active{align-self:center;padding:3px 7px;border-radius:5px;background:#e6f8ef;color:#16804a;font-size:8px;font-weight:600}
        .quotation-supplier-empty{padding:11px 8px;color:#7b899b;font-size:10px;text-align:center}
        .quotation-supplier-error{display:block;margin-top:5px;color:#b42318;font-size:10px}
        .quotation-form-actions{position:sticky;z-index:10;bottom:0;display:flex;align-items:center;justify-content:space-between;gap:12px;margin:0 -16px;padding:10px 16px;border-top:1px solid #e7edf3;background:rgba(255,255,255,.97);box-shadow:0 -4px 14px rgba(31,55,84,.06)}
        .quotation-form-actions-left{color:#748399;font-size:10px}
        .quotation-form-actions-buttons{display:flex;align-items:center;gap:8px}
        @media(max-width:850px){.quotation-request-fields{grid-template-columns:repeat(2,minmax(0,1fr))}.quotation-request-fields .field-wide{grid-column:1/-1}}
        @media(max-width:600px){.quotation-create-heading{align-items:flex-start;flex-direction:column}.quotation-create-heading h1{font-size:20px}.quotation-create-card{padding:12px}.quotation-request-fields{grid-template-columns:1fr;gap:10px}.quotation-request-fields .field-wide{grid-column:auto}.quotation-form-actions{margin:0 -12px;padding:9px 12px}.quotation-form-actions-left{display:none}.quotation-form-actions-buttons{width:100%;justify-content:flex-end}.quotation-supplier-chip{max-width:120px}.quotation-supplier-selected-count{font-size:8px}}
    </style>

    <div class="quotation-create-page">
        <div class="quotation-create-heading">
            <div>
                <span class="section-kicker">PURCHASING / QUOTATIONS</span>
                <h1>Create Quotation Request</h1>
                <p>Select required products and multiple active suppliers.</p>
            </div>
            <a class="button button-light" href="{{ route('quotations.index') }}"><span aria-hidden="true">←</span> Back to quotations</a>
        </div>

        @if ($errors->any())
            <div class="form-alert" role="alert"><strong>Please check the form:</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif

        <form class="quotation-create-form" method="POST" action="{{ route('quotations.store') }}" data-quotation-form>
            @csrf
            <section class="quotation-create-card">
                <div class="quotation-create-card-heading"><span class="quotation-create-icon"><svg viewBox="0 0 24 24"><rect x="4" y="5" width="16" height="16" rx="2"/><path d="M8 3v4m8-4v4M4 10h16m-11 4h6m-6 3h4"/></svg></span><div><h2>Request Information</h2><p>Enter the basic details for this quotation request.</p></div></div>
                <div class="quotation-request-fields">
                    <div class="field">
                        <label for="request_date">Request Date <span class="required-mark">*</span></label>
                        <input class="field-control" type="date" id="request_date" name="request_date" value="{{ old('request_date', now()->toDateString()) }}" required>
                        @error('request_date')<small class="field-error">{{ $message }}</small>@enderror
                    </div>
                    <div class="field">
                        <label for="required_date">Required Date</label>
                        <input class="field-control" type="date" id="required_date" name="required_date" value="{{ old('required_date') }}">
                        @error('required_date')<small class="field-error">{{ $message }}</small>@enderror
                    </div>
                    <div class="field">
                        <label for="store_id">Receive Into Store / Warehouse <span class="required-mark">*</span></label>
                        <select class="field-control" id="store_id" name="store_id" required>
                            <option value="">Select store</option>
                            @foreach ($stores as $store)
                                <option value="{{ $store->id }}" {{ old('store_id') == $store->id ? 'selected' : '' }}>{{ $store->name }}</option>
                            @endforeach
                        </select>
                        @error('store_id')<small class="field-error">{{ $message }}</small>@enderror
                    </div>
                    <div class="field field-wide">
                        <label for="remarks">Remarks</label>
                        <textarea class="field-control" id="remarks" name="remarks" rows="2" maxlength="4000" placeholder="Add any additional notes or remarks (optional)...">{{ old('remarks') }}</textarea>
                        @error('remarks')<small class="field-error">{{ $message }}</small>@enderror
                    </div>
                </div>
            </section>

            <section class="quotation-create-card">
                <div class="quotation-create-card-heading"><span class="quotation-create-icon"><svg viewBox="0 0 24 24"><path d="m12 3 9 5-9 5-9-5 9-5ZM3 8v9l9 5 9-5V8M12 13v9"/></svg></span><div><h2>Required Products</h2><p>Add the products you want to request quotations for.</p></div></div>
                <div class="quotation-items-table-wrap">
                    <table class="quotation-items-table">
                        <thead><tr><th>#</th><th>CATEGORY <span class="required-mark">*</span></th><th>PRODUCT <span class="required-mark">*</span></th><th>UNIT</th><th>QUANTITY <span class="required-mark">*</span></th><th class="quotation-item-actions">ACTIONS</th></tr></thead>
                        <tbody id="quotation-items">
                        @foreach ($quotationItems as $index => $quotationItem)
                            @php $selectedProduct = $products->firstWhere('id', (int) ($quotationItem['product_id'] ?? 0)); @endphp
                            <tr class="quotation-item-row">
                                <td class="quotation-item-number">{{ $loop->iteration }}</td>
                                <td>
                                    <select class="field-control category-select" name="items[{{ $index }}][category_id]" required>
                                        <option value="">Select category</option>
                                        @foreach ($categories as $category)
                                            <option value="{{ $category->id }}" {{ (string) ($quotationItem['category_id'] ?? '') === (string) $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    <select class="field-control product-select" name="items[{{ $index }}][product_id]" required>
                                        <option value="">Select product</option>
                                        @foreach ($products as $product)
                                            <option value="{{ $product->id }}" data-category="{{ $product->category_id }}" data-unit="{{ optional($product->unit)->short_name ?: optional($product->unit)->name ?: '—' }}" {{ (string) ($quotationItem['product_id'] ?? '') === (string) $product->id ? 'selected' : '' }}>{{ $product->name }} ({{ $product->product_code }})</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td><input class="field-control quotation-item-unit" type="text" value="{{ optional(optional($selectedProduct)->unit)->short_name ?: optional(optional($selectedProduct)->unit)->name ?: '—' }}" readonly aria-label="Product unit"></td>
                                <td><input class="field-control" type="number" step="0.01" min="0.01" name="items[{{ $index }}][quantity]" value="{{ $quotationItem['quantity'] ?? '' }}" placeholder="0.00" required></td>
                                <td class="quotation-item-actions">
                                    <button class="quotation-item-remove" type="button" data-remove-item aria-label="Remove product row" title="Remove product row">
                                        <svg viewBox="0 0 24 24"><path d="M4 7h16m-10 4v6m4-6v6M6 7l1 14h10l1-14M9 7V4h6v3"/></svg>
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="quotation-items-footer"><button class="button button-light button-small" type="button" id="add-quotation-item"><span class="button-plus">+</span> Add Product</button><small>At least one product is required.</small></div>
                @error('items')<small class="field-error">{{ $message }}</small>@enderror
                @error('items.*.category_id')<small class="field-error">{{ $message }}</small>@enderror
                @error('items.*.product_id')<small class="field-error">{{ $message }}</small>@enderror
                @error('items.*.quantity')<small class="field-error">{{ $message }}</small>@enderror
            </section>

            <section class="quotation-create-card">
                <div class="quotation-create-card-heading"><span class="quotation-create-icon blue"><svg viewBox="0 0 24 24"><circle cx="9" cy="8" r="3"/><circle cx="17" cy="9" r="2.5"/><path d="M3 20v-2a6 6 0 0 1 12 0v2m1-5a5 5 0 0 1 5 5"/></svg></span><div><h2>Suppliers <span class="required-mark">*</span></h2><p>Select all suppliers who should submit a quotation.</p></div></div>
                <div class="quotation-supplier-picker" data-supplier-picker>
                    <label class="quotation-supplier-picker-label" for="supplier-picker-trigger">Suppliers <span class="required-mark">*</span></label>
                    <small class="quotation-supplier-helper">Select all suppliers who should submit a quotation.</small>
                    <button class="quotation-supplier-trigger" id="supplier-picker-trigger" type="button" aria-haspopup="dialog" aria-expanded="false" aria-controls="supplier-picker-menu" data-supplier-trigger>
                        <span class="quotation-supplier-chips" data-supplier-chips></span>
                        <span class="quotation-supplier-placeholder" data-supplier-placeholder>Select suppliers...</span>
                        <span class="quotation-supplier-selected-count" data-supplier-count></span>
                        <span class="quotation-supplier-chevron" aria-hidden="true">▼</span>
                    </button>
                    <div class="quotation-supplier-menu" id="supplier-picker-menu" role="dialog" aria-label="Select quotation suppliers" data-supplier-menu hidden>
                        <div class="quotation-supplier-search-wrap"><input class="quotation-supplier-search" type="search" placeholder="Search supplier..." aria-label="Search supplier" autocomplete="off" data-supplier-search></div>
                        <div class="quotation-supplier-options" role="group" aria-label="Active suppliers" data-supplier-options>
                            @forelse ($suppliers as $supplier)
                                <label class="quotation-supplier-option" data-supplier-option data-search="{{ strtolower(($supplier->company_name ?: $supplier->name) . ' ' . $supplier->name . ' ' . $supplier->supplier_code . ' ' . $supplier->phone) }}">
                                    <input type="checkbox" name="supplier_ids[]" value="{{ $supplier->id }}" data-supplier-id="{{ $supplier->id }}" data-supplier-name="{{ $supplier->company_name ?: $supplier->name }}" {{ in_array((string) $supplier->id, $selectedSupplierIds, true) ? 'checked' : '' }}>
                                    <span class="quotation-supplier-option-copy"><span class="quotation-supplier-option-name">{{ $supplier->company_name ?: $supplier->name }}</span><span class="quotation-supplier-option-meta">{{ $supplier->supplier_code ?: 'No code' }} · {{ $supplier->phone ?: 'No phone' }}</span></span>
                                    <span class="quotation-supplier-active">Active</span>
                                </label>
                            @empty
                                <div class="quotation-supplier-empty">No active suppliers are available.</div>
                            @endforelse
                            <div class="quotation-supplier-empty" data-supplier-no-results hidden>No suppliers match your search.</div>
                        </div>
                    </div>
                    @error('supplier_ids')<small class="quotation-supplier-error">{{ $message }}</small>@enderror
                    @error('supplier_ids.*')<small class="quotation-supplier-error">{{ $message }}</small>@enderror
                </div>
            </section>

            <div class="quotation-form-actions">
                <span class="quotation-form-actions-left">Review the request details before inviting suppliers.</span>
                <div class="quotation-form-actions-buttons"><a class="button button-light" href="{{ route('quotations.index') }}">Cancel</a><button class="button button-primary" type="submit" data-quotation-submit>Continue to Supplier Quotations <span aria-hidden="true">→</span></button></div>
            </div>
        </form>
    </div>

    <script>
        (function () {
            var picker = document.querySelector('[data-supplier-picker]');
            if (!picker) return;
            var trigger = picker.querySelector('[data-supplier-trigger]');
            var menu = picker.querySelector('[data-supplier-menu]');
            var search = picker.querySelector('[data-supplier-search]');
            var chips = picker.querySelector('[data-supplier-chips]');
            var placeholder = picker.querySelector('[data-supplier-placeholder]');
            var count = picker.querySelector('[data-supplier-count]');
            var options = Array.prototype.slice.call(picker.querySelectorAll('[data-supplier-option]'));
            var noResults = picker.querySelector('[data-supplier-no-results]');

            function selectedControls() {
                return options.map(function (option) {
                    return option.querySelector('[data-supplier-id]');
                }).filter(function (control) {
                    return control.checked;
                });
            }

            function renderSelection() {
                var selected = selectedControls();
                chips.textContent = '';
                placeholder.hidden = selected.length > 0;
                count.textContent = selected.length ? selected.length + (selected.length === 1 ? ' supplier selected' : ' suppliers selected') : '';
                selected.slice(0, 3).forEach(function (control) {
                    var chip = document.createElement('span');
                    chip.className = 'quotation-supplier-chip';
                    var name = document.createElement('span');
                    name.textContent = control.getAttribute('data-supplier-name');
                    var remove = document.createElement('button');
                    remove.type = 'button';
                    remove.textContent = '×';
                    remove.setAttribute('aria-label', 'Remove ' + control.getAttribute('data-supplier-name'));
                    remove.addEventListener('click', function (event) {
                        event.stopPropagation();
                        control.checked = false;
                        renderSelection();
                    });
                    chip.appendChild(name);
                    chip.appendChild(remove);
                    chips.appendChild(chip);
                });
            }

            function closeMenu() {
                picker.classList.remove('is-open');
                menu.hidden = true;
                trigger.setAttribute('aria-expanded', 'false');
            }

            trigger.addEventListener('click', function () {
                var opening = menu.hidden;
                menu.hidden = !opening;
                picker.classList.toggle('is-open', opening);
                trigger.setAttribute('aria-expanded', opening ? 'true' : 'false');
                if (opening) search.focus();
            });

            picker.addEventListener('change', function (event) {
                if (event.target.matches('[data-supplier-id]')) renderSelection();
            });

            search.addEventListener('input', function () {
                var term = search.value.trim().toLowerCase();
                var visibleCount = 0;
                options.forEach(function (option) {
                    var matches = option.getAttribute('data-search').indexOf(term) !== -1;
                    option.hidden = !matches;
                    if (matches) visibleCount++;
                });
                noResults.hidden = visibleCount > 0 || options.length === 0;
            });

            document.addEventListener('click', function (event) {
                if (!picker.contains(event.target)) closeMenu();
            });

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && !menu.hidden) {
                    closeMenu();
                    trigger.focus();
                }
            });

            var quotationForm = document.querySelector('[data-quotation-form]');
            quotationForm.addEventListener('submit', function (event) {
                if (selectedControls().length === 0) {
                    event.preventDefault();
                    var error = picker.querySelector('.quotation-supplier-error');
                    if (!error) {
                        error = document.createElement('small');
                        error.className = 'quotation-supplier-error';
                        error.textContent = 'Please select at least one supplier.';
                        picker.appendChild(error);
                    }
                    menu.hidden = false;
                    picker.classList.add('is-open');
                    trigger.setAttribute('aria-expanded', 'true');
                    search.focus();
                    return;
                }
                var submitButton = quotationForm.querySelector('[data-quotation-submit]');
                submitButton.disabled = true;
                submitButton.textContent = 'Creating request...';
            });

            renderSelection();
        }());

        (function () {
            var items = document.getElementById('quotation-items');
            var addButton = document.getElementById('add-quotation-item');
            var index = Array.prototype.reduce.call(items.querySelectorAll('.quotation-item-row [name^="items["]'), function (highest, control) {
                var match = control.name.match(/^items\[(\d+)\]/);
                return match ? Math.max(highest, parseInt(match[1], 10) + 1) : highest;
            }, 0);

            function updateRowNumber() {
                Array.prototype.forEach.call(items.querySelectorAll('.quotation-item-row'), function (row, rowIndex) {
                    row.querySelector('.quotation-item-number').textContent = rowIndex + 1;
                });
            }

            function filterProducts(row) {
                var category = row.querySelector('.category-select').value;
                var productSelect = row.querySelector('.product-select');
                Array.prototype.forEach.call(productSelect.querySelectorAll('option[data-category]'), function (option) {
                    option.hidden = category && option.getAttribute('data-category') !== category;
                    if (option.hidden && option.selected) option.selected = false;
                });
                updateUnit(row);
            }

            function updateUnit(row) {
                var productSelect = row.querySelector('.product-select');
                var selectedOption = productSelect.options[productSelect.selectedIndex];
                row.querySelector('.quotation-item-unit').value = selectedOption && selectedOption.getAttribute('data-unit')
                    ? selectedOption.getAttribute('data-unit')
                    : '—';
            }

            items.addEventListener('change', function (event) {
                var row = event.target.closest('.quotation-item-row');
                if (!row) return;
                if (event.target.classList.contains('category-select')) filterProducts(row);
                if (event.target.classList.contains('product-select')) updateUnit(row);
            });

            items.addEventListener('click', function (event) {
                var removeButton = event.target.closest('[data-remove-item]');
                if (!removeButton) return;
                if (items.querySelectorAll('.quotation-item-row').length === 1) {
                    var row = removeButton.closest('.quotation-item-row');
                    row.querySelectorAll('select').forEach(function (select) { select.selectedIndex = 0; });
                    row.querySelector('input[type="number"]').value = '';
                    row.querySelector('.quotation-item-unit').value = '—';
                    return;
                }
                removeButton.closest('.quotation-item-row').remove();
                updateRowNumber();
            });

            addButton.addEventListener('click', function () {
                var row = items.querySelector('.quotation-item-row').cloneNode(true);
                row.querySelectorAll('select, input').forEach(function (control) {
                    if (control.name) control.name = control.name.replace(/^items\[\d+\]/, 'items[' + index + ']');
                    if (control.tagName === 'SELECT') control.selectedIndex = 0;
                    else control.value = '';
                });
                row.querySelectorAll('.product-select option').forEach(function (option) { option.hidden = false; });
                row.querySelector('.quotation-item-unit').value = '—';
                items.appendChild(row);
                index++;
                updateRowNumber();
            });

            Array.prototype.forEach.call(items.querySelectorAll('.quotation-item-row'), function (row) {
                filterProducts(row);
            });
            updateRowNumber();
        }());
    </script>
@endsection
