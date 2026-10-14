@extends('layouts.app')

@section('title', 'Purchase Details · ' . $quotation->quotation_code)
@section('topbar-title', 'Purchase Details')

@section('content')
    @php
        $purchaseOrder = $quotation->purchaseOrder;
        $payments = $purchaseOrder ? $purchaseOrder->payments->where('status', 'paid') : collect();
        $oldPaymentPercentage = old('payment_percentage');
        $initialPaymentAmount = old('amount');
        if (old('payment_input_mode') === 'percentage'
            && is_numeric($oldPaymentPercentage)
            && (float) $oldPaymentPercentage > 0
            && (float) $oldPaymentPercentage <= 100) {
            $initialPaymentAmount = number_format($eligibleRemaining * (float) $oldPaymentPercentage / 100, 2, '.', '');
        }
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
        .purchase-status-pill.is-overdue{background:#fff0f1;color:#bf4050}
        .purchase-overview-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}
        .purchase-overview-grid .purchase-details-card{margin-bottom:14px}
        .purchase-overview-grid .purchase-details-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
        .purchase-overview-grid .purchase-detail-wide{grid-column:span 2}
        .purchase-details-header-actions{display:flex;align-items:center;justify-content:flex-end;gap:8px;flex-wrap:wrap}
        .purchase-details-header .button{white-space:nowrap}
        .purchase-payment-summary{grid-template-columns:repeat(5,minmax(0,1fr))}
        .purchase-payment-kpi{min-height:90px}
        .purchase-payment-kpi .payment-progress{height:6px;margin-top:10px;border-radius:8px;background:#e9eff5;overflow:hidden}
        .purchase-payment-kpi .payment-progress span{display:block;height:100%;border-radius:inherit;background:#168b8d}
        .purchase-payment-kpi:nth-child(2) .payment-progress span{background:#3aa779}
        .purchase-payment-kpi:nth-child(3) .payment-progress span{background:#dc7b9b}
        .purchase-payment-kpi:nth-child(4) .payment-progress span{background:#8b73d1}
        .purchase-entry-context{grid-column:1/-1;padding:10px 12px;border-radius:8px;background:#f2f8f8;color:#426875;font-size:11px}
        .purchase-entry-grid{grid-template-columns:repeat(4,minmax(0,1fr))}
        .purchase-payment-actions{align-items:center}
        .purchase-entry-grid{grid-template-columns:repeat(3,minmax(0,1fr))}
        .purchase-entry-context{grid-column:1/-1;padding:12px 14px;border:1px solid #e3edf0;background:#f6fafb;color:#536f7a}
        .purchase-entry-context strong{color:#19385e}
        .payment-form-errors{margin-top:5px;color:#b33d4e;font-size:11px}
        .payment-input-error{border-color:#c84c5e!important}
        .payment-balance-preview{display:flex;gap:16px;flex-wrap:wrap;margin-top:5px;color:#66798c;font-size:10px}
        .payment-balance-preview strong{color:#19385e}
        .payment-history-voucher{font-weight:700;color:#19385e}
        .discount-input-wrap{display:flex;align-items:center;gap:5px;min-width:92px}
        .discount-input-wrap .field-control{min-width:0;width:100%;padding-right:6px}
        .discount-input-suffix{color:#718297;font-size:10px}
        .purchase-detail-accent-blue{border-top:3px solid #85a8e8}
        .purchase-detail-accent-green{border-top:3px solid #75c7a0}
        .purchase-detail-accent-purple{border-top:3px solid #ae9be3}
        .purchase-summary-accent-blue{border-top:3px solid #8aaef0}
        .purchase-summary-accent-green{border-top:3px solid #79cda5}
        .purchase-summary-accent-pink{border-top:3px solid #e59ab4}
        .purchase-summary-accent-purple{border-top:3px solid #ad9ae2}
        .purchase-summary-accent-teal{border-top:3px solid #78c9c5}
        .purchase-print-hide{display:block}
        @media(max-width:1050px){.purchase-overview-grid{grid-template-columns:1fr}.purchase-overview-grid .purchase-details-card{margin-bottom:0}.purchase-overview-grid .purchase-details-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}
        @media(max-width:900px){.purchase-payment-summary{grid-template-columns:repeat(3,minmax(0,1fr))}}
        @media(max-width:620px){.purchase-overview-grid .purchase-details-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.purchase-details-header-actions{justify-content:flex-start}.purchase-payment-summary{grid-template-columns:repeat(2,minmax(0,1fr))}.purchase-entry-grid{grid-template-columns:1fr}.purchase-entry-context{grid-column:auto}}
        @media print{.purchase-print-hide,.purchase-payment-actions{display:none!important}.purchase-details-page{max-width:none}.purchase-details-card{box-shadow:none;break-inside:avoid}.purchase-details-table-wrap{overflow:visible}.purchase-details-table{min-width:0}}
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
            <div class="purchase-details-header-actions purchase-print-hide">
                <a class="button button-light" href="{{ route('quotations.show', $quotation->quotation_request_id) }}">Back to Quotations</a>
                <button class="button button-light" type="button" onclick="window.print()">Print</button>
                @if (!$purchaseOrder && $canManagePayments)
                    <button class="button button-primary" type="submit" form="purchase-order-form" formaction="{{ route('quotations.purchase-orders.generate', $quotation->id) }}">Generate Purchase Order</button>
                @endif
            </div>
        </div>

        @include('components.flash')
        @if ($errors->any())
            <div class="form-alert" role="alert"><strong>Please correct the following:</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif

        <div class="purchase-overview-grid">
        <section class="purchase-details-card purchase-detail-accent-blue">
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

        <section class="purchase-details-card purchase-detail-accent-green">
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
        <section class="purchase-details-card purchase-detail-accent-purple">
            <h2 class="purchase-details-section-title">Quotation Information</h2>
            <div class="purchase-details-grid">
                <div class="purchase-detail"><small>Quotation Number</small><strong>{{ $quotation->quotation_code ?: '—' }}</strong></div>
                <div class="purchase-detail"><small>Request Number</small><strong>{{ optional($quotation->request)->quotation_request_code ?: '—' }}</strong></div>
                <div class="purchase-detail"><small>Quotation Date</small><strong>{{ $quotation->quotation_date ? \Carbon\Carbon::parse($quotation->quotation_date)->format('d M Y') : '—' }}</strong></div>
                <div class="purchase-detail"><small>Required Date</small><strong>{{ optional($quotation->request)->required_date ? \Carbon\Carbon::parse($quotation->request->required_date)->format('d M Y') : '—' }}</strong></div>
                <div class="purchase-detail"><small>Approval Status</small><strong>{{ ucfirst(str_replace('_', ' ', $quotation->status)) }}</strong></div>
                <div class="purchase-detail"><small>Approval Date</small><strong>{{ optional($quotation->approved_at)->format('d M Y') ?: '—' }}</strong></div>
                <div class="purchase-detail purchase-detail-wide"><small>Company / Supplier</small><strong>{{ optional($quotation->supplier)->company_name ?: optional($quotation->supplier)->name ?: '—' }}</strong></div>
            </div>
        </section>
        </div>

        <form method="POST" action="{{ route('quotations.purchase-orders.generate', $quotation->id) }}" id="purchase-order-form">
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
                                    <td>@if ($canManagePayments)<input class="field-control negotiated-rate" type="number" min="0.01" step="0.01" required name="items[{{ $item->id }}][purchase_rate]" value="{{ old('items.' . $item->id . '.purchase_rate', number_format((float) $item->supplier_rate, 2, '.', '')) }}">@else<span class="readonly-rate">₹{{ number_format($item->supplier_rate, 2) }}</span>@endif</td>
                                    <td>@if ($canManagePayments)<span class="discount-input-wrap"><input class="field-control negotiated-discount" type="number" min="0" max="100" step="0.01" required name="items[{{ $item->id }}][discount_percent]" value="{{ old('items.' . $item->id . '.discount_percent', '0.00') }}"><span class="discount-input-suffix">%</span></span>@else<span>{{ number_format(0, 2) }}%</span>@endif</td>
                                    <td><input class="field-control negotiated-rate" type="number" min="0.01" step="0.01" required name="items[{{ $item->id }}][purchase_rate]" value="{{ old('items.' . $item->id . '.purchase_rate', number_format((float) $item->supplier_rate, 2, '.', '')) }}"></td>
                                    <td><input class="field-control negotiated-discount" type="number" min="0" max="100" step="0.01" name="items[{{ $item->id }}][discount_percent]" value="{{ old('items.' . $item->id . '.discount_percent', number_format((float) $item->discount_percent, 2, '.', '')) }}"></td>
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
                            <thead><tr><th>#</th><th>Category</th><th>Product / SKU</th><th>Quantity</th><th>Unit</th><th>Quotation Rate</th><th>Purchase Rate</th><th>Discount %</th><th>Basic Amount</th><th>GST %</th><th>Tax</th><th>Total</th></tr></thead>
                            <tbody>
                            @foreach ($purchaseOrder->items as $item)
                                <tr class="negotiated-line" data-quantity="{{ $item->quantity }}" data-gst="{{ $item->tax_percent }}" data-cgst="{{ $item->cgst_rate }}" data-sgst="{{ $item->sgst_rate }}">
                                    <td>{{ $loop->iteration }}</td>
                                    <td>{{ optional($item->product->category)->name ?: '—' }}</td>
                                    <td><span class="purchase-product">{{ $item->product->name }}</span><small>{{ $item->product->product_code ?: '—' }}</small></td>
                                    <td>{{ number_format($item->quantity, 2) }}</td>
                                    <td>{{ optional($item->product->unit)->short_name ?: optional($item->product->unit)->name ?: '—' }}</td>
                                    <td>₹{{ number_format($item->quotation_rate, 2) }}</td>
                                    <td class="readonly-rate">₹{{ number_format($item->purchase_rate, 2) }}</td>
                                    <td>@if ($canEditPurchaseItems)<span class="discount-input-wrap"><input class="field-control negotiated-discount" type="number" min="0" max="100" step="0.01" required form="purchase-discount-form" name="items[{{ $item->id }}][discount_percent]" value="{{ old('items.' . $item->id . '.discount_percent', number_format($item->discount_percent, 2, '.', '')) }}"><span class="discount-input-suffix">%</span></span>@else{{ number_format($item->discount_percent, 2) }}%@endif</td>
                                    <td class="line-money line-basic">₹{{ number_format($item->basic_amount, 2) }}</td>
                                    <td>{{ number_format($item->tax_percent, 2) }}<small class="purchase-tax-split">CGST {{ number_format($item->cgst_rate, 2) }}% · SGST {{ number_format($item->sgst_rate, 2) }}%</small></td>
                                    <td class="line-money line-tax">₹{{ number_format($item->tax_amount, 2) }}</td>
                                    <td class="line-total">₹{{ number_format($item->total_amount, 2) }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="purchase-details-totals">
                        <div><small>Subtotal</small><strong id="purchase-subtotal">₹{{ number_format($purchaseOrder->subtotal, 2) }}</strong></div>
                        <div><small>Total Discount</small><strong id="purchase-discount">₹{{ number_format($purchaseOrder->discount_amount, 2) }}</strong></div>
                        <div><small>Total Tax</small><strong id="purchase-tax">₹{{ number_format($purchaseOrder->tax_amount, 2) }}</strong></div>
                        <div><small>Grand Total / Total Payable</small><strong id="purchase-total">₹{{ number_format($purchaseOrder->grand_total, 2) }}</strong></div>
                    </div>
                </section>
            @endif
        </form>
        @if ($canEditPurchaseItems)
            <form method="POST" action="{{ route('quotations.purchase-discounts.update', $quotation->id) }}" id="purchase-discount-form" class="purchase-details-actions purchase-print-hide">
                @csrf
                @method('PUT')
                <div class="payment-form-errors" id="discount-payable-error" role="alert" hidden></div>
                <button class="button button-primary" type="submit">Save Discount Changes</button>
            </form>
        @endif

        <section class="purchase-details-card">
            <h2 class="purchase-details-section-title">Payment Summary</h2>
            <div class="purchase-payment-summary" id="purchase-payment-summary" data-paid="{{ number_format($totalPaid, 2, '.', '') }}" data-committed="{{ number_format($committedAmount, 2, '.', '') }}" data-overdue="{{ $paymentStatus === 'Overdue' ? '1' : '0' }}">
                <div class="purchase-payment-kpi purchase-summary-accent-blue"><small>Total Purchase Payable</small><strong id="payment-total-payable">₹{{ number_format($totalPayable, 2) }}</strong></div>
                <div class="purchase-payment-kpi purchase-summary-accent-green"><small>Total Paid</small><strong class="paid" id="payment-total-paid">₹{{ number_format($totalPaid, 2) }}</strong></div>
                <div class="purchase-payment-kpi purchase-summary-accent-pink"><small>Outstanding Amount</small><strong class="pending" id="payment-remaining">₹{{ number_format($remaining, 2) }}</strong></div>
                <div class="purchase-payment-kpi purchase-summary-accent-purple"><small>Payment Percentage</small><strong id="payment-purchase-percentage">{{ number_format($paymentPercentage, 2) }}%</strong><div class="payment-progress" id="payment-purchase-progress" role="progressbar" aria-valuenow="{{ min(100, $paymentPercentage) }}" aria-valuemin="0" aria-valuemax="100"><span style="width:{{ min(100, $paymentPercentage) }}%"></span></div></div>
                <div class="purchase-payment-kpi purchase-summary-accent-teal"><small>Payment Status</small><strong><span id="payment-status" class="purchase-status-pill {{ $paymentStatus === 'Paid' ? 'is-paid' : ($paymentStatus === 'Partially Paid' ? 'is-partial' : ($paymentStatus === 'Overdue' ? 'is-overdue' : '')) }}">{{ $paymentStatus }}</span></strong></div>
            </div>
        </section>

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
                    <div><small>Payment Status</small><strong><span class="purchase-status-pill {{ $paymentStatus === 'Paid' ? 'is-paid' : ($paymentStatus === 'Partially Paid' ? 'is-partial' : ($paymentStatus === 'Overdue' ? 'is-overdue' : '')) }}">{{ $paymentStatus }}</span></strong></div>
                </div>
                <div class="purchase-details-actions">
                    <a class="button button-primary" href="{{ route('purchase-orders.show', $purchaseOrder->id) }}">View Purchase Order</a>
                </div>
            </section>

            <section class="purchase-details-card">
                <h2 class="purchase-details-section-title">Payment History</h2>
                <div class="table-wrap">
                    <table class="data-table listing-table purchase-history-table">
                        <thead><tr><th>Payment Voucher No.</th><th>Actual Payment Date</th><th>Payment Amount</th><th>Payment Percentage</th><th>Recorded By</th><th>Payment Status</th><th>Action</th></tr></thead>
                        <tbody>
                        @forelse ($payments->sortBy('payment_date') as $payment)
                            <tr>
                                <td class="payment-history-voucher">{{ $payment->voucher_number }}</td>
                                <td>{{ \Carbon\Carbon::parse($payment->payment_date)->format('d-M-Y') }}</td>
                                <td>₹{{ number_format($payment->amount, 2) }}</td>
                                <td>{{ number_format($payment->payment_percentage, 2) }}%</td>
                                <td>{{ optional($payment->enteredBy)->name ?: '—' }}</td>
                                <td>@include('procurement._status', ['status' => $payment->status])</td>
                                <td>@if ($canViewPurchaseOrder)<a class="button button-small button-light" href="{{ route('purchase-orders.show', $purchaseOrder->id) }}">View PO</a>@else — @endif</td>
                            </tr>
                        @empty
                            <tr><td colspan="7">No payments recorded.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="purchase-history-footer"><span>Total Paid:<strong>₹{{ number_format($totalPaid, 2) }}</strong></span><span>Remaining:<strong>₹{{ number_format($remaining, 2) }}</strong></span></div>
            </section>
        @endif

        <section class="purchase-details-card">
            <h2 class="purchase-details-section-title">Record Payment</h2>
            @if (!$canManagePayments)
                <div class="form-alert" role="status">You do not have permission to record purchase payments.</div>
            @elseif (!$purchaseOrder)
                <div class="form-alert" role="status">Generate the purchase order before recording a payment. A payment schedule is optional.</div>
            @elseif ($purchaseOrder->status === 'cancelled')
                <div class="form-alert" role="status"><strong>Purchase Order Cancelled</strong>No additional payments can be recorded.</div>
            @elseif ($remaining <= 0)
                <div class="purchase-complete-notice"><strong>Payment Completed</strong>The full payable amount has been paid. No further payment can be added.</div>
            @elseif ($eligibleRemaining <= 0)
                <div class="form-alert" role="status">The remaining payable balance is currently reserved by pending supplier-payment approvals.</div>
            @else
                <form method="POST" action="{{ route('quotations.purchase-payments.store', $quotation->id) }}" id="record-payment-form">
                    @csrf
                    <input type="hidden" name="submission_token" value="{{ old('submission_token', (string) \Illuminate\Support\Str::uuid()) }}">
                    <input type="hidden" name="payment_input_mode" id="payment_input_mode" value="{{ old('payment_input_mode', 'amount') }}">
                    <div class="purchase-entry-grid">
                        <div class="field">
                            <label for="payment_date">Payment Date <span class="required-mark">*</span></label>
                            <input class="field-control" type="date" id="payment_date" name="payment_date" max="{{ now()->toDateString() }}" value="{{ old('payment_date', now()->toDateString()) }}" required>
                            @if ($errors->has('payment_date'))<div class="payment-form-errors">{{ $errors->first('payment_date') }}</div>@endif
                        </div>
                        <div class="field">
                            <label for="payment_amount">Payment Amount (₹) <span class="required-mark">*</span></label>
                            <input class="field-control {{ $errors->has('amount') ? 'payment-input-error' : '' }}" type="number" id="payment_amount" name="amount" min="0.01" max="{{ number_format($eligibleRemaining, 2, '.', '') }}" step="0.01" value="{{ $initialPaymentAmount }}" inputmode="decimal" aria-describedby="payment-amount-error payment-amount-help" required>
                            <small class="field-hint" id="payment-amount-help">Maximum eligible payment: ₹{{ number_format($eligibleRemaining, 2) }}.</small>
                            @if ($errors->has('amount'))<div class="payment-form-errors" id="payment-amount-error">{{ $errors->first('amount') }}</div>@else<div class="payment-form-errors" id="payment-amount-error" hidden></div>@endif
                        </div>
                        <div class="field">
                            <label for="payment_percentage">Payment Percentage (%) <span class="required-mark">*</span></label>
                            <input class="field-control {{ $errors->has('payment_percentage') ? 'payment-input-error' : '' }}" type="number" id="payment_percentage" name="payment_percentage" min="0.01" max="{{ $eligibleRemaining > 0 ? '100.00' : '0.00' }}" step="0.01" value="{{ $oldPaymentPercentage }}" inputmode="decimal" aria-describedby="payment-percentage-error payment-percentage-help" required>
                            <small class="field-hint" id="payment-percentage-help">Calculated against eligible outstanding balance of ₹{{ number_format($eligibleRemaining, 2) }}; maximum 100%.</small>
                            @if ($errors->has('payment_percentage'))<div class="payment-form-errors" id="payment-percentage-error">{{ $errors->first('payment_percentage') }}</div>@else<div class="payment-form-errors" id="payment-percentage-error" hidden></div>@endif
                        </div>
                        <div class="purchase-entry-context" id="payment-balance-context" data-payable="{{ number_format($totalPayable, 2, '.', '') }}" data-outstanding="{{ number_format($remaining, 2, '.', '') }}" data-eligible="{{ number_format($eligibleRemaining, 2, '.', '') }}" aria-live="polite">
                            <strong>Outstanding before payment:</strong> ₹{{ number_format($remaining, 2) }}
                            <div class="payment-balance-preview"><span>Eligible today: <strong>₹{{ number_format($eligibleRemaining, 2) }}</strong></span><span>Remaining after this payment: <strong id="payment-remaining-preview">—</strong></span></div>
                        </div>
                    </div>
                    <div class="purchase-payment-actions purchase-print-hide"><button class="button button-primary" type="submit">Save Payment</button></div>
                </form>
            @endif
        </section>
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

            var rows = Array.prototype.filter.call(document.querySelectorAll('.negotiated-line'), function (row) {
                return row.querySelector('.negotiated-discount');
            });
            if (!rows.length) return;
            function money(value) {
                return '₹' + value.toFixed(2);
            }
            function updateTotals() {
                var subtotal = 0, discountTotal = 0, taxTotal = 0;
                rows.forEach(function (row) {
                    var quantity = parseFloat(row.dataset.quantity) || 0;
                    var rateInput = row.querySelector('.negotiated-rate');
                    var rate = rateInput ? parseFloat(rateInput.value) || 0 : parseFloat(row.querySelector('.readonly-rate').textContent.replace(/[^\d.]/g, '')) || 0;
                    var discountRate = parseFloat(row.querySelector('.negotiated-discount').value) || 0;
                    var gstRate = parseFloat(row.dataset.gst) || 0;
                    var basic = Math.round(quantity * rate * 100) / 100;
                    var discount = Math.round(basic * discountRate) / 100;
                    var taxable = Math.round((basic - discount) * 100) / 100;
                    var cgstRate = row.dataset.cgst !== undefined ? parseFloat(row.dataset.cgst) || 0 : Math.round(gstRate / 2 * 100) / 100;
                    var sgstRate = row.dataset.sgst !== undefined ? parseFloat(row.dataset.sgst) || 0 : Math.round((gstRate - cgstRate) * 100) / 100;
                    var cgst = Math.round(taxable * cgstRate) / 100;
                    var sgst = Math.round(taxable * sgstRate) / 100;
                    var tax = Math.round((cgst + sgst) * 100) / 100;
                    var total = Math.round((taxable + tax) * 100) / 100;
                    row.querySelector('.line-basic').textContent = money(taxable);
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
                var summary = document.getElementById('purchase-payment-summary');
                var paid = parseFloat(summary.dataset.paid) || 0;
                var committed = parseFloat(summary.dataset.committed) || 0;
                var remaining = Math.max(0, Math.round((total - paid) * 100) / 100);
                var paymentPercent = total > 0 ? Math.min(100, paid / total * 100) : 0;
                document.getElementById('payment-total-payable').textContent = money(total);
                document.getElementById('payment-total-paid').textContent = money(paid);
                document.getElementById('payment-remaining').textContent = money(remaining);
                document.getElementById('payment-purchase-percentage').textContent = paymentPercent.toFixed(2) + '%';
                var progress = document.getElementById('payment-purchase-progress');
                progress.setAttribute('aria-valuenow', paymentPercent.toFixed(2));
                progress.querySelector('span').style.width = paymentPercent + '%';
                var status = document.getElementById('payment-status');
                var statusText = remaining <= 0 ? 'Paid' : (summary.dataset.overdue === '1' ? 'Overdue' : (paid > 0 ? 'Partially Paid' : 'Pending'));
                status.textContent = statusText;
                status.classList.toggle('is-paid', statusText === 'Paid');
                status.classList.toggle('is-partial', statusText === 'Partially Paid');
                status.classList.toggle('is-overdue', statusText === 'Overdue');
                var discountSaveButton = document.querySelector('#purchase-discount-form button');
                var discountError = document.getElementById('discount-payable-error');
                if (discountSaveButton && discountError) {
                    var belowCommitted = total < committed;
                    discountSaveButton.disabled = belowCommitted;
                    discountError.hidden = !belowCommitted;
                    discountError.textContent = belowCommitted
                        ? 'The purchase payable cannot be less than the amount already paid or reserved.'
                        : '';
                }
                var paymentContext = document.getElementById('payment-balance-context');
                if (paymentContext) {
                    paymentContext.dataset.payable = total.toFixed(2);
                    paymentContext.dataset.outstanding = remaining.toFixed(2);
                    paymentContext.dataset.eligible = Math.max(0, total - committed).toFixed(2);
                    document.dispatchEvent(new CustomEvent('purchase:totals-changed'));
                }
                var amount = document.getElementById('payment_amount');
                if (amount) amount.max = Math.max(0, total - committed).toFixed(2);
            }
            document.querySelectorAll('.negotiated-rate,.negotiated-discount').forEach(function (input) {
                input.addEventListener('input', updateTotals);
            });
            updateTotals();
        }());
    </script>
@endsection

@section('scripts')
    <script>
        (function () {
            var paymentForm = document.getElementById('record-payment-form');
            var amountInput = document.getElementById('payment_amount');
            var percentInput = document.getElementById('payment_percentage');
            var modeInput = document.getElementById('payment_input_mode');
            var context = document.getElementById('payment-balance-context');
            if (!paymentForm || !amountInput || !percentInput || !modeInput || !context) return;

            var outstanding = 0;
            var eligible = 0;
            var remainingPreview = document.getElementById('payment-remaining-preview');
            var amountError = document.getElementById('payment-amount-error');
            var percentageError = document.getElementById('payment-percentage-error');
            var updateActiveField = true;

            function refreshPaymentContext() {
                outstanding = parseFloat(context.dataset.outstanding) || 0;
                eligible = parseFloat(context.dataset.eligible) || 0;
                amountInput.max = eligible.toFixed(2);
                percentInput.max = eligible > 0 ? '100.00' : '0.00';
                document.getElementById('payment-amount-help').textContent =
                    'Maximum eligible payment: ' + formatMoney(eligible) + '.';
                document.getElementById('payment-percentage-help').textContent =
                    'Calculated against the eligible outstanding balance of ' + formatMoney(eligible) + '; maximum 100%.';
                updatePaymentDetails();
            }

            function roundCurrency(value) {
                return Math.round((value + Number.EPSILON) * 100) / 100;
            }

            function formatMoney(value) {
                return '₹' + new Intl.NumberFormat('en-IN', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                }).format(value);
            }

            function showError(element, input, message) {
                element.textContent = message;
                element.hidden = !message;
                input.classList.toggle('payment-input-error', Boolean(message));
                input.setCustomValidity(message || '');
            }

            function updatePaymentDetails(source) {
                if (source) modeInput.value = source;
                var mode = modeInput.value;
                var amount = parseFloat(amountInput.value);
                var percentage = parseFloat(percentInput.value);
                if (!updateActiveField) return;

                updateActiveField = false;
                if (mode === 'percentage') {
                    amount = eligible > 0 && !isNaN(percentage)
                        ? roundCurrency(eligible * percentage / 100)
                        : NaN;
                    amountInput.value = isNaN(amount) ? '' : amount.toFixed(2);
                    amountInput.required = false;
                    amountInput.min = '0';
                    percentInput.required = true;
                    percentInput.min = '0.01';
                    percentInput.max = eligible > 0 ? '100.00' : '0.00';
                } else {
                    percentage = eligible > 0 && !isNaN(amount)
                        ? Math.round((amount / eligible) * 10000) / 100
                        : NaN;
                    percentInput.value = isNaN(percentage) ? '' : percentage.toFixed(2);
                    amountInput.required = true;
                    amountInput.min = '0.01';
                    percentInput.required = false;
                    percentInput.min = '0';
                    percentInput.max = eligible > 0 ? '100.00' : '0.00';
                }
                updateActiveField = true;

                var amountMessage = '';
                var percentageMessage = '';
                if (amountInput.value !== '' && amount <= 0) {
                    amountMessage = 'Payment amount must be greater than zero.';
                } else if (!isNaN(amount) && amount > eligible) {
                    amountMessage = 'Payment cannot exceed the eligible balance of ' + formatMoney(eligible) + '.';
                }
                if (percentInput.value !== '' && percentage <= 0) {
                    percentageMessage = 'Payment percentage must be greater than zero.';
                } else if (!isNaN(percentage) && percentage > 100) {
                    percentageMessage = 'Payment percentage cannot exceed 100%.';
                } else if (!isNaN(amount) && amount > eligible) {
                    percentageMessage = 'Payment cannot exceed the eligible outstanding balance.';
                }
                showError(amountError, amountInput, amountMessage);
                showError(percentageError, percentInput, percentageMessage);
                remainingPreview.textContent = !isNaN(amount) && amount > 0 && amount <= eligible
                    ? formatMoney(Math.max(0, roundCurrency(outstanding - amount)))
                    : '—';
            }

            amountInput.addEventListener('input', function () { updatePaymentDetails('amount'); });
            percentInput.addEventListener('input', function () { updatePaymentDetails('percentage'); });
            document.addEventListener('purchase:totals-changed', refreshPaymentContext);
            paymentForm.addEventListener('submit', function (event) {
                refreshPaymentContext();
                if (!paymentForm.checkValidity()) {
                    event.preventDefault();
                    paymentForm.reportValidity();
                }
            });
            refreshPaymentContext();
        }());
    </script>
@endsection
