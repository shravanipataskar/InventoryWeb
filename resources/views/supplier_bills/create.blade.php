@extends('layouts.app')

@php
    $editing = isset($bill);
    $existingLines = $editing ? $bill->items->keyBy('purchase_order_item_id') : collect();
    $fmt = function ($minor) { return \App\Support\Money::fromMinor($minor); };
@endphp

@section('title', $editing ? 'Correct ' . $bill->bill_number : 'Enter Supplier Bill')
@section('topbar-title', 'Supplier bill')

@section('content')
    <div class="page-heading">
        <div><span class="section-kicker">SUPPLIER BILLS / {{ $purchaseOrder->po_number }}</span><h1>{{ $editing ? 'Correct ' . $bill->bill_number : 'Enter Supplier Bill' }}</h1><p>{{ $purchaseOrder->supplier->name }} · Bill lines are checked against the PO rates/taxes and the accepted goods received.</p></div>
        <a class="button button-light" href="{{ $editing ? route('supplier-bills.show', $bill->id) : route('purchase-orders.show', $purchaseOrder->id) }}">Back</a>
    </div>
    @if ($errors->any())
        <div class="form-alert" role="alert"><strong>Please check the form:</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    <section class="form-card">
        <form action="{{ $editing ? route('supplier-bills.update', $bill->id) : route('supplier-bills.store', $purchaseOrder->id) }}" method="POST" enctype="multipart/form-data" data-submit-once>
            @csrf
            @if ($editing) @method('PUT') @else <input type="hidden" name="submission_token" value="{{ old('submission_token', (string) \Illuminate\Support\Str::uuid()) }}"> @endif
            <div class="form-section">
                <div class="form-grid">
                    <div class="field"><label for="invoice_number">Supplier invoice number <span class="required-mark">*</span></label><input class="field-control" id="invoice_number" name="invoice_number" type="text" maxlength="100" value="{{ old('invoice_number', $editing ? $bill->invoice_number : optional($purchaseOrder->goodsReceipts->whereNotNull('invoice_number')->last())->invoice_number) }}" required>@error('invoice_number')<small class="field-error">{{ $message }}</small>@enderror</div>
                    <div class="field"><label for="invoice_date">Invoice date <span class="required-mark">*</span></label><input class="field-control" id="invoice_date" name="invoice_date" type="date" max="{{ date('Y-m-d') }}" value="{{ old('invoice_date', $editing ? $bill->invoice_date : date('Y-m-d')) }}" required></div>
                    <div class="field"><label for="due_date">Due date</label><input class="field-control" id="due_date" name="due_date" type="date" value="{{ old('due_date', $editing ? $bill->due_date : '') }}"></div>
                    <div class="field"><label for="invoice_total">Invoice grand total (₹) <span class="required-mark">*</span></label><input class="field-control" id="invoice_total" name="invoice_total" type="number" min="0" step="0.01" value="{{ old('invoice_total', $editing ? $bill->invoice_total : '') }}" required><small class="field-hint">Auto-filled from the bill lines. Compare it with the supplier invoice and edit it if the printed total differs.</small></div>
                    <div class="field"><label for="attachment">Invoice document (PDF/JPG/PNG/WEBP, max 10 MB)</label><input class="field-control" id="attachment" name="attachment" type="file" accept=".pdf,.jpg,.jpeg,.png,.webp">@if ($editing && $bill->attachment_name)<small class="field-hint">Current: {{ $bill->attachment_name }} (uploading replaces it)</small>@endif</div>
                </div>
            </div>
            <div class="form-section">
                <div class="form-card-heading"><span class="form-section-icon form-icon-gold"><svg><use href="#icon-tray-in"></use></svg></span><div><h2>Billed items</h2><p>Enter what the supplier invoiced. Leave quantity at 0 for items not on this invoice.</p></div></div>
                <div class="table-wrap">
                    <table class="data-table listing-table">
                        <thead><tr><th>PRODUCT</th><th>ORDERED</th><th>ACCEPTED (GRN)</th><th>ALREADY BILLED</th><th>PO RATE / TAX</th><th>BILLED QTY</th><th>RATE (₹)</th><th>DISC %</th><th>TAX %</th><th>LINE TOTAL</th></tr></thead>
                        <tbody>
                        @foreach ($purchaseOrder->items as $item)
                            @php
                                $position = $positions[$item->id];
                                $line = $existingLines->get($item->id);
                                $defaultQty = $line ? $line->quantity : $fmt($position['billable']);
                            @endphp
                            <tr class="bill-line">
                                <td><strong>{{ $item->product->name }}</strong><small class="field-hint">{{ $item->product->product_code }}</small></td>
                                <td>{{ $fmt($position['ordered']) }}</td>
                                <td>{{ $fmt($position['accepted']) }}</td>
                                <td>{{ $fmt($position['billed']) }}</td>
                                <td>₹{{ number_format((float) $item->purchase_rate, 2) }} / {{ number_format((float) $item->tax_percent, 2) }}%</td>
                                <td><input class="field-control bill-qty" type="number" min="0" step="0.01" name="items[{{ $item->id }}][quantity]" value="{{ old('items.' . $item->id . '.quantity', $defaultQty) }}"></td>
                                <td><input class="field-control bill-rate" type="number" min="0" step="0.01" name="items[{{ $item->id }}][rate]" value="{{ old('items.' . $item->id . '.rate', $line ? $line->rate : $item->purchase_rate) }}" required></td>
                                <td><input class="field-control bill-discount" type="number" min="0" max="100" step="0.01" name="items[{{ $item->id }}][discount_percent]" value="{{ old('items.' . $item->id . '.discount_percent', $line ? $line->discount_percent : $item->discount_percent) }}"></td>
                                <td><input class="field-control bill-tax" type="number" min="0" max="100" step="0.01" name="items[{{ $item->id }}][tax_percent]" value="{{ old('items.' . $item->id . '.tax_percent', $line ? $line->tax_percent : $item->tax_percent) }}"></td>
                                <td class="bill-line-total">₹0.00</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="form-actions"><strong>Computed line total: <span id="bill-computed-total">₹0.00</span></strong> <small class="field-hint">Estimate only; the server recalculates and matches exactly.</small></div>
            </div>
            <div class="form-section">
                <div class="field"><label for="notes">Notes</label><textarea class="field-control" id="notes" name="notes" rows="2">{{ old('notes', $editing ? $bill->notes : '') }}</textarea></div>
                @if ($editing)
                    <div class="field"><label for="correction_reason">Reason for correction <span class="required-mark">*</span></label><input class="field-control" id="correction_reason" name="correction_reason" type="text" maxlength="2000" value="{{ old('correction_reason') }}" required></div>
                @endif
            </div>
            <div class="form-actions"><a class="button button-light" href="{{ $editing ? route('supplier-bills.show', $bill->id) : route('purchase-orders.show', $purchaseOrder->id) }}">Cancel</a><button class="button button-primary" type="submit">{{ $editing ? 'Save Correction and Re-match' : 'Submit Bill for Verification' }}</button></div>
        </form>
    </section>
    <script>
        (function () {
            var invoiceTotal = document.getElementById('invoice_total');
            var initializeInvoiceTotal = {{ $editing || old('invoice_total') !== null ? 'false' : 'true' }};

            function toMinor(value) {
                return Math.round((parseFloat(value) || 0) * 100);
            }

            function roundHalfUp(numerator, denominator) {
                return Math.floor((numerator + denominator / 2) / denominator);
            }

            function recalc(updateInvoiceTotal) {
                var totalMinor = 0;
                document.querySelectorAll('.bill-line').forEach(function (row) {
                    var quantityMinor = toMinor(row.querySelector('.bill-qty').value);
                    var rateMinor = toMinor(row.querySelector('.bill-rate').value);
                    var discountMinor = toMinor(row.querySelector('.bill-discount').value);
                    var taxMinor = toMinor(row.querySelector('.bill-tax').value);
                    var basicMinor = roundHalfUp(quantityMinor * rateMinor, 100);
                    var discountAmountMinor = roundHalfUp(basicMinor * discountMinor, 10000);
                    var taxableMinor = basicMinor - discountAmountMinor;
                    var cgstMinor = roundHalfUp(taxableMinor * Math.ceil(taxMinor / 2), 10000);
                    var sgstMinor = roundHalfUp(taxableMinor * (taxMinor - Math.ceil(taxMinor / 2)), 10000);
                    var lineTotalMinor = taxableMinor + cgstMinor + sgstMinor;
                    row.querySelector('.bill-line-total').textContent = '₹' + (lineTotalMinor / 100).toFixed(2);
                    totalMinor += lineTotalMinor;
                });
                var total = (totalMinor / 100).toFixed(2);
                document.getElementById('bill-computed-total').textContent = '₹' + total;
                if (updateInvoiceTotal) {
                    invoiceTotal.value = total;
                }
            }
            document.querySelectorAll('.bill-line input').forEach(function (input) {
                input.addEventListener('input', function () { recalc(true); });
            });
            recalc(initializeInvoiceTotal);
        }());
    </script>
    @include('procurement._submit_once')
@endsection
