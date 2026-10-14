@extends('layouts.app')

@section('title', $supplier->name . ' · Supplier')
@section('topbar-title', 'Supplier details')

@section('content')
    <style>
        .supplier-profile-tabs{display:flex;gap:6px;overflow-x:auto;margin:0 0 14px;padding:5px;border:1px solid #e7edf3;border-radius:10px;background:#fff}
        .supplier-profile-tabs a{padding:9px 13px;border-radius:7px;color:#65788e;font-size:11px;font-weight:600;text-decoration:none;white-space:nowrap}
        .supplier-profile-tabs a:hover,.supplier-profile-tabs a:focus{background:#edf6f8;color:#087f82}
        .supplier-history-summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:9px;margin:0 0 14px}
        .supplier-history-kpi{padding:12px;border:1px solid #e8edf2;border-radius:9px;background:#fff}
        .supplier-history-kpi small,.supplier-history-kpi strong{display:block}
        .supplier-history-kpi small{margin-bottom:6px;color:#738398;font-size:10px}
        .supplier-history-kpi strong{color:#19385e;font-size:16px}
        @media(max-width:700px){.supplier-history-summary{grid-template-columns:repeat(2,minmax(0,1fr))}}
    </style>
    <div class="page-heading">
        <div><span class="section-kicker">PARTNERS / SUPPLIERS</span><h1>{{ $supplier->company_name ?: $supplier->name }}</h1><p>Read-only supplier profile and business information.</p></div>
        <a class="button button-light" href="{{ route('suppliers.index') }}">Back to Suppliers</a>
    </div>
    <nav class="supplier-profile-tabs" aria-label="Supplier details sections">
        <a href="#supplier-overview">Overview</a>
        @if ($canViewPurchaseHistory)<a href="#supplier-purchase-history">Purchase History</a>@endif
        <a href="#supplier-documents">Documents</a>
    </nav>
    <section class="panel workspace-panel">
        <div class="form-section" id="supplier-overview">
            <div class="form-card-heading"><span class="form-section-icon"><svg><use href="#icon-users"></use></svg></span><div><h2>Basic Information</h2><p>Supplier identity and primary contact details.</p></div></div>
            <div class="form-grid">
                <div class="field"><label>Supplier Code</label><div class="product-identifier-display">{{ $supplier->supplier_code ?: '—' }}</div></div>
                <div class="field"><label>Supplier / Contact Name</label><div class="product-identifier-display">{{ $supplier->name ?: '—' }}</div></div>
                <div class="field"><label>Company Name</label><div class="product-identifier-display">{{ $supplier->company_name ?: '—' }}</div></div>
                <div class="field"><label>Phone / Mobile</label><div class="product-identifier-display">{{ $supplier->phone ?: '—' }}</div></div>
                <div class="field"><label>Email</label><div class="product-identifier-display">{{ $supplier->email ?: '—' }}</div></div>
                <div class="field"><label>Supplier Warehouse / Dispatch Location</label><div class="product-identifier-display">{{ $supplier->warehouse_location ?: '—' }}</div></div>
                <div class="field"><label>Supplier Status</label><div class="product-identifier-display">{{ $supplier->is_active ? 'Active' : 'Inactive' }}</div></div>
            </div>
        </div>

        <div class="form-section">
            <div class="form-card-heading"><span class="form-section-icon form-icon-gold"><svg><use href="#icon-layers"></use></svg></span><div><h2>Tax and Address</h2><p>Registered tax details and supplier address.</p></div></div>
            <div class="form-grid">
                <div class="field"><label>GSTIN</label><div class="product-identifier-display">{{ $supplier->gst_number ?: '—' }}</div></div>
                <div class="field"><label>PAN</label><div class="product-identifier-display">{{ $supplier->pan_number ?: '—' }}</div></div>
                <div class="field field-wide"><label>Address</label><div class="product-identifier-display">{{ $supplier->address ?: '—' }}</div></div>
                <div class="field"><label>City</label><div class="product-identifier-display">{{ $supplier->city ?: '—' }}</div></div>
                <div class="field"><label>State</label><div class="product-identifier-display">{{ $supplier->state ?: '—' }}</div></div>
                <div class="field"><label>Pincode</label><div class="product-identifier-display">{{ $supplier->pincode ?: '—' }}</div></div>
            </div>
        </div>

        @if ($canViewPurchaseHistory)
            <div class="form-section" id="supplier-purchase-history">
                <div class="form-card-heading"><span class="form-section-icon"><svg><use href="#icon-tray-in"></use></svg></span><div><h2>Purchase History</h2><p>Purchase orders associated with this supplier.</p></div></div>
                <div class="supplier-history-summary">
                    <div class="supplier-history-kpi"><small>Total Purchase Orders</small><strong>{{ number_format($purchaseSummary['count']) }}</strong></div>
                    <div class="supplier-history-kpi"><small>Total Purchase Value</small><strong>₹{{ number_format((float) $purchaseSummary['value'], 2) }}</strong></div>
                    <div class="supplier-history-kpi"><small>Pending Orders</small><strong>{{ number_format($purchaseSummary['pending']) }}</strong></div>
                    <div class="supplier-history-kpi"><small>Last Purchase</small><strong>{{ $purchaseSummary['last_date'] ? \Carbon\Carbon::parse($purchaseSummary['last_date'])->format('d M Y') : '—' }}</strong></div>
                </div>
                <div class="table-wrap">
                    <table class="data-table listing-table">
                        <thead><tr><th>#</th><th>PO NUMBER</th><th>DATE</th><th>ITEMS</th><th>TOTAL AMOUNT</th><th>PAID AMOUNT</th><th>OUTSTANDING</th><th>STATUS</th><th>ACTION</th></tr></thead>
                        <tbody>
                        @forelse ($purchaseOrders as $order)
                            <tr>
                                <td>{{ $purchaseOrders->firstItem() + $loop->index }}</td>
                                <td><span class="unit-code">{{ $order->po_number }}</span></td>
                                <td>{{ \Carbon\Carbon::parse($order->po_date)->format('d M Y') }}</td>
                                <td>{{ number_format($order->items_count) }}</td>
                                <td>₹{{ number_format((float) $order->grand_total, 2) }}</td>
                                <td>₹{{ number_format((float) $order->paid_total, 2) }}</td>
                                <td>₹{{ number_format(max(0, (float) $order->grand_total - (float) $order->paid_total), 2) }}</td>
                                <td>@include('procurement._status', ['status' => $order->status])</td>
                                <td><a class="button button-small button-light" href="{{ route('purchase-orders.show', $order->id) }}">View</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="9">No purchase orders for this supplier.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($purchaseOrders->hasPages())<div class="workspace-footer">{{ $purchaseOrders->fragment('supplier-purchase-history')->links() }}</div>@endif
            </div>
        @endif

        <div class="form-section">
            <div class="form-card-heading"><span class="form-section-icon"><svg><use href="#icon-calendar"></use></svg></span><div><h2>Payment and Bank Information</h2><p>Saved payment terms and bank account details.</p></div></div>
            <div class="form-grid">
                <div class="field"><label>Payment Terms</label><div class="product-identifier-display">{{ $supplier->payment_terms ? ucwords(str_replace('_', ' ', $supplier->payment_terms)) : '—' }}</div></div>
                <div class="field"><label>Bank Name</label><div class="product-identifier-display">{{ $supplier->bank_name ?: '—' }}</div></div>
                <div class="field"><label>Account Holder Name</label><div class="product-identifier-display">{{ $supplier->account_holder_name ?: '—' }}</div></div>
                <div class="field"><label>Account Number</label><div class="product-identifier-display">{{ $supplier->account_number ?: '—' }}</div></div>
                <div class="field"><label>IFSC Code</label><div class="product-identifier-display">{{ $supplier->ifsc_code ?: '—' }}</div></div>
                <div class="field"><label>Branch Name</label><div class="product-identifier-display">{{ $supplier->branch_name ?: '—' }}</div></div>
                <div class="field field-wide"><label>Notes / Remarks</label><div class="product-identifier-display">{{ $supplier->notes ?: '—' }}</div></div>
            </div>
        </div>

        <div class="form-section" id="supplier-documents">
            <div class="form-card-heading"><span class="form-section-icon form-icon-violet"><svg><use href="#icon-box"></use></svg></span><div><h2>Supplier Documents</h2><p>Securely stored supplier identity documents.</p></div></div>
            <div class="form-grid">
                @foreach (['aadhaar_card' => 'Aadhaar Card', 'pan_card' => 'PAN Card'] as $document => $label)
                    <div class="field">
                        <label>{{ $label }}</label>
                        <div class="product-identifier-display">
                            @if ($supplier->{$document})
                                <a href="{{ route('suppliers.documents.show', [$supplier->id, $document]) }}" target="_blank" rel="noopener">View {{ $label }}</a>
                                <a href="{{ route('suppliers.documents.show', [$supplier->id, $document, 'download' => 1]) }}">Download</a>
                            @else
                                —
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="form-section">
            <div class="form-grid">
                <div class="field"><label>Created Date</label><div class="product-identifier-display">{{ optional($supplier->created_at)->format('d M Y, h:i A') ?: '—' }}</div></div>
                <div class="field"><label>Updated Date</label><div class="product-identifier-display">{{ optional($supplier->updated_at)->format('d M Y, h:i A') ?: '—' }}</div></div>
            </div>
        </div>
    </section>
@endsection
