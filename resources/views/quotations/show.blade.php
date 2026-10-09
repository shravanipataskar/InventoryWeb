@extends('layouts.app')

@section('title', $quotationRequest->quotation_request_code)
@section('topbar-title', 'Supplier quotations')

@section('content')
    <div class="page-heading">
        <div><span class="section-kicker">PURCHASING / QUOTATIONS</span><h1>{{ $quotationRequest->quotation_request_code }}</h1><p>{{ \Carbon\Carbon::parse($quotationRequest->request_date)->format('d M Y') }} · {{ optional($quotationRequest->store)->name }} · {{ ucfirst(str_replace('_', ' ', $quotationRequest->status)) }}</p></div>
        <a class="button button-light" href="{{ route('quotations.index') }}">Back to quotations</a>
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
        <section class="panel listing-panel">
            <div class="panel-heading"><h2>{{ $quotation->quotation_code }} · {{ optional($quotation->supplier)->company_name ?: optional($quotation->supplier)->name }}</h2><p>{{ optional($quotation->supplier)->supplier_code }} · {{ optional($quotation->supplier)->phone ?: 'No phone' }} · {{ optional($quotation->supplier)->email ?: 'No email' }} · Status: {{ ucfirst($quotation->status) }}</p></div>
            <div class="table-wrap"><table class="data-table listing-table">
                <thead><tr><th>CATEGORY</th><th>PRODUCT</th><th>QTY</th><th>UNIT</th><th>RATE</th><th>BASIC</th><th>GST %</th><th>CGST %</th><th>SGST %</th><th>TAX</th><th>TOTAL</th></tr></thead>
                <tbody>@foreach ($quotation->items as $item)
                    <tr><td>{{ optional($item->product->category)->name }}</td><td>{{ $item->product->name }}</td><td>{{ number_format($item->quantity, 2) }}</td><td>{{ optional($item->product->unit)->short_name ?: optional($item->product->unit)->name }}</td>
                        @if ($quotation->status === 'draft' && auth()->user()->hasPermission('quotations', 'edit'))
                            <td><input class="field-control quote-rate" data-quantity="{{ $item->quantity }}" type="number" step="0.01" min="0"                                                         form="submit-all-quotations"
                            name="items[{{ $quotation->id }}][{{ $item->id }}][supplier_rate]"                             value="{{ old('items.' . $quotation->id . '.' . $item->id . '.supplier_rate', $item->supplier_rate) }}" required></td>
                            <td class="quote-basic">₹{{ number_format($item->basic_amount, 2) }}</td>
                            <td><input class="field-control quote-gst" type="number" step="0.01" min="0" max="100"                                                         form="submit-all-quotations"
                            name="items[{{ $quotation->id }}][{{ $item->id }}][gst_rate]"                             value="{{ old('items.' . $quotation->id . '.' . $item->id . '.gst_rate', $item->gst_rate) }}" required></td>
                            <td><input class="field-control quote-cgst" type="number" step="0.01" min="0" max="100" value="{{ old('items.' . $item->id . '.cgst_rate', $item->cgst_rate) }}" readonly tabindex="-1"></td>
                            <td><input class="field-control quote-sgst" type="number" step="0.01" min="0" max="100" value="{{ old('items.' . $item->id . '.sgst_rate', $item->sgst_rate) }}" readonly tabindex="-1"></td>
                            <td class="quote-tax">₹{{ number_format($item->tax_amount, 2) }}</td>
                            <td class="quote-total">₹{{ number_format($item->total_amount, 2) }}</td>
                        @else
                            <td>₹{{ number_format($item->supplier_rate, 2) }}</td><td>₹{{ number_format($item->basic_amount, 2) }}</td><td>{{ number_format($item->gst_rate, 2) }}</td><td>{{ number_format($item->cgst_rate, 2) }}</td><td>{{ number_format($item->sgst_rate, 2) }}</td><td>₹{{ number_format($item->tax_amount, 2) }}</td>
                        @endif
                        @if (!($quotation->status === 'draft' && auth()->user()->hasPermission('quotations', 'edit')))<td>₹{{ number_format($item->total_amount, 2) }}</td>@endif</tr>
                @endforeach</tbody>
            </table></div>
            <div class="form-actions"><strong>Subtotal: <span class="quote-subtotal">₹{{ number_format($quotation->subtotal, 2) }}</span> · Tax: <span class="quote-tax-total">₹{{ number_format($quotation->tax_total, 2) }}</span> · Grand Total: <span class="quote-grand-total">₹{{ number_format($quotation->grand_total, 2) }}</span></strong>
                @if (in_array($quotation->status, ['submitted', 'under_review'], true))
                    @if (auth()->user()->hasPermission('quotations', 'approve'))<form class="inline-form" method="POST" action="{{ route('quotations.approve', $quotation->id) }}">@csrf<button class="button button-primary" type="submit">Approve This Supplier</button></form>@endif
                    @if (auth()->user()->hasPermission('quotations', 'reject'))<form class="inline-form" method="POST" action="{{ route('quotations.reject', $quotation->id) }}"><input class="field-control" type="text" name="rejection_reason" placeholder="Rejection reason" required>@csrf<button class="button button-small button-deactivate" type="submit">Reject Request</button></form>@endif
                @endif
                @if ($quotation->status === 'approved')
                    @if (!$quotation->purchase_order_id && auth()->user()->hasPermission('quotations', 'approve'))<form method="POST" action="{{ route('quotations.generate-purchase', $quotation->id) }}">@csrf<button class="button button-primary" type="submit">Generate Purchase</button></form>@elseif ($quotation->purchase_order_id)<a class="button button-light" href="{{ route('purchase-orders.show', $quotation->purchase_order_id) }}">View Purchase</a>@endif
                    @if (auth()->user()->hasPermission('quotations', 'approve'))<a class="button button-light" href="{{ route('quotations.rejected', $quotationRequest->id) }}">View Rejected Quotation</a>@endif
                @endif
            </div>
            @if ($quotation->status === 'rejected')<p class="form-alert">Rejection reason: {{ $quotation->rejection_reason ?: '—' }}</p>@endif
        </section>
        @endif
    @endforeach
    @if ($canSubmitAll)<div class="form-actions"><button class="button button-primary" type="submit" form="submit-all-quotations">Submit All Quotations</button></div>@endif
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
