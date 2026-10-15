@extends('layouts.app')

@section('title', $purchaseOrder->po_number)
@section('topbar-title', 'Purchase Order Details')

@section('content')
    @php
        $receivedQuantity = $purchaseOrder->items->sum('received_quantity');
        $remainingValue = $purchaseOrder->items->sum(function ($item) {
            return max(0, (float) $item->ordered_quantity - (float) $item->received_quantity) * (float) $item->purchase_rate;
        });
        $receivedReceipts = $purchaseOrder->goodsReceipts->count();
        $statusLabel = $purchaseOrder->status === 'received' ? 'Fully Received' : ucwords(str_replace('_', ' ', $purchaseOrder->status));
        $purchaseOutstanding = max(0, round((float) $purchaseOrder->grand_total - $purchasePaid, 2));
        $purchasePaymentStatus = $purchaseOutstanding <= 0 ? 'Fully Paid' : ($purchasePaid > 0 ? 'Partially Paid' : 'Pending');
    @endphp
    <style>
        .purchase-order-detail{max-width:1320px;margin:0 auto;padding-bottom:24px}
        .purchase-order-detail .po-back{display:inline-flex;align-items:center;gap:8px;margin:0 0 12px;color:#53677d;font-size:12px}
        .purchase-order-detail .po-back:hover{color:#108b8c}
        .po-detail-heading{display:flex;align-items:center;justify-content:space-between;gap:18px;margin-bottom:16px}
        .po-detail-title{display:flex;align-items:center;gap:14px;min-width:0}
        .po-detail-symbol{display:grid;width:54px;height:54px;flex:0 0 54px;place-items:center;border-radius:17px;background:#e8f2ff;color:#1478d4}
        .po-detail-symbol svg{width:26px;height:26px;fill:none;stroke:currentColor;stroke-linecap:round;stroke-linejoin:round;stroke-width:1.8}
        .po-detail-title h1{display:flex;align-items:center;flex-wrap:wrap;gap:10px;margin:0;color:#122a4e;font-size:22px}
        .po-detail-subtitle{display:flex;flex-wrap:wrap;gap:8px;margin:7px 0 0;color:#687b92;font-size:11px}
        .po-status-pill{display:inline-flex;align-items:center;gap:6px;padding:5px 10px;border:1px solid #bfead1;border-radius:20px;background:#e9f9f0;color:#13824a;font-size:10px;font-weight:700}
        .po-status-pill:before{width:6px;height:6px;border-radius:50%;background:currentColor;content:""}
        .po-status-pill.is-cancelled,.po-status-pill.is-rejected{border-color:#f8c8c5;background:#fff0ef;color:#b73b32}
        .po-status-pill.is-pending,.po-status-pill.is-ordered,.po-status-pill.is-draft{border-color:#f4dfac;background:#fff8e7;color:#a46a08}
        .po-detail-actions{display:flex;align-items:center;justify-content:flex-end;flex-wrap:wrap;gap:7px;flex-shrink:0}
        .po-detail-actions .button{white-space:nowrap}
        .po-summary-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:9px;margin:0 0 13px}
        .po-summary-card{display:flex;align-items:center;gap:11px;min-width:0;padding:11px;border:1px solid #e9eef4;border-radius:9px;background:#fff;box-shadow:0 2px 9px rgba(31,55,84,.03)}
        .po-summary-icon{display:grid;width:35px;height:35px;flex:0 0 35px;place-items:center;border-radius:10px;background:#f0eaff;color:#8054d6}
        .po-summary-card:nth-child(2) .po-summary-icon{background:#e9fbf1;color:#159456}
        .po-summary-card:nth-child(3) .po-summary-icon{background:#fff3e6;color:#d7791c}
        .po-summary-card:nth-child(4) .po-summary-icon{background:#eaf3ff;color:#267ad1}
        .po-summary-icon svg{width:18px;height:18px;fill:none;stroke:currentColor;stroke-linecap:round;stroke-linejoin:round;stroke-width:1.8}
        .po-summary-card small,.po-summary-card strong{display:block}
        .po-summary-card small{margin-bottom:4px;color:#718198;font-size:9px}
        .po-summary-card strong{overflow:hidden;color:#142d53;font-size:14px;text-overflow:ellipsis;white-space:nowrap}
        .po-summary-card strong.po-summary-pending{color:#c46315}
        .po-information-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-bottom:12px}
        .po-detail-panel{min-width:0;padding:12px;border:1px solid #e9eef4;border-radius:9px;background:#fff;box-shadow:0 2px 9px rgba(31,55,84,.03)}
        .po-panel-heading{display:flex;align-items:center;gap:9px;padding-bottom:9px;border-bottom:1px solid #edf1f5}
        .po-panel-heading h2{margin:0;color:#18385f;font-size:12px}
        .po-panel-heading p{margin:3px 0 0;color:#7b8a9b;font-size:9px}
        .po-panel-icon{display:grid;width:27px;height:27px;flex:0 0 27px;place-items:center;border-radius:8px;background:#eaf3ff;color:#287ed1}
        .po-panel-icon.green{background:#e9faf1;color:#139459}
        .po-panel-icon.orange{background:#fff3e6;color:#d7791c}
        .po-panel-icon.violet{background:#f2ebff;color:#8152d1}
        .po-panel-icon svg{width:15px;height:15px;fill:none;stroke:currentColor;stroke-linecap:round;stroke-linejoin:round;stroke-width:1.8}
        .po-info-rows{margin-top:3px}
        .po-info-row{display:grid;grid-template-columns:minmax(110px,.8fr) minmax(0,1.35fr);gap:10px;align-items:center;min-height:26px;padding:5px 1px;border-bottom:1px solid #f0f2f5;color:#5e7189;font-size:10px}
        .po-info-row:last-child{border-bottom:0}
        .po-info-row strong,.po-info-row a{color:#19385e;font-weight:600;overflow-wrap:anywhere}
        .po-info-row a:hover{text-decoration:underline}
        .po-table-panel{margin-bottom:11px;padding:12px;border:1px solid #e9eef4;border-radius:9px;background:#fff;box-shadow:0 2px 9px rgba(31,55,84,.03)}
        .po-table-heading{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:9px}
        .po-table-scroll{overflow-x:auto}
        .po-table{width:100%;min-width:820px;border-collapse:collapse}
        .po-table th{padding:8px 7px;background:#f7f9fc;color:#718198;font-size:8px;font-weight:700;text-align:left;white-space:nowrap}
        .po-table td{padding:8px 7px;border-top:1px solid #edf1f5;color:#19395f;font-size:9px;vertical-align:middle}
        .po-product-name{color:#16365c;font-size:10px;font-weight:700}
        .po-product-sub{display:block;margin-top:3px;color:#75859a;font-size:8px}
        .po-line-total{text-align:right;white-space:nowrap}
        .po-table tfoot td{background:#f0f6ff;color:#15375f;font-weight:700}
        .po-table-empty{padding:16px 9px!important;color:#7a899d!important;text-align:center}
        .po-empty-action{text-align:center}
        .po-empty-action p{margin:0 0 9px;color:#7a899d;font-size:10px}
        .po-receipt-items{margin:0;padding:0;list-style:none}
        .po-receipt-items li+li{margin-top:5px}
        .po-receipt-items strong,.po-receipt-items small{display:block}
        .po-receipt-items small{margin-top:2px;color:#718198;font-size:9px}
        .po-control-panel{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:11px;padding:10px 12px;border:1px solid #e9eef4;border-radius:9px;background:#fff}
        .po-control-panel .po-panel-heading{padding:0;border:0}
        .po-control-panel .po-control-note{margin:0;color:#748399;font-size:9px}
        .po-control-panel .inline-form{display:flex;align-items:center;gap:7px}
        .po-control-panel .field-control{max-width:230px;min-height:32px}
        .po-history-panel .po-panel-heading{margin-bottom:7px}
        .po-history-panel .table-wrap{overflow-x:auto}
        .po-history-panel .data-table{min-width:700px}
        .po-inline-notice{margin:0 0 12px;padding:9px 12px;border:1px solid #d9eafb;border-radius:8px;background:#f2f8ff;color:#45607d;font-size:10px}
        .po-inline-notice strong{color:#284d75}
        .po-inline-notice small{display:block;margin-top:3px;color:#657b93}
        .po-print-only{display:none}
        .po-release-dialog{width:min(480px,calc(100% - 28px));padding:0;border:0;border-radius:14px;box-shadow:0 24px 80px rgba(18,42,78,.25)}
        .po-release-dialog::backdrop{background:rgba(20,35,52,.48)}
        .po-release-content{padding:22px}
        .po-release-content h2{margin:0 0 7px;color:#16345a;font-size:18px}
        .po-release-content>p{margin:0;color:#6b7d91;font-size:12px;line-height:1.6}
        .po-release-content dl{margin:16px 0;padding:10px 12px;border-radius:9px;background:#f6f8fb}
        .po-release-content dl>div{display:flex;justify-content:space-between;gap:12px;padding:7px 0;border-bottom:1px solid #e7edf3;font-size:11px}
        .po-release-content dl>div:last-child{border:0}
        .po-release-content dt{color:#748397}
        .po-release-content dd{margin:0;color:#18375d;font-weight:700;text-align:right}
        .po-release-actions{display:flex;justify-content:flex-end;gap:8px}
        .po-release-actions form{margin:0}
        .po-account-reveal{display:inline-block;margin-left:5px;font-size:9px}
        .po-account-reveal summary{display:inline;color:#1478d4;cursor:pointer}
        .po-account-reveal span{display:block}
        @media(max-width:900px){.po-summary-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
        @media(max-width:700px){.po-detail-heading{align-items:flex-start;flex-direction:column}.po-detail-actions{width:100%;justify-content:flex-start}.po-information-grid{grid-template-columns:1fr}.po-control-panel{align-items:flex-start;flex-direction:column}.po-control-panel .inline-form{width:100%;flex-wrap:wrap}}
        @media(max-width:420px){.po-summary-grid{gap:6px}.po-summary-card{gap:7px;padding:8px}.po-summary-icon{width:30px;height:30px;flex-basis:30px}.po-summary-card strong{font-size:12px}.po-detail-title h1{font-size:18px}.po-info-row{grid-template-columns:minmax(98px,.8fr) minmax(0,1.2fr)}}
        @media print{.sidebar,.topbar,.po-back,.po-detail-actions,.po-inline-notice,.po-control-panel,.po-table-heading>.button,.po-empty-action,.po-history-panel .workspace-footer{display:none!important}.main-content,.main-content-inner{margin:0!important;padding:0!important}.purchase-order-detail{max-width:none}.po-summary-card,.po-detail-panel,.po-table-panel{box-shadow:none;break-inside:avoid}.po-detail-heading{margin-top:0}.po-print-only{display:block}.po-information-grid{grid-template-columns:1fr 1fr}}
    </style>

    <main class="purchase-order-detail">
        <a class="po-back" href="{{ route('purchase-orders.index') }}"><span aria-hidden="true">←</span> Back to purchase orders</a>

        @if (session('success'))<div class="inventory-notice notice-in" role="status"><span class="notice-symbol">✓</span><span>{{ session('success') }}</span></div>@endif
        @if (session('error'))<div class="form-alert" role="alert">{{ session('error') }}</div>@endif
        @if ($errors->any())<div class="form-alert" role="alert"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

        <header class="po-detail-heading">
            <div class="po-detail-title">
                <span class="po-detail-symbol" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M6 3h9l4 4v14H6zM14 3v5h5M9 13h7m-7 4h7"/></svg></span>
                <div>
                    <h1>{{ $purchaseOrder->po_number }} <span class="po-status-pill is-{{ $purchaseOrder->status }}">{{ $statusLabel }}</span></h1>
                    <p class="po-detail-subtitle">
                        <span>{{ optional($purchaseOrder->supplier)->name ?: 'Supplier unavailable' }}</span>
                        <span aria-hidden="true">·</span>
                        <span>{{ \Carbon\Carbon::parse($purchaseOrder->order_date)->format('d M Y') }}</span>
                        <span aria-hidden="true">·</span>
                        <span>{{ optional($purchaseOrder->store)->name ?: '—' }}</span>
                    </p>
                </div>
            </div>
            <div class="po-detail-actions">
                <a class="button button-light" href="{{ route('purchase-orders.print', $purchaseOrder->id) }}" target="_blank" rel="noopener" title="Open a print-ready purchase order or save it as a PDF"><span aria-hidden="true">▣</span> Print PO / Save PDF</a>
                @if ($purchaseOrder->quotation)<a class="button button-light" href="{{ route('quotations.show', $purchaseOrder->quotation->quotation_request_id) }}">Back to Quotations</a>@endif
                @if ($purchaseOrder->quotation)<a class="button button-light" href="{{ route('quotations.purchase-details', $purchaseOrder->quotation_id) }}">Manage Payments</a>@endif
                @if ($purchaseOrder->status === 'draft' && !$purchaseOrder->goodsReceipts->count() && !$purchaseOrder->payments->count() && (!$billingEnabled || !$purchaseOrder->supplierBills->count()) && auth()->user()->hasPermission('purchase', 'edit'))
                    <a class="button button-light" href="{{ route('purchase-orders.edit', $purchaseOrder->id) }}">Edit PO</a>
                @endif
                @if ($purchaseOrder->status === 'draft' && auth()->user()->hasPermission('purchase', 'approve'))
                    <button class="button button-primary" type="button" data-open-release="release-po-modal">Release Purchase Order</button>
                @endif
                @if (in_array($purchaseOrder->status, \App\PurchaseOrder::RECEIVABLE_STATUSES, true) && auth()->user()->hasPermission('goods_receipts', 'create'))
                    <a class="button button-primary" href="{{ route('goods-receipts.create', $purchaseOrder->id) }}"><span aria-hidden="true">▣</span> Receive Goods</a>
                @endif
                @if (!empty($billingEnabled) && $purchaseOrder->status !== 'cancelled' && $purchaseOrder->goodsReceipts->isNotEmpty() && auth()->user()->hasPermission('bills', 'create'))
                    <a class="button button-light" href="{{ route('supplier-bills.create', $purchaseOrder->id) }}">Enter Supplier Bill</a>
                @endif
                @if (!$purchaseOrder->quotation)<a class="button button-light" href="{{ route('purchase-orders.index') }}">Back to list</a>@endif
            </div>
        </header>
        @if ($purchaseOrder->status === 'draft' && auth()->user()->hasPermission('purchase', 'approve'))
            <dialog class="po-release-dialog" id="release-po-modal">
                <div class="po-release-content">
                    <h2>Release Purchase Order?</h2>
                    <p>Once released, this purchase order can be used to receive goods from the supplier.</p>
                    <dl>
                        <div><dt>PO Number</dt><dd>{{ $purchaseOrder->po_number }}</dd></div>
                        <div><dt>Supplier</dt><dd>{{ optional($purchaseOrder->supplier)->company_name ?: optional($purchaseOrder->supplier)->name }}</dd></div>
                        <div><dt>Grand Total</dt><dd>₹{{ number_format((float) $purchaseOrder->grand_total, 2) }}</dd></div>
                    </dl>
                    <div class="po-release-actions">
                        <button class="button button-light" type="button" data-close-release>Cancel</button>
                        <form method="POST" action="{{ route('purchase-orders.release', $purchaseOrder->id) }}">@csrf<button class="button button-primary" type="submit">Release Purchase Order</button></form>
                    </div>
                </div>
            </dialog>
        @endif

        <section class="po-summary-grid" aria-label="Purchase order summary">
            <article class="po-summary-card"><span class="po-summary-icon"><svg viewBox="0 0 24 24"><path d="M3 4h2l2.2 11h11.5l2-8H6M9 19a1 1 0 1 0 0 .01M17 19a1 1 0 1 0 0 .01"/></svg></span><div><small>Total Order Value</small><strong>₹{{ number_format((float) $purchaseOrder->grand_total, 2) }}</strong></div></article>
            <article class="po-summary-card"><span class="po-summary-icon"><svg viewBox="0 0 24 24"><path d="m12 3 9 5-9 5-9-5 9-5ZM3 8v9l9 5 9-5V8M12 13v9"/></svg></span><div><small>Total Items</small><strong>{{ number_format($purchaseOrder->items->count()) }}</strong></div></article>
            <article class="po-summary-card"><span class="po-summary-icon"><svg viewBox="0 0 24 24"><path d="M5 4h14v16H5zM8 8h2v2H8zm6 0h2m-8 4h2v2H8zm6 0h2m-8 4h2m6 0h2"/></svg></span><div><small>Received Quantity</small><strong>{{ number_format((float) $receivedQuantity, 2) }}</strong></div></article>
            <article class="po-summary-card"><span class="po-summary-icon"><svg viewBox="0 0 24 24"><path d="M12 3v9l6 3M21 12a9 9 0 1 1-3-6.7"/></svg></span><div><small>Pending Value</small><strong class="po-summary-pending">₹{{ number_format((float) $remainingValue, 2) }}</strong></div></article>
        </section>

        <section class="po-summary-grid" aria-label="Payment summary">
            <article class="po-summary-card"><div><small>Total Payable</small><strong>₹{{ number_format((float) $purchaseOrder->grand_total, 2) }}</strong></div></article>
            <article class="po-summary-card"><div><small>Paid Amount</small><strong>₹{{ number_format($purchasePaid, 2) }}</strong></div></article>
            <article class="po-summary-card"><div><small>Outstanding Amount</small><strong class="po-summary-pending">₹{{ number_format($purchaseOutstanding, 2) }}</strong></div></article>
            <article class="po-summary-card"><div><small>Payment Status</small><strong>{{ $purchasePaymentStatus }}</strong></div></article>
        </section>

        <div class="po-information-grid">
            <section class="po-detail-panel">
                <div class="po-panel-heading"><span class="po-panel-icon"><svg viewBox="0 0 24 24"><path d="M3 21h18M5 21V5h14v16M8 8h2m4 0h2M8 12h2m4 0h2M8 16h2m4 0h2"/></svg></span><div><h2>Supplier Information</h2><p>Supplier details for this purchase order.</p></div></div>
                <div class="po-info-rows">
                    <div class="po-info-row"><span>Supplier</span><strong>{{ optional($purchaseOrder->supplier)->name ?: '—' }}</strong></div>
                    <div class="po-info-row"><span>Company</span><strong>{{ optional($purchaseOrder->supplier)->company_name ?: '—' }}</strong></div>
                    <div class="po-info-row"><span>Supplier Code</span><strong>{{ optional($purchaseOrder->supplier)->supplier_code ?: '—' }}</strong></div>
                    <div class="po-info-row"><span>Contact Person</span><strong>{{ optional($purchaseOrder->supplier)->name ?: '—' }}</strong></div>
                    <div class="po-info-row"><span>Phone</span><strong>{{ optional($purchaseOrder->supplier)->phone ?: '—' }}</strong></div>
                    <div class="po-info-row"><span>Email</span><strong>{{ optional($purchaseOrder->supplier)->email ?: '—' }}</strong></div>
                    <div class="po-info-row"><span>GSTIN / PAN</span><strong>{{ optional($purchaseOrder->supplier)->gst_number ?: '—' }} / {{ optional($purchaseOrder->supplier)->pan_number ?: '—' }}</strong></div>
                    <div class="po-info-row"><span>Dispatch Location</span><strong>{{ optional($purchaseOrder->supplier)->warehouse_location ?: '—' }}</strong></div>
                    <div class="po-info-row"><span>Address</span><strong>{{ collect([optional($purchaseOrder->supplier)->address, optional($purchaseOrder->supplier)->city, optional($purchaseOrder->supplier)->state, optional($purchaseOrder->supplier)->pincode])->filter()->implode(', ') ?: '—' }}</strong></div>
                </div>
            </section>

            <section class="po-detail-panel">
                <div class="po-panel-heading"><span class="po-panel-icon"><svg viewBox="0 0 24 24"><path d="M6 3h9l4 4v14H6zM14 3v5h5M9 13h7m-7 4h7"/></svg></span><div><h2>Order Information</h2><p>Dates, source and approval details.</p></div></div>
                <div class="po-info-rows">
                    <div class="po-info-row"><span>Purchase Order No.</span><strong>{{ $purchaseOrder->po_number }}</strong></div>
                    <div class="po-info-row"><span>Date</span><strong>{{ \Carbon\Carbon::parse($purchaseOrder->order_date)->format('d M Y') }}</strong></div>
                    @if ($purchaseOrder->expected_date)<div class="po-info-row"><span>Expected Date</span><strong>{{ \Carbon\Carbon::parse($purchaseOrder->expected_date)->format('d M Y') }}</strong></div>@endif
                    <div class="po-info-row"><span>Source Quotation</span><strong>@if ($purchaseOrder->quotation)<a href="{{ route('quotations.show', $purchaseOrder->quotation->quotation_request_id) }}">{{ $purchaseOrder->quotation->quotation_code }}</a>{{ optional($purchaseOrder->quotation->request)->quotation_request_code ? ', ' . $purchaseOrder->quotation->request->quotation_request_code : '' }}@else Created manually @endif</strong></div>
                    <div class="po-info-row"><span>Created By</span><strong>{{ optional($purchaseOrder->creator)->name ?: '—' }}</strong></div>
                    @if ($purchaseOrder->released_at)<div class="po-info-row"><span>Released</span><strong>{{ $purchaseOrder->released_at->format('d M Y, h:i A') }} · {{ optional($purchaseOrder->releaser)->name ?: '—' }}</strong></div>@endif
                    <div class="po-info-row"><span>Status</span><strong><span class="po-status-pill is-{{ $purchaseOrder->status }}">{{ $statusLabel }}</span>{{ $purchaseOrder->status_reason ? ' · ' . $purchaseOrder->status_reason : '' }}</strong></div>
                    <div class="po-info-row"><span>Remarks</span><strong>{{ $purchaseOrder->notes ?: '—' }}</strong></div>
                </div>
            </section>
        </div>

        <section class="po-detail-panel po-bank-panel">
            <div class="po-panel-heading"><span class="po-panel-icon green"><svg viewBox="0 0 24 24"><path d="M3 9h18L12 4 3 9Zm2 2v7m4-7v7m6-7v7m4-7v7M3 21h18"/></svg></span><div><h2>Bank &amp; Payment Details</h2><p>Supplier remittance and payment information.</p></div></div>
            <div class="po-info-rows">
                <div class="po-info-row"><span>Bank Name</span><strong>{{ optional($purchaseOrder->supplier)->bank_name ?: '—' }}</strong></div>
                <div class="po-info-row"><span>Account Holder</span><strong>{{ optional($purchaseOrder->supplier)->account_holder_name ?: '—' }}</strong></div>
                @php($supplierAccount = optional($purchaseOrder->supplier)->account_number)
                <div class="po-info-row"><span>Account Number</span><strong>@if ($supplierAccount){{ str_repeat('•', max(0, strlen($supplierAccount) - 4)) . substr($supplierAccount, -4) }} <details class="po-account-reveal"><summary>Show</summary><span>{{ $supplierAccount }}</span></details>@else — @endif</strong></div>
                <div class="po-info-row"><span>IFSC / Branch</span><strong>{{ optional($purchaseOrder->supplier)->ifsc_code ?: '—' }} / {{ optional($purchaseOrder->supplier)->branch_name ?: '—' }}</strong></div>
                <div class="po-info-row"><span>Payment Terms</span><strong>{{ optional($purchaseOrder->supplier)->payment_terms ? ucwords(str_replace('_', ' ', $purchaseOrder->supplier->payment_terms)) : '—' }}</strong></div>
                <div class="po-info-row"><span>Notes</span><strong>{{ optional($purchaseOrder->supplier)->notes ?: '—' }}</strong></div>
            </div>
        </section>

        <div class="po-inline-notice"><strong>Purchase orders do not increase current stock.</strong><small>Accepted quantities are added to Products and Current Stock after you post the Goods Received entry.</small></div>

        <section class="po-table-panel">
            <div class="po-table-heading"><div class="po-panel-heading"><span class="po-panel-icon green"><svg viewBox="0 0 24 24"><path d="m12 3 9 5-9 5-9-5 9-5ZM3 8v9l9 5 9-5V8M12 13v9"/></svg></span><div><h2>Order Items</h2><p>Products and quantities included in this order.</p></div></div></div>
            <div class="po-table-scroll">
                <table class="po-table">
                    <thead><tr><th>#</th><th>CATEGORY</th><th>PRODUCT</th><th>SKU</th><th>ORDERED</th><th>UNIT</th><th>RATE (₹)</th><th>DISCOUNT</th><th>GST %</th><th>TAX (₹)</th><th style="text-align:right">TOTAL (₹)</th></tr></thead>
                    <tbody>
                    <?php $poSubtotal = 0; $poTax = 0; ?>
                    <?php foreach ($purchaseOrder->items as $itemIndex => $item): ?>
                        <?php
                            $product = $item->product;
                            $ordered = (float) $item->ordered_quantity;
                            $lineSubtotal = $ordered * (float) $item->purchase_rate;
                            $lineDiscount = round($lineSubtotal * (float) $item->discount_percent / 100, 2);
                            $lineTax = round((float) $item->cgst_amount + (float) $item->sgst_amount, 2);
                            $lineTotal = (float) $item->total_amount ?: round($lineSubtotal - $lineDiscount + $lineTax, 2);
                            $poSubtotal += $lineSubtotal;
                            $poTax += $lineTax;
                        ?>
                        <tr>
                            <td>{{ $itemIndex + 1 }}</td>
                            <td>{{ optional(optional($product)->category)->name ?: '—' }}</td>
                            <td><span class="po-product-name">{{ optional($product)->name ?: 'Product unavailable' }}</span></td>
                            <td>{{ optional($product)->product_code ?: '—' }}</td>
                            <td>{{ number_format($ordered, 2) }}</td>
                            <td>{{ optional(optional($product)->unit)->short_name ?: optional(optional($product)->unit)->name ?: '—' }}</td>
                            <td>₹{{ number_format((float) $item->purchase_rate, 2) }}</td>
                            <td>{{ number_format((float) $item->discount_percent, 2) }}%<small class="po-product-sub">₹{{ number_format($lineDiscount, 2) }}</small></td>
                            <td>{{ number_format((float) $item->tax_percent, 2) }}%</td>
                            <td>₹{{ number_format($lineTax, 2) }}</td>
                            <td class="po-line-total">₹{{ number_format($lineTotal, 2) }}</td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr><td colspan="10" style="text-align:right">Subtotal (₹)</td><td class="po-line-total">₹{{ number_format($purchaseOrder->subtotal !== null ? (float) $purchaseOrder->subtotal : $poSubtotal, 2) }}</td></tr>
                        <tr><td colspan="10" style="text-align:right">Discount (₹)</td><td class="po-line-total">₹{{ number_format((float) $purchaseOrder->discount_amount, 2) }}</td></tr>
                        <tr><td colspan="10" style="text-align:right">Tax (₹)</td><td class="po-line-total">₹{{ number_format($purchaseOrder->tax_amount !== null ? (float) $purchaseOrder->tax_amount : $poTax, 2) }}</td></tr>
                        <tr><td colspan="10" style="text-align:right">Grand Total (₹)</td><td class="po-line-total">₹{{ number_format((float) $purchaseOrder->grand_total, 2) }}</td></tr>
                        <tr><td colspan="10" style="text-align:right">Paid Amount (₹)</td><td class="po-line-total">₹{{ number_format($purchasePaid, 2) }}</td></tr>
                        <tr><td colspan="10" style="text-align:right">Outstanding Amount (₹)</td><td class="po-line-total">₹{{ number_format($purchaseOutstanding, 2) }}</td></tr>
                    </tfoot>
                </table>
            </div>
        </section>

        @if ($purchaseOrder->payments->isNotEmpty())
            <section class="po-table-panel po-history-panel">
                <div class="po-table-heading"><div class="po-panel-heading"><span class="po-panel-icon violet"><svg viewBox="0 0 24 24"><path d="M3 6h18v12H3zM3 10h18m-13 4h4"/></svg></span><div><h2>Purchase Payment History</h2><p>Advance and negotiated purchase payments recorded against this PO.</p></div></div></div>
                <div class="po-table-scroll">
                    <table class="po-table" style="min-width:720px">
                        <thead><tr><th>#</th><th>PAYMENT DATE</th><th>AMOUNT</th><th>METHOD</th><th>REFERENCE</th><th>REMARKS</th><th>ENTERED BY</th><th>STATUS</th><th>ACTION</th></tr></thead>
                        <tbody>
                        @foreach ($purchaseOrder->payments->sortBy('payment_date') as $payment)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>{{ \Carbon\Carbon::parse($payment->payment_date)->format('d M Y') }}</td>
                                <td>₹{{ number_format($payment->amount, 2) }}</td>
                                <td>{{ $payment->payment_method }}</td>
                                <td>{{ $payment->reference_number ?: '—' }}</td>
                                <td>{{ $payment->remarks ?: '—' }}</td>
                                <td>{{ optional($payment->enteredBy)->name ?: '—' }}</td>
                                <td>@include('procurement._status', ['status' => $payment->status])</td>
                                <td><a class="button button-small button-light" href="{{ route('quotations.purchase-details', $purchaseOrder->quotation_id) }}">View Details</a></td>
                            </tr>
                        @endforeach
                        </tbody>
                        <tfoot><tr><td colspan="2">Total Paid</td><td>₹{{ number_format($purchasePaid, 2) }}</td><td colspan="3">Outstanding</td><td colspan="3">₹{{ number_format($purchaseOutstanding, 2) }}</td></tr></tfoot>
                    </table>
                </div>
            </section>
        @endif

        @if (!empty($billingEnabled) && auth()->user()->hasPermission('purchase', 'approve') && in_array($purchaseOrder->status, ['draft', 'released', 'ordered', 'pending', 'approved', 'partially_received', 'received'], true))
            <section class="po-control-panel">
                <div class="po-panel-heading"><span class="po-panel-icon orange"><svg viewBox="0 0 24 24"><path d="m12 3 2 2 3-.2.8 2.8 2.6 1.6-1 2.8 1 2.8-2.6 1.6L17 20l-3-.2-2 2-2-2-3 .2-.8-2.8-2.6-1.6 1-2.8-1-2.8 2.6L7 4.8 10 5z"/><circle cx="12" cy="13" r="3"/></svg></span><div><h2>Order Control</h2><p class="po-control-note">Additional actions for this purchase order.</p></div></div>
                <p class="po-control-note">Cancellation is allowed only if no goods are received or bills are created.</p>
                @if (in_array($purchaseOrder->status, ['draft', 'released', 'ordered', 'pending', 'approved'], true) && $purchaseOrder->goodsReceipts->isEmpty())
                    <form class="inline-form" method="POST" action="{{ route('purchase-orders.cancel', $purchaseOrder->id) }}" data-submit-once>@csrf<input class="field-control" type="text" name="reason" placeholder="Cancellation reason" required><button class="button button-small button-danger" type="submit">Cancel Purchase Order</button></form>
                @endif
                @if (in_array($purchaseOrder->status, ['partially_received', 'received'], true))
                    <form class="inline-form" method="POST" action="{{ route('purchase-orders.close', $purchaseOrder->id) }}" data-submit-once>@csrf<input class="field-control" type="text" name="reason" placeholder="Closing reason" required><button class="button button-small button-light" type="submit">Close Purchase Order</button></form>
                @endif
            </section>
        @endif

        @if (!empty($billingEnabled))
            <section class="po-table-panel po-history-panel">
                <div class="po-table-heading"><div class="po-panel-heading"><span class="po-panel-icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg></span><div><h2>Audit History</h2><p>Recorded automatically; entries cannot be edited or deleted.</p></div></div></div>
                <div class="po-table-scroll">
                    <table class="po-table" style="min-width:700px">
                        <thead><tr><th>WHEN</th><th>EVENT</th><th>STATUS</th><th>BY</th><th>REASON / DETAILS</th></tr></thead>
                        <tbody>
                        @forelse ($history as $entry)
                            <tr>
                                <td>{{ optional($entry->created_at)->format('d M Y H:i') }}</td>
                                <td>{{ ucfirst(str_replace('_', ' ', $entry->event)) }}</td>
                                <td>{{ $entry->from_status ? str_replace('_', ' ', $entry->from_status) . ' → ' : '' }}{{ $entry->to_status ? str_replace('_', ' ', $entry->to_status) : '—' }}</td>
                                <td>{{ $entry->user_name ?: '—' }}</td>
                                <td>{{ $entry->reason ?: '—' }}@if ($entry->meta)<small class="field-hint">@foreach ($entry->meta as $key => $value)@if (!is_array($value) && $value !== null && $value !== ''){{ str_replace('_', ' ', $key) }}: {{ $value }}@if (!$loop->last) · @endif @endif @endforeach</small>@endif</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="po-table-empty">No audit entries yet.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
            @include('procurement._submit_once')
        @endif
    </main>
    @if ($purchaseOrder->status === 'draft' && auth()->user()->hasPermission('purchase', 'approve'))
        <script>
            (function () {
                var dialog = document.getElementById('release-po-modal');
                var openButton = document.querySelector('[data-open-release]');
                var closeButton = document.querySelector('[data-close-release]');
                if (dialog && openButton && typeof dialog.showModal === 'function') {
                    openButton.addEventListener('click', function () { dialog.showModal(); });
                    closeButton.addEventListener('click', function () { dialog.close(); });
                    dialog.addEventListener('click', function (event) {
                        if (event.target === dialog) dialog.close();
                    });
                }
            }());
        </script>
    @endif
@endsection
