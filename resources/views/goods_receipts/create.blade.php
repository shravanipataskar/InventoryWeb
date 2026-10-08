@extends('layouts.app')

@section('title', 'Receive Goods')
@section('topbar-title', 'Receive goods')

@section('content')
    <div class="page-heading">
        <div><span class="section-kicker">GOODS RECEIVED / {{ $purchaseOrder->po_number }}</span><h1>Receive Goods</h1><p>Accepted quantities are posted once to the selected location and recorded in Stock Inward.</p></div>
        <a class="button button-light" href="{{ route('purchase-orders.show', $purchaseOrder->id) }}">Back to purchase order</a>
    </div>
    @if ($errors->any())
        <div class="form-alert" role="alert"><strong>Please check the form:</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    @if (!$stores->count())
        <div class="inventory-notice notice-out"><span class="notice-symbol">!</span><span><strong>Location required</strong><small>Add an active store/location before receiving goods.</small></span></div>
    @endif
    <section class="form-card">
        <form action="{{ route('goods-receipts.store', $purchaseOrder->id) }}" method="POST">
            @csrf
            <input type="hidden" name="submission_token" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
            <div class="form-section">
                <div class="form-grid">
                    <div class="field"><label for="store_id">Receive into location <span class="required-mark">*</span></label><select class="field-control" id="store_id" name="store_id" required><option value="">Select location</option>@foreach ($stores as $store)<option value="{{ $store->id }}" {{ old('store_id') == $store->id ? 'selected' : '' }}>{{ $store->name }}{{ $store->location ? ' · ' . $store->location : '' }}</option>@endforeach</select>@error('store_id')<small class="field-error">{{ $message }}</small>@enderror</div>
                    <div class="field"><label for="received_date">Received date <span class="required-mark">*</span></label><input class="field-control" id="received_date" name="received_date" type="date" value="{{ old('received_date', date('Y-m-d')) }}" required>@error('received_date')<small class="field-error">{{ $message }}</small>@enderror</div>
                    <div class="field"><label for="invoice_number">Supplier invoice/bill number</label><input class="field-control" id="invoice_number" name="invoice_number" type="text" maxlength="100" value="{{ old('invoice_number') }}"></div>
                </div>
            </div>
            <div class="form-section">
                <div class="form-card-heading"><span class="form-section-icon form-icon-gold"><svg><use href="#icon-tray-in"></use></svg></span><div><h2>{{ $purchaseOrder->po_number }} · {{ $purchaseOrder->supplier->name }}</h2><p>Rejected units do not increase stock. Enter 0 for products not included in this delivery.</p></div></div>
                <div class="table-wrap">
                    <table class="data-table listing-table">
                        <thead><tr><th>PRODUCT</th><th>ORDERED</th><th>PREVIOUSLY RECEIVED</th><th>REMAINING</th><th>RECEIVED NOW</th><th>REJECTED</th><th>ACCEPTED</th><th>UNIT RATE</th></tr></thead>
                        <tbody>
                        @foreach ($purchaseOrder->items as $index => $item)
                            @php($remaining = max(0, (float) $item->ordered_quantity - (float) $item->received_quantity))
                            <tr>
                                <td><strong>{{ $item->product->name }}</strong><small class="field-hint">{{ $item->product->product_code }}</small><input type="hidden" name="items[{{ $index }}][purchase_order_item_id]" value="{{ $item->id }}"></td>
                                <td>{{ number_format($item->ordered_quantity, 2) }}</td>
                                <td>{{ number_format($item->received_quantity, 2) }}</td>
                                <td>{{ number_format($remaining, 2) }}</td>
                                <td><input class="field-control receipt-quantity" type="number" name="items[{{ $index }}][received_quantity]" min="0" max="{{ $remaining }}" step="0.01" value="{{ old('items.' . $index . '.received_quantity', 0) }}" data-remaining="{{ $remaining }}" required></td>
                                <td><input class="field-control receipt-rejected" type="number" name="items[{{ $index }}][rejected_quantity]" min="0" max="{{ $remaining }}" step="0.01" value="{{ old('items.' . $index . '.rejected_quantity', 0) }}" required></td>
                                <td class="receipt-accepted">0.00</td>
                                <td>₹{{ number_format($item->purchase_rate, 2) }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="form-section">
                <div class="field"><label for="remarks">Remarks</label><textarea class="field-control" id="remarks" name="remarks" rows="3" placeholder="Optional receiving notes">{{ old('remarks') }}</textarea></div>
            </div>
            <div class="form-actions"><a class="button button-light" href="{{ route('purchase-orders.show', $purchaseOrder->id) }}">Cancel</a><button class="button button-primary" type="submit" {{ !$stores->count() ? 'disabled' : '' }}>Confirm Goods Received</button></div>
        </form>
    </section>
    <script>
        (function () {
            function updateAccepted(input) {
                var row = input.closest('tr');
                var received = parseFloat(row.querySelector('.receipt-quantity').value) || 0;
                var rejected = parseFloat(row.querySelector('.receipt-rejected').value) || 0;
                row.querySelector('.receipt-accepted').textContent = Math.max(0, received - rejected).toFixed(2);
            }
            document.querySelectorAll('.receipt-quantity, .receipt-rejected').forEach(function (input) {
                input.addEventListener('input', function () { updateAccepted(input); });
                updateAccepted(input);
            });
        }());
    </script>
@endsection
