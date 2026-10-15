@extends('layouts.app')

@section('title', $quotationRequest->quotation_request_code)
@section('topbar-title', 'Supplier quotations')

@section('content')
    <div class="page-heading">
        <div>
            <span class="section-kicker">PURCHASING / QUOTATIONS</span>
            <h1>{{ $quotationRequest->quotation_request_code }}</h1>
            <p>
                Request Date: {{ \Carbon\Carbon::parse($quotationRequest->request_date)->format('d M Y') }}
                <span aria-hidden="true">·</span>
                Required Date: {{ $quotationRequest->required_date ? \Carbon\Carbon::parse($quotationRequest->required_date)->format('d M Y') : '—' }}
                <span aria-hidden="true">·</span>
                {{ optional($quotationRequest->store)->name }}
                <span aria-hidden="true">·</span>
                {{ ucfirst(str_replace('_', ' ', $quotationRequest->status)) }}
            </p>
        </div>
        <a class="button button-primary" href="{{ route('quotations.index') }}">Back to quotations</a>
    </div>
    @include('components.flash')
    @if ($errors->any())<div class="form-alert" role="alert"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @php($canSubmitAll = $quotationRequest->quotations->contains(function ($quotation) {
        return $quotation->status === 'draft' && auth()->user()->hasPermission('quotations', 'edit');
    }))
    @php($hasApprovedQuotation = $quotationRequest->quotations->contains(function ($quotation) {
        return $quotation->status === 'approved';
    }))
    @if ($canSubmitAll)<form id="submit-all-quotations" method="POST" enctype="multipart/form-data" action="{{ route('quotations.request.submit', $quotationRequest->id) }}">@csrf</form>@endif
    @foreach ($quotationRequest->quotations as $quotation)
        @if ($quotation->status !== 'rejected' || !$hasApprovedQuotation)
        <section class="quote-review-card">
            <header class="quote-review-header">
                <div class="quote-review-title">
                    <h2>{{ $quotation->quotation_code }} <span>·</span> {{ optional($quotation->supplier)->company_name ?: optional($quotation->supplier)->name }}</h2>
                    @if (optional($quotation->supplier)->supplier_code)<small>{{ $quotation->supplier->supplier_code }}</small>@endif
                </div>
                <div class="quote-review-meta">
                    @if (optional($quotation->supplier)->phone)<span>{{ $quotation->supplier->phone }}</span>@endif
                    @if (optional($quotation->supplier)->email)<span>{{ $quotation->supplier->email }}</span>@endif
                    <span class="quote-review-status quote-status-{{ $quotation->status }}">{{ ucfirst(str_replace('_', ' ', $quotation->status)) }}</span>
                </div>
            </header>
            <div class="quote-review-table-wrap"><table class="quote-review-table">
                <thead><tr><th>CATEGORY</th><th>PRODUCT</th><th>QTY</th><th>UNIT</th><th>RATE</th><th>BASIC</th><th>DISCOUNT %</th><th>GST %</th><th>TAX</th><th>TOTAL</th></tr></thead>
                <tbody>@foreach ($quotation->items as $item)
                    <tr><td>{{ optional($item->product->category)->name }}</td><td>{{ $item->product->name }}</td><td>{{ number_format($item->quantity, 2) }}</td><td>{{ optional($item->product->unit)->short_name ?: optional($item->product->unit)->name }}</td>
                        @if ($quotation->status === 'draft' && auth()->user()->hasPermission('quotations', 'edit'))
                            <td><input class="field-control quote-rate" data-quantity="{{ $item->quantity }}" type="number" step="0.01" min="0" form="submit-all-quotations" name="items[{{ $quotation->id }}][{{ $item->id }}][supplier_rate]" value="{{ old('items.' . $quotation->id . '.' . $item->id . '.supplier_rate', $item->supplier_rate) }}" required></td>
                            <td class="quote-basic">₹{{ number_format($item->basic_amount, 2) }}</td>
                            <td><input class="field-control quote-discount" type="number" step="0.01" min="0" max="100" form="submit-all-quotations" name="items[{{ $quotation->id }}][{{ $item->id }}][discount_percent]" value="{{ old('items.' . $quotation->id . '.' . $item->id . '.discount_percent', $item->discount_percent) }}" required></td>
                            <td>
                                <input class="field-control quote-gst" type="number" step="0.01" min="0" max="100" form="submit-all-quotations" name="items[{{ $quotation->id }}][{{ $item->id }}][gst_rate]" value="{{ old('items.' . $quotation->id . '.' . $item->id . '.gst_rate', $item->gst_rate) }}" required>
                                <small class="quote-tax-split">CGST <span class="quote-cgst">{{ number_format($item->cgst_rate, 2) }}</span>% · SGST <span class="quote-sgst">{{ number_format($item->sgst_rate, 2) }}</span>%</small>
                            </td>
                            <td class="quote-tax">₹{{ number_format($item->tax_amount, 2) }}</td>
                            <td class="quote-total">₹{{ number_format($item->total_amount, 2) }}</td>
                        @else
                            <td>₹{{ number_format($item->supplier_rate, 2) }}</td><td>₹{{ number_format($item->basic_amount, 2) }}</td><td>{{ number_format($item->discount_percent, 2) }}%<small class="quote-tax-split">₹{{ number_format((float) $item->basic_amount * (float) $item->discount_percent / 100, 2) }}</small></td><td>{{ number_format($item->gst_rate, 2) }}<small class="quote-tax-split">CGST {{ number_format($item->cgst_rate, 2) }}% · SGST {{ number_format($item->sgst_rate, 2) }}%</small></td><td>₹{{ number_format($item->tax_amount, 2) }}</td>
                        @endif
                        @if (!($quotation->status === 'draft' && auth()->user()->hasPermission('quotations', 'edit')))<td>₹{{ number_format($item->total_amount, 2) }}</td>@endif</tr>
                @endforeach</tbody>
            </table></div>
            @if ($quotation->bill_copy_path && \Illuminate\Support\Facades\Storage::disk('local')->exists($quotation->bill_copy_path))
                <div class="quote-bill-copy-card">
                    <div>
                        <strong>Supplier bill copy</strong>
                        <small>{{ $quotation->bill_copy_name ?: 'Attached document' }}</small>
                    </div>
                    <div class="quote-bill-copy-actions">
                        <a class="button button-small button-light" href="{{ route('quotations.bill-copy', $quotation->id) }}" target="_blank" rel="noopener">View</a>
                        <a class="button button-small button-light" href="{{ route('quotations.bill-copy', [$quotation->id, 'download' => 1]) }}">Download</a>
                    </div>
                </div>
            @endif
            @if ($quotation->status === 'draft' && auth()->user()->hasPermission('quotations', 'edit'))
                <div class="quote-bill-copy-upload">
                    <label for="supplier-invoice-number-{{ $quotation->id }}">Supplier invoice/bill number</label>
                    <input id="supplier-invoice-number-{{ $quotation->id }}" class="field-control" type="text" name="supplier_invoice_numbers[{{ $quotation->id }}]" form="submit-all-quotations" maxlength="100" value="{{ old('supplier_invoice_numbers.' . $quotation->id, $quotation->supplier_invoice_number) }}" autocomplete="off">
                    <label for="bill-copy-{{ $quotation->id }}">Bill Copy / Supplier Quotation (optional)</label>
                    <input id="bill-copy-{{ $quotation->id }}" class="field-control" type="file" name="bill_copies[{{ $quotation->id }}]" form="submit-all-quotations" accept=".pdf,.jpg,.jpeg,.png,.webp,application/pdf,image/jpeg,image/png,image/webp" data-bill-copy-input>
                    <small>Upload a photo or PDF copy · JPG, PNG, WEBP or PDF · Maximum 10 MB</small>
                    <small class="quote-bill-copy-selected" data-bill-copy-selected hidden></small>
                    @error('bill_copies.' . $quotation->id)<small class="field-error">{{ $message }}</small>@enderror
                </div>
            @endif
            <footer class="quote-review-footer">
                <div class="quote-review-totals">
                    <div><span>Subtotal</span><strong class="quote-subtotal">₹{{ number_format($quotation->subtotal, 2) }}</strong></div>
                    <div><span>Discount</span><strong class="quote-discount-total">₹{{ number_format($quotation->discount_total, 2) }}</strong></div>
                    <div><span>Total Tax</span><strong class="quote-tax-total">₹{{ number_format($quotation->tax_total, 2) }}</strong></div>
                    <div class="quote-grand-total-box"><span>Grand Total</span><strong class="quote-grand-total">₹{{ number_format($quotation->grand_total, 2) }}</strong></div>
                </div>
                @if (in_array($quotation->status, ['submitted', 'under_review'], true))
                    @if (auth()->user()->hasPermission('quotations', 'approve'))
                        <form method="POST" action="{{ route('quotations.approve', $quotation->id) }}">@csrf<button class="button button-primary" type="submit">Approve This Supplier</button></form>
                    @endif
                    @if (auth()->user()->hasPermission('quotations', 'reject'))<form class="inline-form" method="POST" action="{{ route('quotations.reject', $quotation->id) }}"><input class="field-control" type="text" name="rejection_reason" placeholder="Rejection reason" required>@csrf<button class="button button-small button-deactivate" type="submit">Reject Request</button></form>@endif
                @endif
                @if ($quotation->status === 'approved')
                    @if ($quotation->purchase_order_id)
                        <a class="button button-primary" href="{{ route('purchase-orders.show', $quotation->purchase_order_id) }}">View Purchase Order</a>
                    @else
                        <a class="button button-primary" href="{{ route('quotations.purchase-details', $quotation->id) }}">Continue to Purchase Details</a>
                    @endif
                    @if (session('approval_quotation_id') == $quotation->id)
                        <dialog class="quotation-approval-dialog" id="supplier-approved-dialog">
                            <div class="quotation-approval-content">
                                <span class="quotation-approval-check" aria-hidden="true">✓</span>
                                <h2>Supplier Approved</h2>
                                <p>The supplier quotation is approved. Continue to negotiate purchase rates and record payment before a purchase order is generated.</p>
                                <dl>
                                    <div><dt>Supplier</dt><dd>{{ optional($quotation->supplier)->company_name ?: optional($quotation->supplier)->name }}</dd></div>
                                    <div><dt>Supplier Code</dt><dd>{{ optional($quotation->supplier)->supplier_code ?: '—' }}</dd></div>
                                    <div><dt>Quotation No.</dt><dd>{{ $quotation->quotation_code }}</dd></div>
                                    <div><dt>Grand Total</dt><dd>₹{{ number_format($quotation->grand_total, 2) }}</dd></div>
                                    <div><dt>Approved On</dt><dd>{{ optional($quotation->approved_at)->format('d M Y') ?: '—' }}</dd></div>
                                </dl>
                                <div class="quotation-approval-actions">
                                    <a class="button button-primary" href="{{ route('quotations.purchase-details', $quotation->id) }}">Continue to Purchase Details</a>
                                    <button class="button button-light" type="button" data-close-approval>Close</button>
                                </div>
                            </div>
                        </dialog>
                    @endif
                    @if (auth()->user()->hasPermission('quotations', 'approve'))<a class="button button-light" href="{{ route('quotations.rejected', $quotationRequest->id) }}">View Rejected Quotation</a>@endif
                @endif
            </footer>
            @if ($quotation->status === 'rejected')<p class="form-alert">Rejection reason: {{ $quotation->rejection_reason ?: '—' }}</p>@endif
        </section>
        @endif
    @endforeach
    @if ($canSubmitAll)<div class="form-actions"><button class="button button-primary" type="submit" form="submit-all-quotations">Submit All Quotations</button></div>@endif
    <style>
        .quote-review-card{margin:0 0 20px;background:#fff;border:1px solid #e7ebf0;border-radius:16px;box-shadow:0 5px 18px rgba(20,42,64,.04);overflow:hidden}
        .quote-review-header{min-height:90px;padding:20px 24px;display:flex;align-items:center;justify-content:space-between;gap:20px}
        .quote-review-title h2{margin:0;color:#172b3d;font-size:16px;font-weight:700}
        .quote-review-title h2 span{color:#9aa7b4}
        .quote-review-title small{display:block;margin-top:5px;color:#8493a2}
        .quote-review-meta{display:flex;align-items:center;justify-content:flex-end;gap:12px;color:#8493a2;font-size:12px}
        .quote-review-meta>span+span:not(.quote-review-status){border-left:1px solid #e7ebf0;padding-left:12px}
        .quote-review-status{border-radius:20px;padding:7px 12px;background:#f1f4f7;color:#647487;font-weight:700;white-space:nowrap}
        .quote-status-approved{background:#e7f7ee;color:#14804a}
        .quote-status-submitted,.quote-status-under_review{background:#fff5df;color:#a76b00}
        .quote-status-rejected{background:#fff0ee;color:#c14437}
        .quote-review-table-wrap{width:100%;overflow-x:auto}
        .quote-review-table{width:100%;min-width:820px;border-collapse:collapse}
        .quote-review-table th{padding:15px 14px;background:#f8fafc;border-top:1px solid #edf0f4;border-bottom:1px solid #edf0f4;color:#8b99a8;text-align:left;font-size:11px;font-weight:700;white-space:nowrap}
        .quote-review-table td{padding:18px 14px;border-bottom:1px solid #edf0f4;color:#40566b;white-space:nowrap}
        .quote-review-table tbody tr:last-child td{border-bottom:0}
        .quote-review-table .field-control{min-width:84px;padding:8px}
        .quote-tax-split{display:block;margin-top:5px;color:#7b8998;font-size:10px;white-space:nowrap}
        .quote-review-footer{padding:16px 20px;display:flex;align-items:center;justify-content:flex-end;gap:16px;flex-wrap:wrap;border-top:1px solid #edf0f4}
        .quote-review-totals{display:flex;align-items:center;gap:0;margin-right:auto}
        .quote-review-totals>div{min-width:132px;padding:4px 18px;display:grid;gap:5px;border-right:1px solid #e7ebf0}
        .quote-review-totals span{color:#78899a;font-size:12px}
        .quote-review-totals strong{color:#172b3d;font-size:14px}
        .quote-review-totals .quote-grand-total-box{min-width:160px;border:0;border-radius:10px;background:#eef5ff}
        .quote-review-totals .quote-grand-total-box span,.quote-review-totals .quote-grand-total-box strong{color:#1763c6}
        .quote-bill-copy-card{display:flex;align-items:center;justify-content:space-between;gap:12px;margin:0 20px 12px;padding:10px 12px;border:1px solid #e5ebf1;border-radius:8px;background:#fbfcfd}
        .quote-bill-copy-card strong,.quote-bill-copy-card small{display:block}
        .quote-bill-copy-card strong,.quote-bill-copy-upload label{color:#263e58;font-size:11px;font-weight:600}
        .quote-bill-copy-card small,.quote-bill-copy-upload>small{margin-top:4px;color:#77879a;font-size:10px}
        .quote-bill-copy-actions{display:flex;gap:7px}
        .quote-bill-copy-upload{display:grid;grid-template-columns:minmax(0,1fr);gap:5px;margin:0 20px 12px;padding:10px 12px;border:1px dashed #d8e1ea;border-radius:8px;background:#fafcfe}
        .quote-bill-copy-upload .field-control{max-width:440px;padding:6px}
        .quote-bill-copy-upload .quote-bill-copy-selected{color:#177b62}
        .quotation-approval-dialog{width:min(480px,calc(100% - 28px));padding:0;border:0;border-radius:16px;box-shadow:0 24px 80px rgba(18,42,78,.25)}
        .quotation-approval-dialog::backdrop{background:rgba(20,35,52,.48)}
        .quotation-approval-content{padding:24px}
        .quotation-approval-check{display:grid;width:42px;height:42px;place-items:center;border-radius:50%;background:#e8f8ef;color:#13824a;font-size:24px}
        .quotation-approval-content h2{margin:16px 0 6px;color:#172b3d;font-size:20px}
        .quotation-approval-content>p{margin:0;color:#6f8092;font-size:13px;line-height:1.6}
        .quotation-approval-content dl{margin:18px 0;padding:12px;border-radius:10px;background:#f7f9fc}
        .quotation-approval-content dl>div{display:flex;justify-content:space-between;gap:12px;padding:7px 0;border-bottom:1px solid #e8edf3;font-size:12px}
        .quotation-approval-content dl>div:last-child{border:0}
        .quotation-approval-content dt{color:#748397}
        .quotation-approval-content dd{margin:0;color:#19385e;font-weight:600;text-align:right}
        .quotation-approval-actions{display:flex;justify-content:flex-end;gap:8px;flex-wrap:wrap}
        @media(max-width:760px){.quote-review-header{align-items:flex-start;flex-direction:column;padding:18px}.quote-review-meta{justify-content:flex-start;flex-wrap:wrap}.quote-review-footer{align-items:stretch;flex-direction:column}.quote-review-totals{width:100%;flex-wrap:wrap}.quote-review-totals>div{min-width:33.3%;padding:8px}.quote-review-footer>.button,.quote-review-footer>form{align-self:flex-start}}
    </style>
    <script>
        document.querySelectorAll('[data-open-approval]').forEach(function (button) {
            button.addEventListener('click', function () {
                var dialog = document.getElementById(button.dataset.openApproval);
                if (dialog && typeof dialog.showModal === 'function') dialog.showModal();
            });
        });
        document.querySelectorAll('[data-close-approval]').forEach(function (button) {
            button.addEventListener('click', function () {
                button.closest('dialog').close();
            });
        });
        document.querySelectorAll('.quotation-approval-dialog').forEach(function (dialog) {
            dialog.addEventListener('click', function (event) {
                if (event.target === dialog) dialog.close();
            });
            var approvedDialog = document.getElementById('supplier-approved-dialog');
            if (approvedDialog && typeof approvedDialog.showModal === 'function') approvedDialog.showModal();
        });

        document.querySelectorAll('[data-bill-copy-input]').forEach(function (input) {
            input.addEventListener('change', function () {
                var selected = input.parentElement.querySelector('[data-bill-copy-selected]');
                var file = input.files[0];
                if (!file) {
                    selected.hidden = true;
                    selected.textContent = '';
                    return;
                }

                var validType = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'].indexOf(file.type) !== -1;
                var validExtension = /\.(pdf|jpe?g|png|webp)$/i.test(file.name);
                if (!validType || !validExtension || file.size > 10 * 1024 * 1024) {
                    input.value = '';
                    selected.textContent = file.size > 10 * 1024 * 1024
                        ? 'Maximum file size is 10 MB.'
                        : 'Choose a JPG, PNG, WEBP or PDF bill copy.';
                    selected.style.color = '#b42318';
                    selected.hidden = false;
                    return;
                }

                selected.textContent = 'Selected: ' + file.name + ' (' + (file.size / 1024 / 1024).toFixed(2) + ' MB)';
                selected.style.color = '#177b62';
                selected.hidden = false;
            });
        });

        document.querySelectorAll('.quote-rate').forEach(function (rate) {
            function update() {
                var row = rate.closest('tr');
                var basic = (parseFloat(rate.dataset.quantity) || 0) * (parseFloat(rate.value) || 0);
                var discountPercent = parseFloat(row.querySelector('.quote-discount').value) || 0;
                var discount = basic * (discountPercent / 100);
                var taxable = basic - discount;
                var gstRate = parseFloat(row.querySelector('.quote-gst').value) || 0;
                var cgstRate = Math.round((gstRate / 2) * 100) / 100;
                var sgstRate = Math.round((gstRate - cgstRate) * 100) / 100;
                row.querySelector('.quote-cgst').textContent = cgstRate.toFixed(2);
                row.querySelector('.quote-sgst').textContent = sgstRate.toFixed(2);
                var cgst = taxable * (cgstRate / 100);
                var sgst = taxable * (sgstRate / 100);
                row.querySelector('.quote-basic').textContent = '₹' + basic.toFixed(2);
                row.querySelector('.quote-tax').textContent = '₹' + (cgst + sgst).toFixed(2);
                row.querySelector('.quote-total').textContent = '₹' + (taxable + cgst + sgst).toFixed(2);
                var subtotal = 0, discountTotal = 0, tax = 0;
                row.closest('tbody').querySelectorAll('tr').forEach(function (line) {
                    var lineBasic = parseFloat(line.querySelector('.quote-basic').textContent.replace('₹', '')) || 0;
                    subtotal += lineBasic;
                    discountTotal += lineBasic * ((parseFloat(line.querySelector('.quote-discount').value) || 0) / 100);
                    tax += parseFloat(line.querySelector('.quote-tax').textContent.replace('₹', '')) || 0;
                });
                row.closest('section').querySelector('.quote-subtotal').textContent = '₹' + subtotal.toFixed(2);
                row.closest('section').querySelector('.quote-discount-total').textContent = '₹' + discountTotal.toFixed(2);
                row.closest('section').querySelector('.quote-tax-total').textContent = '₹' + tax.toFixed(2);
                row.closest('section').querySelector('.quote-grand-total').textContent = '₹' + (subtotal - discountTotal + tax).toFixed(2);
            }
            rate.closest('tr').querySelectorAll('input').forEach(function (input) {
                if (!input.readOnly) input.addEventListener('input', update);
            });
            update();
        });
    </script>
@endsection
