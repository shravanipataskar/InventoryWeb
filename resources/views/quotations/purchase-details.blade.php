@extends('layouts.app')

@section('title', 'Purchase Details · ' . $quotation->quotation_code)
@section('topbar-title', 'Purchase Details')

@section('content')
    @php
        $purchaseOrder = $quotation->purchaseOrder;
        $payments = $purchaseOrder ? $purchaseOrder->payments->where('status', 'paid') : collect();
        $paymentStatus = $remaining <= 0 ? 'Fully Paid' : ($totalPaid > 0 ? 'Partially Paid' : 'Pending');
    @endphp
    <style>
        .purchase-details-page{max-width:1320px;margin:0 auto;padding-bottom:28px}
        .purchase-details-header{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin-bottom:16px}
        .purchase-details-header h1{margin:0;color:#172b3d;font-size:23px}
        .purchase-details-header p{margin:6px 0 0;color:#77879a;font-size:12px}
        .purchase-details-card{margin-bottom:14px;padding:17px 18px;border:1px solid #e7edf2;border-radius:13px;background:#fff;box-shadow:0 4px 16px rgba(22,46,67,.035)}
        .purchase-details-section-title{display:flex;align-items:center;gap:9px;margin:0 0 13px;color:#18385f;font-size:14px}
        .purchase-details-section-title small{display:block;margin-top:3px;color:#8190a0;font-size:10px;font-weight:400}
        .purchase-details-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px 16px}
        .purchase-detail{min-width:0;padding:8px 10px;border-radius:8px;background:#f8fafc}
        .purchase-detail small,.purchase-detail strong{display:block}
        .purchase-detail small{margin-bottom:4px;color:#7c8b9b;font-size:10px}
        .purchase-detail strong{overflow-wrap:anywhere;color:#25415b;font-size:11px;font-weight:600}
        .purchase-detail-wide{grid-column:span 2}
        .purchase-documents{display:flex;gap:8px;flex-wrap:wrap;margin-top:11px}
        .purchase-documents a{font-size:10px}
        .purchase-account{display:flex;align-items:center;gap:8px;flex-wrap:nowrap;white-space:nowrap}
        .purchase-account span{flex:0 0 auto;white-space:nowrap;overflow-wrap:normal}
        .purchase-account button{display:inline-flex;flex:0 0 auto;align-items:center;padding:0;border:0;background:transparent;color:#147e81;cursor:pointer;font-size:11px;white-space:nowrap}
        .purchase-account button svg{width:17px;height:17px;fill:none;stroke:currentColor;stroke-linecap:round;stroke-linejoin:round;stroke-width:1.7}
        .purchase-account button .account-eye-slash{display:none}
        .purchase-account button.is-visible .account-eye-slash{display:block}
        .purchase-details-table-wrap{overflow-x:auto}
        .purchase-details-table{width:100%;min-width:1000px;border-collapse:collapse}
        .purchase-details-table th{padding:10px 8px;background:#f7f9fc;color:#748398;font-size:9px;text-align:left;white-space:nowrap}
        .purchase-details-table td{padding:9px 8px;border-top:1px solid #edf1f5;color:#344f68;font-size:10px;vertical-align:middle}
        .purchase-details-table .field-control{min-width:84px;padding:7px 8px}
        .purchase-details-table .readonly-rate{color:#6d7f91;white-space:nowrap}
        .purchase-details-table .line-money{white-space:nowrap}
        .purchase-details-table .line-total{color:#19385e;font-weight:700}
        .purchase-product{color:#19385e;font-weight:700}
        .purchase-product small{display:block;margin-top:3px;color:#8492a1;font-size:9px;font-weight:400}
        .purchase-tax-split{display:block;margin-top:4px;color:#7c8b9b;font-size:9px;white-space:nowrap}
        .purchase-details-totals{display:flex;justify-content:flex-end;gap:0;margin-top:12px}
        .purchase-details-totals>div{min-width:135px;padding:6px 14px;border-right:1px solid #e8edf2}
        .purchase-details-totals>div:last-child{border:0;border-radius:8px;background:#eef5ff}
        .purchase-details-totals small,.purchase-details-totals strong{display:block}
        .purchase-details-totals small{margin-bottom:5px;color:#748398;font-size:10px}
        .purchase-details-totals strong{color:#19385e;font-size:13px}
        .purchase-details-totals>div:last-child strong{color:#1763c6}
        .purchase-payment-summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px}
        .purchase-payment-kpi{padding:13px;border:1px solid #e8edf2;border-radius:10px;background:#fbfcfe}
        .purchase-payment-kpi small,.purchase-payment-kpi strong{display:block}
        .purchase-payment-kpi small{margin-bottom:6px;color:#77879a;font-size:10px}
        .purchase-payment-kpi strong{color:#19385e;font-size:15px}
        .purchase-payment-kpi .pending{color:#ba7410}
        .purchase-payment-kpi .paid{color:#13824a}
        .purchase-entry-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}
        .purchase-entry-grid .field{min-width:0}
        .purchase-payment-actions{display:flex;justify-content:flex-end;gap:9px;margin-top:13px}
        .purchase-complete-notice{padding:12px 14px;border:1px solid #bfe8d2;border-radius:9px;background:#edfaf3;color:#18774a;font-size:12px}
        .purchase-complete-notice strong{display:block;margin-bottom:3px}
        .purchase-history-table{min-width:850px}
        .purchase-history-footer{display:flex;justify-content:flex-end;gap:24px;padding:12px 4px 0;color:#607389;font-size:11px}
        .purchase-history-footer strong{margin-left:5px;color:#19385e}
        .purchase-po-info{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px}
        .purchase-po-info>div{padding:11px;border-radius:9px;background:#f7f9fc}
        .purchase-po-info small,.purchase-po-info strong{display:block}
        .purchase-po-info small{margin-bottom:5px;color:#77879a;font-size:10px}
        .purchase-po-info strong{color:#19385e;font-size:12px}
        .purchase-details-actions{display:flex;justify-content:flex-end;gap:8px;margin-top:13px}
        .purchase-status-pill{display:inline-flex;padding:5px 9px;border-radius:20px;background:#fff4dc;color:#97650b;font-size:10px;font-weight:700}
        .purchase-status-pill.is-partial{background:#fff0e3;color:#b45f16}
        .purchase-status-pill.is-paid{background:#e8f8ef;color:#16814a}
        @media(max-width:900px){.purchase-details-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.purchase-payment-summary,.purchase-po-info{grid-template-columns:repeat(2,minmax(0,1fr))}}
        @media(max-width:620px){.purchase-details-header{align-items:flex-start;flex-direction:column}.purchase-details-card{padding:13px}.purchase-details-grid,.purchase-entry-grid{grid-template-columns:1fr}.purchase-detail-wide{grid-column:auto}.purchase-details-totals{justify-content:flex-start;flex-wrap:wrap}.purchase-details-totals>div{min-width:50%;padding:8px}.purchase-payment-summary,.purchase-po-info{grid-template-columns:repeat(2,minmax(0,1fr))}.purchase-history-footer{justify-content:flex-start;flex-wrap:wrap;gap:12px}}
    </style>

    <main class="purchase-details-page">
        <div class="purchase-details-header">
            <div>
                <span class="section-kicker">PURCHASING / APPROVED QUOTATION</span>
                <h1>Purchase Details</h1>
                <p>{{ $quotation->quotation_code }} · {{ optional($quotation->supplier)->company_name ?: optional($quotation->supplier)->name }} · Approved {{ optional($quotation->approved_at)->format('d M Y') ?: '—' }}</p>
            </div>
            <a class="button button-light" href="{{ route('quotations.show', $quotation->quotation_request_id) }}">Back to Quotations</a>
        </div>

        @include('components.flash')
        @if ($errors->any())
            <div class="form-alert" role="alert"><strong>Please correct the following:</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif

        <section class="purchase-details-card">
            <h2 class="purchase-details-section-title">Supplier Details</h2>
            <div class="purchase-details-grid">
                <div class="purchase-detail"><small>Supplier Name</small><strong>{{ $quotation->supplier->name ?: '—' }}</strong></div>
                <div class="purchase-detail"><small>Supplier Code</small><strong>{{ $quotation->supplier->supplier_code ?: '—' }}</strong></div>
                <div class="purchase-detail"><small>Company Name</small><strong>{{ $quotation->supplier->company_name ?: '—' }}</strong></div>
                <div class="purchase-detail"><small>Contact Person</small><strong>{{ $quotation->supplier->name ?: '—' }}</strong></div>
                <div class="purchase-detail"><small>Phone</small><strong>{{ $quotation->supplier->phone ?: '—' }}</strong></div>
                <div class="purchase-detail"><small>Email</small><strong>{{ $quotation->supplier->email ?: '—' }}</strong></div>
                <div class="purchase-detail"><small>GSTIN</small><strong>{{ $quotation->supplier->gst_number ?: '—' }}</strong></div>
                <div class="purchase-detail"><small>PAN</small><strong>{{ $quotation->supplier->pan_number ?: '—' }}</strong></div>
                <div class="purchase-detail"><small>Warehouse / Location</small><strong>{{ $quotation->supplier->warehouse_location ?: '—' }}</strong></div>
                <div class="purchase-detail purchase-detail-wide"><small>Complete Address</small><strong>{{ collect([$quotation->supplier->address, $quotation->supplier->city, $quotation->supplier->state, $quotation->supplier->pincode])->filter()->implode(', ') ?: '—' }}</strong></div>
            </div>
            @if ($quotation->supplier->aadhaar_card || $quotation->supplier->pan_card)
                <div class="purchase-documents">
                    @foreach (['aadhaar_card' => 'Aadhaar Card', 'pan_card' => 'PAN Card'] as $document => $label)
                        @if ($quotation->supplier->{$document})
                            <a class="button button-small button-light" href="{{ route('suppliers.documents.show', [$quotation->supplier_id, $document]) }}" target="_blank" rel="noopener">View {{ $label }}</a>
                            <a class="button button-small button-light" href="{{ route('suppliers.documents.show', [$quotation->supplier_id, $document, 'download' => 1]) }}">Download {{ $label }}</a>
                        @endif
                    @endforeach
                </div>
            @endif
        </section>

        <section class="purchase-details-card">
            <h2 class="purchase-details-section-title">Bank &amp; Payment Details</h2>
            <div class="purchase-details-grid">
                <div class="purchase-detail"><small>Bank Name</small><strong>{{ $quotation->supplier->bank_name ?: '—' }}</strong></div>
                <div class="purchase-detail"><small>Account Holder Name</small><strong>{{ $quotation->supplier->account_holder_name ?: '—' }}</strong></div>
                <div class="purchase-detail"><small>Account Number</small><strong class="purchase-account"><span id="supplier-account" data-account="{{ $quotation->supplier->account_number ?: '' }}">{{ $quotation->supplier->account_number ? str_repeat('•', max(0, strlen($quotation->supplier->account_number) - 4)) . substr($quotation->supplier->account_number, -4) : '—' }}</span>@if ($quotation->supplier->account_number)<button type="button" id="toggle-account" aria-label="Show account number" title="Show account number"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z"/><circle cx="12" cy="12" r="2.5"/><path class="account-eye-slash" d="m4 4 16 16"/></svg></button>@endif</strong></div>
                <div class="purchase-detail"><small>IFSC Code</small><strong>{{ $quotation->supplier->ifsc_code ?: '—' }}</strong></div>
                <div class="purchase-detail"><small>Branch Name</small><strong>{{ $quotation->supplier->branch_name ?: '—' }}</strong></div>
                <div class="purchase-detail"><small>Payment Terms</small><strong>{{ $quotation->supplier->payment_terms ? ucwords(str_replace('_', ' ', $quotation->supplier->payment_terms)) : '—' }}</strong></div>
                <div class="purchase-detail purchase-detail-wide"><small>Notes</small><strong>{{ $quotation->supplier->notes ?: '—' }}</strong></div>
            </div>
        </section>

        <form method="POST" action="{{ route('quotations.purchase-payments.store', $quotation->id) }}" id="purchase-payment-form">
            @csrf
            @if (!$purchaseOrder)
                <section class="purchase-details-card">
                    <h2 class="purchase-details-section-title">Purchase Items <small>Quotation rate is read-only. Set final negotiated purchase rate and discount.</small></h2>
                    <div class="purchase-details-table-wrap">
                        <table class="purchase-details-table" id="negotiated-items">
                            <thead><tr><th>#</th><th>Category</th><th>Product / SKU</th><th>Quantity</th><th>Unit</th><th>Quotation Rate</th><th>Purchase Rate</th><th>Discount %</th><th>Basic Amount</th><th>GST %</th><th>Tax</th><th>Total</th></tr></thead>
                            <tbody>
                            @foreach ($quotation->items as $item)
                                <tr class="negotiated-line" data-quantity="{{ $item->quantity }}" data-gst="{{ $item->gst_rate }}">
                                    <td>{{ $loop->iteration }}</td>
                                    <td>{{ optional($item->product->category)->name ?: '—' }}</td>
                                    <td><span class="purchase-product">{{ $item->product->name }}</span><small>{{ $item->product->product_code ?: '—' }}</small></td>
                                    <td>{{ number_format($item->quantity, 2) }}</td>
                                    <td>{{ optional($item->product->unit)->short_name ?: optional($item->product->unit)->name ?: '—' }}</td>
                                    <td class="readonly-rate">₹{{ number_format($item->supplier_rate, 2) }}</td>
                                    <td><input class="field-control negotiated-rate" type="number" min="0.01" step="0.01" required name="items[{{ $item->id }}][purchase_rate]" value="{{ old('items.' . $item->id . '.purchase_rate', number_format((float) $item->supplier_rate, 2, '.', '')) }}"></td>
                                    <td><input class="field-control negotiated-discount" type="number" min="0" max="100" step="0.01" name="items[{{ $item->id }}][discount_percent]" value="{{ old('items.' . $item->id . '.discount_percent', '0.00') }}"></td>
                                    <td class="line-money line-basic">₹0.00</td>
                                    <td><span>{{ number_format($item->gst_rate, 2) }}</span><small class="purchase-tax-split">CGST {{ number_format($item->cgst_rate, 2) }}% · SGST {{ number_format($item->sgst_rate, 2) }}%</small></td>
                                    <td class="line-money line-tax">₹0.00</td>
                                    <td class="line-money line-total">₹0.00</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="purchase-details-totals">
                        <div><small>Subtotal</small><strong id="purchase-subtotal">₹0.00</strong></div>
                        <div><small>Total Discount</small><strong id="purchase-discount">₹0.00</strong></div>
                        <div><small>Total Tax</small><strong id="purchase-tax">₹0.00</strong></div>
                        <div><small>Grand Total / Total Payable</small><strong id="purchase-total">₹0.00</strong></div>
                    </div>
                </section>
            @else
                <section class="purchase-details-card">
                    <h2 class="purchase-details-section-title">Purchase Items</h2>
                    <div class="purchase-details-table-wrap">
                        <table class="purchase-details-table">
                            <thead><tr><th>#</th><th>Category</th><th>Product / SKU</th><th>Quantity</th><th>Unit</th><th>Purchase Rate</th><th>Discount %</th><th>Basic Amount</th><th>GST %</th><th>Tax</th><th>Total</th></tr></thead>
                            <tbody>
                            @foreach ($purchaseOrder->items as $item)
                                <tr>
                                    @php
                                        $basic = round((float) $item->quantity * (float) $item->purchase_rate, 2);
                                        $discount = round($basic * (float) $item->discount_percent / 100, 2);
                                    @endphp
                                    <td>{{ $loop->iteration }}</td>
                                    <td>{{ optional($item->product->category)->name ?: '—' }}</td>
                                    <td><span class="purchase-product">{{ $item->product->name }}</span><small>{{ $item->product->product_code ?: '—' }}</small></td>
                                    <td>{{ number_format($item->quantity, 2) }}</td>
                                    <td>{{ optional($item->product->unit)->short_name ?: optional($item->product->unit)->name ?: '—' }}</td>
                                    <td>₹{{ number_format($item->purchase_rate, 2) }}</td>
                                    <td>{{ number_format($item->discount_percent, 2) }}%</td>
                                    <td>₹{{ number_format($basic, 2) }}</td>
                                    <td>{{ number_format($item->tax_percent, 2) }}<small class="purchase-tax-split">CGST {{ number_format($item->cgst_rate, 2) }}% · SGST {{ number_format($item->sgst_rate, 2) }}%</small></td>
                                    <td>₹{{ number_format((float) $item->cgst_amount + (float) $item->sgst_amount, 2) }}</td>
                                    <td class="line-total">₹{{ number_format($item->total_amount, 2) }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="purchase-details-totals">
                        <div><small>Subtotal</small><strong>₹{{ number_format($purchaseOrder->subtotal, 2) }}</strong></div>
                        <div><small>Total Discount</small><strong>₹{{ number_format($purchaseOrder->discount_amount, 2) }}</strong></div>
                        <div><small>Total Tax</small><strong>₹{{ number_format($purchaseOrder->tax_amount, 2) }}</strong></div>
                        <div><small>Grand Total / Total Payable</small><strong>₹{{ number_format($purchaseOrder->grand_total, 2) }}</strong></div>
                    </div>
                </section>
            @endif

            <section class="purchase-details-card">
                <h2 class="purchase-details-section-title">Payment Summary</h2>
                <div class="purchase-payment-summary">
                    <div class="purchase-payment-kpi"><small>Total Payable</small><strong id="payment-total-payable">₹{{ number_format($totalPayable, 2) }}</strong></div>
                    <div class="purchase-payment-kpi"><small>Total Paid</small><strong id="payment-total-paid" class="paid">₹{{ number_format($totalPaid, 2) }}</strong></div>
                    <div class="purchase-payment-kpi"><small>Remaining Amount</small><strong id="payment-remaining" class="pending">₹{{ number_format($remaining, 2) }}</strong></div>
                    <div class="purchase-payment-kpi"><small>Payment Status</small><strong><span id="payment-status" class="purchase-status-pill {{ $paymentStatus === 'Fully Paid' ? 'is-paid' : ($paymentStatus === 'Partially Paid' ? 'is-partial' : '') }}">{{ $paymentStatus }}</span></strong></div>
                </div>
            </section>

            @if ($remaining > 0 && (!$purchaseOrder || $purchaseOrder->status !== 'cancelled'))
                <section class="purchase-details-card">
                    <h2 class="purchase-details-section-title">Add Payment Entry</h2>
                    <input type="hidden" name="submission_token" value="{{ old('submission_token', (string) \Illuminate\Support\Str::uuid()) }}">
                    <div class="purchase-entry-grid">
                        <div class="field"><label for="payment_date">Payment Date <span class="required-mark">*</span></label><input class="field-control" type="date" id="payment_date" name="payment_date" value="{{ old('payment_date', now()->toDateString()) }}" required></div>
                        <div class="field"><label for="payment_amount">Amount <span class="required-mark">*</span></label><input class="field-control" type="number" id="payment_amount" name="amount" min="0.01" max="{{ number_format($remaining, 2, '.', '') }}" step="0.01" value="{{ old('amount') }}" required><small class="field-hint">Cannot exceed remaining ₹{{ number_format($remaining, 2) }}.</small></div>
                        <div class="field"><label for="payment_method">Payment Method <span class="required-mark">*</span></label><select class="field-control" id="payment_method" name="payment_method" required><option value="">Select method</option>@foreach (\App\PurchasePayment::METHODS as $method)<option value="{{ $method }}" {{ old('payment_method') === $method ? 'selected' : '' }}>{{ $method }}</option>@endforeach</select></div>
                        <div class="field"><label for="reference_number">Reference Number</label><input class="field-control" type="text" id="reference_number" name="reference_number" maxlength="100" value="{{ old('reference_number') }}"></div>
                        <div class="field field-wide"><label for="payment_remarks">Remarks</label><input class="field-control" type="text" id="payment_remarks" name="remarks" maxlength="2000" value="{{ old('remarks') }}"></div>
                    </div>
                    <div class="purchase-payment-actions"><button class="button button-primary" type="submit">Save Payment{{ $purchaseOrder ? '' : ' & Generate Purchase Order' }}</button></div>
                </section>
            @else
                @if ($remaining <= 0)
                    <div class="purchase-complete-notice"><strong>Payment Completed</strong>The full payable amount has been paid. No further payment can be added.</div>
                @else
                    <div class="form-alert" role="status"><strong>Purchase Order Cancelled</strong>No additional payments can be added to this order.</div>
                @endif
            @endif
        </form>

        @if ($purchaseOrder)
            <section class="purchase-details-card">
                <h2 class="purchase-details-section-title">Purchase Order Information</h2>
                <div class="purchase-po-info">
                    <div><small>PO Number</small><strong>{{ $purchaseOrder->po_number }}</strong></div>
                    <div><small>Generated On</small><strong>{{ optional($purchaseOrder->created_at)->format('d M Y') ?: '—' }}</strong></div>
                    <div><small>Total Payable</small><strong>₹{{ number_format($purchaseOrder->grand_total, 2) }}</strong></div>
                    <div><small>Paid Amount</small><strong>₹{{ number_format($totalPaid, 2) }}</strong></div>
                    <div><small>Remaining</small><strong>₹{{ number_format($remaining, 2) }}</strong></div>
                    <div><small>PO Status</small><strong>{{ ucwords(str_replace('_', ' ', $purchaseOrder->status)) }}</strong></div>
                    <div><small>Payment Status</small><strong><span class="purchase-status-pill {{ $paymentStatus === 'Fully Paid' ? 'is-paid' : ($paymentStatus === 'Partially Paid' ? 'is-partial' : '') }}">{{ $paymentStatus }}</span></strong></div>
                </div>
                <div class="purchase-details-actions">
                    <a class="button button-primary" href="{{ route('purchase-orders.show', $purchaseOrder->id) }}">View Purchase Order</a>
                </div>
            </section>

            <section class="purchase-details-card">
                <h2 class="purchase-details-section-title">Payment History</h2>
                <div class="table-wrap">
                    <table class="data-table listing-table purchase-history-table">
                        <thead><tr><th>#</th><th>Payment Date</th><th>Amount</th><th>Payment Method</th><th>Reference Number</th><th>Remarks</th><th>Entered By</th><th>Status</th><th>Action</th></tr></thead>
                        <tbody>
                        @forelse ($payments->sortBy('payment_date') as $payment)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>{{ \Carbon\Carbon::parse($payment->payment_date)->format('d-M-Y') }}</td>
                                <td>₹{{ number_format($payment->amount, 2) }}</td>
                                <td>{{ $payment->payment_method }}</td>
                                <td>{{ $payment->reference_number ?: '—' }}</td>
                                <td>{{ $payment->remarks ?: '—' }}</td>
                                <td>{{ optional($payment->enteredBy)->name ?: '—' }}</td>
                                <td>@include('procurement._status', ['status' => $payment->status])</td>
                                <td><a class="button button-small button-light" href="{{ route('purchase-orders.show', $purchaseOrder->id) }}">View PO</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="9">No payments recorded.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="purchase-history-footer"><span>Total Paid:<strong>₹{{ number_format($totalPaid, 2) }}</strong></span><span>Remaining:<strong>₹{{ number_format($remaining, 2) }}</strong></span></div>
            </section>
        @endif
    </main>

    <script>
        (function () {
            var account = document.getElementById('supplier-account');
            var toggle = document.getElementById('toggle-account');
            if (account && toggle) {
                var visible = false;
                toggle.addEventListener('click', function () {
                    visible = !visible;
                    var value = account.dataset.account;
                    account.textContent = visible
                        ? value
                        : (value.length > 4 ? new Array(value.length - 3).join('•') + value.slice(-4) : value);
                    toggle.setAttribute('aria-label', visible ? 'Hide account number' : 'Show account number');
                    toggle.title = visible ? 'Hide account number' : 'Show account number';
                    toggle.classList.toggle('is-visible', visible);
                });
            }

            var rows = document.querySelectorAll('.negotiated-line');
            if (!rows.length) return;
            function money(value) {
                return '₹' + value.toFixed(2);
            }
            function updateTotals() {
                var subtotal = 0, discountTotal = 0, taxTotal = 0;
                rows.forEach(function (row) {
                    var quantity = parseFloat(row.dataset.quantity) || 0;
                    var rate = parseFloat(row.querySelector('.negotiated-rate').value) || 0;
                    var discountRate = parseFloat(row.querySelector('.negotiated-discount').value) || 0;
                    var gstRate = parseFloat(row.dataset.gst) || 0;
                    var basic = Math.round(quantity * rate * 100) / 100;
                    var discount = Math.round(basic * discountRate) / 100;
                    var taxable = basic - discount;
                    var cgstRate = Math.round(gstRate / 2 * 100) / 100;
                    var sgstRate = Math.round((gstRate - cgstRate) * 100) / 100;
                    var tax = Math.round(taxable * cgstRate) / 100 + Math.round(taxable * sgstRate) / 100;
                    var total = taxable + tax;
                    row.querySelector('.line-basic').textContent = money(basic);
                    row.querySelector('.line-tax').textContent = money(tax);
                    row.querySelector('.line-total').textContent = money(total);
                    subtotal += basic;
                    discountTotal += discount;
                    taxTotal += tax;
                });
                subtotal = Math.round(subtotal * 100) / 100;
                discountTotal = Math.round(discountTotal * 100) / 100;
                taxTotal = Math.round(taxTotal * 100) / 100;
                var total = Math.round((subtotal - discountTotal + taxTotal) * 100) / 100;
                document.getElementById('purchase-subtotal').textContent = money(subtotal);
                document.getElementById('purchase-discount').textContent = money(discountTotal);
                document.getElementById('purchase-tax').textContent = money(taxTotal);
                document.getElementById('purchase-total').textContent = money(total);
                document.getElementById('payment-total-payable').textContent = money(total);
                document.getElementById('payment-total-paid').textContent = money(0);
                document.getElementById('payment-remaining').textContent = money(total);
                var status = document.getElementById('payment-status');
                status.textContent = total > 0 ? 'Pending' : 'Fully Paid';
                status.classList.toggle('is-paid', total <= 0);
                status.classList.remove('is-partial');
                var amount = document.getElementById('payment_amount');
                if (amount) amount.max = total.toFixed(2);
            }
            document.querySelectorAll('.negotiated-rate,.negotiated-discount').forEach(function (input) {
                input.addEventListener('input', updateTotals);
            });
            updateTotals();
        }());
    </script>
@endsection
