@extends('layouts.app')

@section('title', $quotationRequest->quotation_request_code)
@section('topbar-title', 'Supplier quotations')

@section('content')
    <div class="page-heading">
        <div><span class="section-kicker">PURCHASING / QUOTATIONS</span><h1>{{ $quotationRequest->quotation_request_code }}</h1><p>{{ \Carbon\Carbon::parse($quotationRequest->request_date)->format('d M Y') }} · {{ optional($quotationRequest->store)->name }} · {{ ucfirst(str_replace('_', ' ', $quotationRequest->status)) }}</p></div>
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
    @if ($canSubmitAll)<form id="submit-all-quotations" method="POST" action="{{ route('quotations.request.submit', $quotationRequest->id) }}">@csrf</form>@endif
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
                <thead><tr><th>CATEGORY</th><th>PRODUCT</th><th>QTY</th><th>UNIT</th><th>RATE</th><th>BASIC</th><th>GST %</th><th>CGST %</th><th>SGST %</th><th>TAX</th><th>TOTAL</th></tr></thead>
                <tbody>@foreach ($quotation->items as $item)
                    <tr><td>{{ optional($item->product->category)->name }}</td><td>{{ $item->product->name }}</td><td>{{ number_format($item->quantity, 2) }}</td><td>{{ optional($item->product->unit)->short_name ?: optional($item->product->unit)->name }}</td>
                        @if ($quotation->status === 'draft' && auth()->user()->hasPermission('quotations', 'edit'))
                            <td><input class="field-control quote-rate" data-quantity="{{ $item->quantity }}" type="number" step="0.01" min="0" form="submit-all-quotations" name="items[{{ $quotation->id }}][{{ $item->id }}][supplier_rate]" value="{{ old('items.' . $quotation->id . '.' . $item->id . '.supplier_rate', $item->supplier_rate) }}" required></td>
                            <td class="quote-basic">₹{{ number_format($item->basic_amount, 2) }}</td>
                            <td><input class="field-control quote-gst" type="number" step="0.01" min="0" max="100" form="submit-all-quotations" name="items[{{ $quotation->id }}][{{ $item->id }}][gst_rate]" value="{{ old('items.' . $quotation->id . '.' . $item->id . '.gst_rate', $item->gst_rate) }}" required></td>
                            <td><input class="field-control quote-cgst" type="number" step="0.01" min="0" max="100" value="{{ $item->cgst_rate }}" readonly tabindex="-1"></td>
                            <td><input class="field-control quote-sgst" type="number" step="0.01" min="0" max="100" value="{{ $item->sgst_rate }}" readonly tabindex="-1"></td>
                            <td class="quote-tax">₹{{ number_format($item->tax_amount, 2) }}</td>
                            <td class="quote-total">₹{{ number_format($item->total_amount, 2) }}</td>
                        @else
                            <td>₹{{ number_format($item->supplier_rate, 2) }}</td><td>₹{{ number_format($item->basic_amount, 2) }}</td><td>{{ number_format($item->gst_rate, 2) }}</td><td>{{ number_format($item->cgst_rate, 2) }}</td><td>{{ number_format($item->sgst_rate, 2) }}</td><td>₹{{ number_format($item->tax_amount, 2) }}</td>
                        @endif
                        @if (!($quotation->status === 'draft' && auth()->user()->hasPermission('quotations', 'edit')))<td>₹{{ number_format($item->total_amount, 2) }}</td>@endif</tr>
                @endforeach</tbody>
            </table></div>
            <footer class="quote-review-footer">
                <div class="quote-review-totals">
                    <div><span>Subtotal</span><strong class="quote-subtotal">₹{{ number_format($quotation->subtotal, 2) }}</strong></div>
                    <div><span>Total Tax</span><strong class="quote-tax-total">₹{{ number_format($quotation->tax_total, 2) }}</strong></div>
                    <div class="quote-grand-total-box"><span>Grand Total</span><strong class="quote-grand-total">₹{{ number_format($quotation->grand_total, 2) }}</strong></div>
                </div>
                @if (in_array($quotation->status, ['submitted', 'under_review'], true))
                    @if (auth()->user()->hasPermission('quotations', 'approve'))<form class="inline-form" method="POST" action="{{ route('quotations.approve', $quotation->id) }}">@csrf<button class="button button-primary" type="submit">Approve This Supplier</button></form>@endif
                    @if (auth()->user()->hasPermission('quotations', 'reject'))<form class="inline-form" method="POST" action="{{ route('quotations.reject', $quotation->id) }}"><input class="field-control" type="text" name="rejection_reason" placeholder="Rejection reason" required>@csrf<button class="button button-small button-deactivate" type="submit">Reject Request</button></form>@endif
                @endif
                @if ($quotation->status === 'approved')
                    @if (!$quotation->purchase_order_id && auth()->user()->hasPermission('quotations', 'approve'))<form method="POST" action="{{ route('quotations.generate-purchase', $quotation->id) }}">@csrf<button class="button button-primary" type="submit">Generate Purchase</button></form>@elseif ($quotation->purchase_order_id)<a class="button button-primary" href="{{ route('purchase-orders.show', $quotation->purchase_order_id) }}">View Purchase</a>@endif
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
        .quote-review-table{width:100%;min-width:900px;border-collapse:collapse}
        .quote-review-table th{padding:15px 14px;background:#f8fafc;border-top:1px solid #edf0f4;border-bottom:1px solid #edf0f4;color:#8b99a8;text-align:left;font-size:11px;font-weight:700;white-space:nowrap}
        .quote-review-table td{padding:18px 14px;border-bottom:1px solid #edf0f4;color:#40566b;white-space:nowrap}
        .quote-review-table tbody tr:last-child td{border-bottom:0}
        .quote-review-table .field-control{min-width:84px;padding:8px}
        .quote-review-footer{padding:16px 20px;display:flex;align-items:center;justify-content:flex-end;gap:16px;flex-wrap:wrap;border-top:1px solid #edf0f4}
        .quote-review-totals{display:flex;align-items:center;gap:0;margin-right:auto}
        .quote-review-totals>div{min-width:132px;padding:4px 18px;display:grid;gap:5px;border-right:1px solid #e7ebf0}
        .quote-review-totals span{color:#78899a;font-size:12px}
        .quote-review-totals strong{color:#172b3d;font-size:14px}
        .quote-review-totals .quote-grand-total-box{min-width:160px;border:0;border-radius:10px;background:#eef5ff}
        .quote-review-totals .quote-grand-total-box span,.quote-review-totals .quote-grand-total-box strong{color:#1763c6}
        @media(max-width:760px){.quote-review-header{align-items:flex-start;flex-direction:column;padding:18px}.quote-review-meta{justify-content:flex-start;flex-wrap:wrap}.quote-review-footer{align-items:stretch;flex-direction:column}.quote-review-totals{width:100%;flex-wrap:wrap}.quote-review-totals>div{min-width:33.3%;padding:8px}.quote-review-footer>.button,.quote-review-footer>form{align-self:flex-start}}
    </style>
    <script>
        document.querySelectorAll('.quote-rate').forEach(function (rate) {
            function update() {
                var row = rate.closest('tr');
                var basic = (parseFloat(rate.dataset.quantity) || 0) * (parseFloat(rate.value) || 0);
                var gstRate = parseFloat(row.querySelector('.quote-gst').value) || 0;
                var cgstRate = Math.round((gstRate / 2) * 100) / 100;
                var sgstRate = Math.round((gstRate - cgstRate) * 100) / 100;
                row.querySelector('.quote-cgst').value = cgstRate.toFixed(2);
                row.querySelector('.quote-sgst').value = sgstRate.toFixed(2);
                var cgst = basic * (cgstRate / 100);
                var sgst = basic * (sgstRate / 100);
                row.querySelector('.quote-basic').textContent = '₹' + basic.toFixed(2);
                row.querySelector('.quote-tax').textContent = '₹' + (cgst + sgst).toFixed(2);
                row.querySelector('.quote-total').textContent = '₹' + (basic + cgst + sgst).toFixed(2);
                var subtotal = 0, tax = 0;
                row.closest('tbody').querySelectorAll('tr').forEach(function (line) {
                    subtotal += parseFloat(line.querySelector('.quote-basic').textContent.replace('₹', '')) || 0;
                    tax += parseFloat(line.querySelector('.quote-tax').textContent.replace('₹', '')) || 0;
                });
                row.closest('section').querySelector('.quote-subtotal').textContent = '₹' + subtotal.toFixed(2);
                row.closest('section').querySelector('.quote-tax-total').textContent = '₹' + tax.toFixed(2);
                row.closest('section').querySelector('.quote-grand-total').textContent = '₹' + (subtotal + tax).toFixed(2);
            }
            rate.closest('tr').querySelectorAll('input').forEach(function (input) {
                if (!input.readOnly) input.addEventListener('input', update);
            });
            update();
        });
    </script>
@endsection
