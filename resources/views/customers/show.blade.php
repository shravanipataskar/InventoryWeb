@extends('layouts.app')

@section('title', $customer->name)
@section('topbar-title', 'Customer details')

@section('content')
    <style>
        .customer-detail-page{max-width:1280px;margin:0 auto;padding-bottom:24px}
        .customer-detail-page .customer-detail-back{display:inline-flex;align-items:center;gap:8px;margin:0 0 12px;color:#53677d;font-size:12px}
        .customer-detail-page .customer-detail-back:hover{color:#108b8c}
        .customer-detail-heading{display:flex;align-items:center;justify-content:space-between;gap:20px;margin-bottom:18px}
        .customer-detail-title{display:flex;align-items:center;gap:16px;min-width:0}
        .customer-detail-avatar{display:grid;width:58px;height:58px;flex:0 0 58px;place-items:center;border-radius:50%;background:#e3f0ff;color:#1478d4}
        .customer-detail-avatar svg{width:28px;height:28px;fill:none;stroke:currentColor;stroke-linecap:round;stroke-linejoin:round;stroke-width:1.8}
        .customer-detail-title h1{display:flex;align-items:center;flex-wrap:wrap;gap:10px;margin:0;color:#102b4e;font-size:22px}
        .customer-detail-title p{margin:6px 0 0;color:#657790;font-size:12px}
        .customer-detail-code{display:inline-flex;align-items:center;gap:7px;margin-top:8px;color:#53677d;font-size:11px}
        .customer-detail-actions{display:flex;align-items:center;gap:8px;flex-shrink:0}
        .customer-status-pill{display:inline-flex;align-items:center;padding:5px 11px;border-radius:20px;background:#e4f8ee;color:#178347;font-size:11px;font-weight:700}
        .customer-status-pill.is-inactive{background:#fff1e8;color:#bd5c17}
        .customer-detail-summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin-bottom:16px;padding:5px;border-radius:12px;background:#fff}
        .customer-detail-stat{display:flex;align-items:center;gap:12px;min-width:0;padding:12px;border-radius:9px;background:#eef6ff}
        .customer-detail-stat:nth-child(2){background:#eafaf3}
        .customer-detail-stat:nth-child(3){background:#f4efff}
        .customer-detail-stat:nth-child(4){background:#fff6e9}
        .customer-detail-stat-icon{display:grid;width:36px;height:36px;flex:0 0 36px;place-items:center;border-radius:10px;background:rgba(255,255,255,.75);color:#1682d4}
        .customer-detail-stat:nth-child(2) .customer-detail-stat-icon{color:#12945c}
        .customer-detail-stat:nth-child(3) .customer-detail-stat-icon{color:#8053d4}
        .customer-detail-stat:nth-child(4) .customer-detail-stat-icon{color:#d77a0a}
        .customer-detail-stat-icon svg{width:18px;height:18px;fill:none;stroke:currentColor;stroke-linecap:round;stroke-linejoin:round;stroke-width:1.8}
        .customer-detail-stat small,.customer-detail-stat strong{display:block}
        .customer-detail-stat small{margin-bottom:4px;color:#697b91;font-size:10px}
        .customer-detail-stat strong{overflow:hidden;color:#12335d;font-size:13px;text-overflow:ellipsis;white-space:nowrap}
        .customer-detail-columns{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;margin-bottom:14px}
        .customer-detail-card{min-width:0;padding:14px;border:1px solid #e9eef4;border-radius:11px;background:#fff;box-shadow:0 3px 12px rgba(31,55,84,.035)}
        .customer-detail-card-heading{display:flex;align-items:center;gap:10px;padding-bottom:10px;border-bottom:1px solid #edf1f5}
        .customer-detail-card-heading h2{margin:0;color:#17375f;font-size:13px}
        .customer-detail-card-heading p{margin:3px 0 0;color:#7b8a9b;font-size:10px}
        .customer-detail-card-icon{display:grid;width:29px;height:29px;flex:0 0 29px;place-items:center;border-radius:8px;background:#e7f7f4;color:#128b87}
        .customer-detail-card-icon.blue{background:#e9f3ff;color:#2481d8}
        .customer-detail-card-icon.violet{background:#f2ebff;color:#8754d6}
        .customer-detail-card-icon svg{width:16px;height:16px;fill:none;stroke:currentColor;stroke-linecap:round;stroke-linejoin:round;stroke-width:1.8}
        .customer-detail-rows{margin-top:4px}
        .customer-detail-row{display:grid;grid-template-columns:minmax(130px,.8fr) minmax(0,1.4fr);gap:12px;align-items:center;min-height:31px;padding:6px 2px;border-bottom:1px solid #eff2f6;font-size:11px}
        .customer-detail-row:last-child{border-bottom:0}
        .customer-detail-row-label{display:flex;align-items:center;gap:8px;color:#60738b}
        .customer-detail-row-label svg{width:13px;height:13px;flex:0 0 13px;fill:none;stroke:#5b718a;stroke-linecap:round;stroke-linejoin:round;stroke-width:1.8}
        .customer-detail-row-value{overflow-wrap:anywhere;color:#19395f;font-weight:500}
        .customer-document-list{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-top:12px}
        .customer-document-item{min-width:0;padding:10px;border:1px solid #e8edf3;border-radius:9px}
        .customer-document-item h3{margin:0 0 8px;color:#17375f;font-size:11px}
        .customer-document-content{display:flex;align-items:center;gap:12px;min-height:88px}
        .customer-document-thumb{width:clamp(115px,18vw,190px);height:88px;flex:0 1 190px;border:1px solid #e2e8f0;border-radius:6px;object-fit:cover;background:#f8fafc}
        .customer-document-actions{display:flex;flex-direction:column;align-items:flex-start;gap:6px}
        .customer-document-actions .button{min-width:72px;justify-content:center}
        .customer-document-uploaded{margin:7px 0 0;color:#76879c;font-size:10px}
        .customer-document-missing{display:grid;min-height:88px;place-items:center;border:1px dashed #dce4ec;border-radius:7px;color:#8795a5;font-size:11px}
        .customer-history-heading{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:10px}
        .customer-history-heading .customer-detail-card-heading{padding-bottom:0;border:0}
        .customer-history-table-wrap{overflow-x:auto}
        .customer-history-table{width:100%;min-width:760px;border-collapse:collapse}
        .customer-history-table th{padding:9px 8px;background:#f7f9fc;color:#718198;font-size:9px;font-weight:700;text-align:left;white-space:nowrap}
        .customer-history-table td{padding:10px 8px;border-top:1px solid #edf1f5;color:#19395f;font-size:10px}
        .customer-history-empty{padding:22px 12px;text-align:center}
        .customer-history-empty h3{margin:0 0 5px;color:#19395f;font-size:13px}
        .customer-history-empty p{margin:0 0 12px;color:#718198;font-size:11px}
        .customer-document-modal{position:fixed;z-index:1000;inset:0;display:grid;place-items:center;padding:20px;background:rgba(15,23,42,.78)}
        .customer-document-modal[hidden]{display:none}
        .customer-document-modal-content{position:relative;max-width:min(92vw,900px);max-height:90vh;padding:18px;border-radius:12px;background:#fff;box-shadow:0 20px 60px rgba(0,0,0,.25)}
        .customer-document-modal-content img{display:block;max-width:100%;max-height:calc(90vh - 90px);margin:auto;border-radius:6px;object-fit:contain}
        .customer-document-modal-heading{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:12px}
        .customer-document-modal-close{border:0;border-radius:6px;padding:7px 11px;background:#eef2f5;color:#243746;cursor:pointer}
        @media(max-width:900px){.customer-detail-summary{grid-template-columns:repeat(2,minmax(0,1fr))}.customer-document-thumb{width:130px}}
        @media(max-width:700px){.customer-detail-heading{align-items:flex-start;flex-direction:column}.customer-detail-actions{width:100%;justify-content:flex-end}.customer-detail-columns,.customer-document-list{grid-template-columns:1fr}.customer-detail-summary{gap:7px}.customer-detail-stat{padding:9px}.customer-detail-row{grid-template-columns:minmax(112px,.8fr) minmax(0,1.2fr)}.customer-document-thumb{width:clamp(120px,40vw,190px)}.customer-document-modal{padding:12px}.customer-document-modal-content{padding:12px}}
        @media(max-width:420px){.customer-detail-summary{grid-template-columns:1fr 1fr}.customer-detail-stat{gap:8px;padding:8px}.customer-detail-stat-icon{width:30px;height:30px;flex-basis:30px}.customer-detail-row{grid-template-columns:1fr;gap:3px}.customer-document-content{align-items:flex-start;flex-direction:column}.customer-document-actions{flex-direction:row}}
        @media print{.customer-detail-page .customer-detail-back,.customer-detail-actions,.customer-history-heading>.button,.customer-document-actions,.customer-document-modal{display:none!important}.customer-detail-page{max-width:none}.customer-detail-card{box-shadow:none;break-inside:avoid}.customer-detail-columns{grid-template-columns:1fr 1fr}}
    </style>

    <main class="customer-detail-page">
        <a class="customer-detail-back" href="{{ route('customers.index') }}"><span aria-hidden="true">←</span> Back to customers</a>

        <header class="customer-detail-heading">
            <div class="customer-detail-title">
                <span class="customer-detail-avatar" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M3 21h18M5 21V6l7-3 7 3v15M9 21v-5h6v5M8 9h1m6 0h1m-8 4h1m6 0h1"/></svg></span>
                <div>
                    <h1>{{ $customer->name }} <span class="customer-status-pill {{ $customer->is_active ? '' : 'is-inactive' }}">{{ $customer->is_active ? 'Active' : 'Inactive' }}</span></h1>
                    <span class="customer-detail-code"><svg viewBox="0 0 24 24" width="13" height="13" aria-hidden="true"><rect x="4" y="4" width="16" height="16" rx="2" fill="none" stroke="currentColor"/><path d="M8 8h8M8 12h8M8 16h4" fill="none" stroke="currentColor"/></svg>{{ $customer->customer_code ?: '—' }} <span aria-hidden="true">·</span> {{ $customer->customer_type }} customer</span>
                </div>
            </div>
            <div class="customer-detail-actions">
                <button class="button button-light" type="button" onclick="window.print()"><span aria-hidden="true">▣</span> Print</button>
                <a class="button button-primary" href="{{ route('customers.edit', $customer->id) }}"><span aria-hidden="true">✎</span> Edit Customer</a>
            </div>
        </header>

        <section class="customer-detail-summary" aria-label="Customer summary">
            <article class="customer-detail-stat"><span class="customer-detail-stat-icon"><svg viewBox="0 0 24 24"><path d="M4 20V7l8-4 8 4v13M2 20h20M9 20v-6h6v6M8 9h1m6 0h1m-8 3h1m6 0h1"/></svg></span><div><small>Customer Type</small><strong>{{ $customer->customer_type }}</strong></div></article>
            <article class="customer-detail-stat"><span class="customer-detail-stat-icon"><svg viewBox="0 0 24 24"><path d="M4 8h16v12H4zM8 8V5h8v3M8 13h8M10 17h4"/></svg></span><div><small>Stock Issues</small><strong>{{ number_format($stockOutwards->total()) }}</strong></div></article>
            <article class="customer-detail-stat"><span class="customer-detail-stat-icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21v-2a8 8 0 0 1 16 0v2z"/></svg></span><div><small>Status</small><strong>{{ $customer->is_active ? 'Active' : 'Inactive' }}</strong></div></article>
            <article class="customer-detail-stat"><span class="customer-detail-stat-icon"><svg viewBox="0 0 24 24"><path d="M6 3h9l4 4v14H6zM14 3v5h5M9 13h7m-7 4h7"/></svg></span><div><small>Customer Code</small><strong>{{ $customer->customer_code ?: '—' }}</strong></div></article>
        </section>

        <div class="customer-detail-columns">
            <section class="customer-detail-card">
                <div class="customer-detail-card-heading"><span class="customer-detail-card-icon"><svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="8" cy="11" r="2"/><path d="M5 16c1-2 5-2 6 0m3-5h4m-4 4h4"/></svg></span><div><h2>Contact Information</h2><p>Saved customer contact details.</p></div></div>
                <div class="customer-detail-rows">
                    <div class="customer-detail-row"><span class="customer-detail-row-label"><svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21v-2a8 8 0 0 1 16 0v2z"/></svg>Contact Person</span><span class="customer-detail-row-value">{{ $customer->contact_person ?: '—' }}</span></div>
                    <div class="customer-detail-row"><span class="customer-detail-row-label"><svg viewBox="0 0 24 24"><path d="M5 3h4l2 5-3 2a15 15 0 0 0 6 6l2-3 5 2v4c0 1-1 2-2 2C10 20 4 14 3 5c0-1 1-2 2-2z"/></svg>Phone / Mobile</span><span class="customer-detail-row-value">{{ $customer->phone ?: '—' }}</span></div>
                    <div class="customer-detail-row"><span class="customer-detail-row-label"><svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m4 7 8 6 8-6"/></svg>Email</span><span class="customer-detail-row-value">{{ $customer->email ?: '—' }}</span></div>
                    <div class="customer-detail-row"><span class="customer-detail-row-label"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a15 15 0 0 1 0 18M12 3a15 15 0 0 0 0 18"/></svg>Store / Warehouse Location</span><span class="customer-detail-row-value">{{ $customer->store_warehouse_location ?: '—' }}</span></div>
                </div>
            </section>

            <section class="customer-detail-card">
                <div class="customer-detail-card-heading"><span class="customer-detail-card-icon blue"><svg viewBox="0 0 24 24"><path d="M20 10c0 5-8 12-8 12S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/></svg></span><div><h2>Address Information</h2><p>Customer business and postal address.</p></div></div>
                <div class="customer-detail-rows">
                    <div class="customer-detail-row"><span class="customer-detail-row-label"><svg viewBox="0 0 24 24"><path d="m3 11 9-8 9 8M5 10v11h14V10m-9 11v-7h4v7"/></svg>Address</span><span class="customer-detail-row-value">{{ $customer->address ?: '—' }}</span></div>
                    <div class="customer-detail-row"><span class="customer-detail-row-label"><svg viewBox="0 0 24 24"><path d="M3 21h18M5 21V5h14v16M8 8h2m4 0h2M8 12h2m4 0h2"/></svg>City</span><span class="customer-detail-row-value">{{ $customer->city ?: '—' }}</span></div>
                    <div class="customer-detail-row"><span class="customer-detail-row-label"><svg viewBox="0 0 24 24"><path d="M4 20V4h16v16M8 8h8m-8 4h8m-8 4h5"/></svg>State</span><span class="customer-detail-row-value">{{ $customer->state ?: '—' }}</span></div>
                    <div class="customer-detail-row"><span class="customer-detail-row-label"><svg viewBox="0 0 24 24"><path d="M4 7h16v10H4zM8 10h3m-3 4h5m3-4h1"/></svg>Pincode</span><span class="customer-detail-row-value">{{ $customer->pincode ?: '—' }}</span></div>
                    <div class="customer-detail-row"><span class="customer-detail-row-label"><svg viewBox="0 0 24 24"><path d="M4 5h16v14H4zM8 9h8m-8 4h8"/></svg>GSTIN</span><span class="customer-detail-row-value">{{ $customer->gstin ?: '—' }}</span></div>
                    <div class="customer-detail-row"><span class="customer-detail-row-label"><svg viewBox="0 0 24 24"><path d="M4 5h16v14H4zM8 9h8m-8 4h8"/></svg>PAN</span><span class="customer-detail-row-value">{{ $customer->pan ?: '—' }}</span></div>
                </div>
            </section>
        </div>

        <section class="customer-detail-card" style="margin-bottom:14px">
            <div class="customer-detail-card-heading"><span class="customer-detail-card-icon blue"><svg viewBox="0 0 24 24"><path d="M6 3h9l4 4v14H6zM14 3v5h5M9 13h7m-7 4h7"/></svg></span><div><h2>Identity &amp; Documents</h2><p>Customer identity documents for verification.</p></div></div>
            <div class="customer-document-list">
                @foreach (['aadhaar_card' => 'Aadhaar Card', 'pan_card' => 'PAN Card'] as $document => $label)
                    <article class="customer-document-item">
                        <h3>{{ $label }}</h3>
                        @if ($customer->{$document} && \Illuminate\Support\Facades\Storage::disk('local')->exists($customer->{$document}))
                            @php $uploadedOn = \Carbon\Carbon::createFromTimestamp(\Illuminate\Support\Facades\Storage::disk('local')->lastModified($customer->{$document})); @endphp
                            <div class="customer-document-content">
                                <img class="customer-document-thumb" src="{{ route('customers.documents.show', [$customer->id, $document]) }}" alt="{{ $label }} preview">
                                <div>
                                    <div class="customer-document-actions">
                                        <a class="button button-small button-light" href="{{ route('customers.documents.show', [$customer->id, $document]) }}" data-document-preview data-document-title="{{ $label }}"><span aria-hidden="true">◎</span> View</a>
                                        <a class="button button-small button-light" href="{{ route('customers.documents.show', [$customer->id, $document, 'download' => 1]) }}"><span aria-hidden="true">↓</span> Download</a>
                                    </div>
                                    <p class="customer-document-uploaded">Uploaded on {{ $uploadedOn->format('d M Y') }}</p>
                                </div>
                            </div>
                        @else
                            <div class="customer-document-missing">Not uploaded</div>
                        @endif
                    </article>
                @endforeach
            </div>
        </section>

        <section class="customer-detail-card">
            <div class="customer-history-heading">
                <div class="customer-detail-card-heading"><span class="customer-detail-card-icon violet"><svg viewBox="0 0 24 24"><path d="M4 9h16v11H4zM12 3v9m-3-3 3 3 3-3M4 14h5l2 3h2l2-3h5"/></svg></span><div><h2>Stock Issues / Transaction History</h2><p>Stock outward records for this customer.</p></div></div>
                <a class="button button-primary button-small" href="{{ route('stock-outwards.create', ['customer_id' => $customer->id]) }}"><span class="button-plus">+</span> Record Stock Outward</a>
            </div>
            @if ($stockOutwards->count())
                <div class="customer-history-table-wrap">
                    <table class="customer-history-table">
                        <thead><tr><th>#</th><th>DATE</th><th>PRODUCT</th><th>QUANTITY</th><th>UNIT</th><th>LOCATION</th><th>PURPOSE</th><th>REFERENCE NUMBER</th></tr></thead>
                        <tbody>
                        @foreach ($stockOutwards as $stock)
                            @php
                                $product = $stock->product;
                                $location = $product ? collect([
                                    optional($product->hall)->name,
                                    optional($product->rack)->name,
                                    optional($product->shelf)->name,
                                ])->filter()->implode(' / ') : '';
                            @endphp
                            <tr>
                                <td>{{ $stockOutwards->firstItem() + $loop->index }}</td>
                                <td>{{ \Carbon\Carbon::parse($stock->outward_date)->format('d-M-Y') }}</td>
                                <td>{{ $product ? $product->name : '—' }}</td>
                                <td>{{ number_format($stock->quantity, 2) }}</td>
                                <td>{{ optional(optional($product)->unit)->short_name ?: '—' }}</td>
                                <td>{{ $location ?: '—' }}</td>
                                <td>{{ $stock->remarks ?: '—' }}</td>
                                <td>{{ $stock->reference_number ?: '—' }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($stockOutwards->hasPages())
                    <footer class="workspace-footer"><span>Showing {{ number_format($stockOutwards->firstItem()) }}–{{ number_format($stockOutwards->lastItem()) }} of {{ number_format($stockOutwards->total()) }} stock issues</span><div class="workspace-pagination">{{ $stockOutwards->links() }}</div></footer>
                @endif
            @else
                <div class="customer-history-empty"><h3>No stock issues yet</h3><p>Stock outward transactions for {{ $customer->name }} will appear here.</p><a class="button button-primary button-small" href="{{ route('stock-outwards.create', ['customer_id' => $customer->id]) }}">Record Stock Outward</a></div>
            @endif
        </section>
    </main>

    <div class="customer-document-modal" data-document-modal role="dialog" aria-modal="true" aria-label="Customer document preview" hidden>
        <div class="customer-document-modal-content">
            <div class="customer-document-modal-heading"><strong data-document-modal-title></strong><button class="customer-document-modal-close" type="button" data-document-modal-close aria-label="Close preview">Close</button></div>
            <img src="" alt="" data-document-modal-image>
        </div>
    </div>
    <script>
        (function () {
            var modal = document.querySelector('[data-document-modal]');
            if (!modal) return;
            var image = modal.querySelector('[data-document-modal-image]');
            var title = modal.querySelector('[data-document-modal-title]');
            var closeButton = modal.querySelector('[data-document-modal-close]');

            function closePreview() {
                modal.hidden = true;
                image.src = '';
                document.body.style.overflow = '';
            }

            document.querySelectorAll('[data-document-preview]').forEach(function (link) {
                link.addEventListener('click', function (event) {
                    event.preventDefault();
                    image.src = link.href;
                    image.alt = link.getAttribute('data-document-title');
                    title.textContent = link.getAttribute('data-document-title');
                    modal.hidden = false;
                    document.body.style.overflow = 'hidden';
                    closeButton.focus();
                });
            });

            closeButton.addEventListener('click', closePreview);
            modal.addEventListener('click', function (event) {
                if (event.target === modal) closePreview();
            });
            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && !modal.hidden) closePreview();
            });
        })();
    </script>
@endsection
