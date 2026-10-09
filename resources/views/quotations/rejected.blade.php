@extends('layouts.app')

@section('title', 'Rejected Quotations')
@section('topbar-title', 'Rejected quotations')

@section('content')
    <div class="page-heading">
        <div><span class="section-kicker">PURCHASING / AUTHORITY</span><h1>Rejected Quotations</h1><p>{{ $quotationRequest->quotation_request_code }} · Selected and rejected supplier quotations</p></div>
        <a class="button button-light" href="{{ route('quotations.approval') }}">Back to quotation approval</a>
    </div>
    @include('components.flash')
    @forelse ($quotationRequest->quotations->where('status', 'rejected') as $quotation)
        <section class="panel listing-panel">
            <div class="panel-heading"><h2>{{ $quotation->quotation_code }} · {{ optional($quotation->supplier)->company_name ?: optional($quotation->supplier)->name }}</h2><p>Status: {{ ucfirst($quotation->status) }}</p></div>
            <div class="table-wrap"><table class="data-table listing-table">
                <thead><tr><th>CATEGORY</th><th>PRODUCT</th><th>QTY</th><th>UNIT</th><th>RATE</th><th>BASIC</th><th>GST %</th><th>CGST %</th><th>SGST %</th><th>TAX</th><th>TOTAL</th></tr></thead>
                <tbody>@foreach ($quotation->items as $item)
                    <tr>
                        <td>{{ optional($item->product->category)->name }}</td>
                        <td>{{ optional($item->product)->name }}</td>
                        <td>{{ number_format($item->quantity, 2) }}</td>
                        <td>{{ optional($item->product->unit)->short_name ?: optional($item->product->unit)->name }}</td>
                        <td>₹{{ number_format($item->supplier_rate, 2) }}</td>
                        <td>₹{{ number_format($item->basic_amount, 2) }}</td>
                        <td>{{ number_format($item->gst_rate, 2) }}</td>
                        <td>{{ number_format($item->cgst_rate, 2) }}</td>
                        <td>{{ number_format($item->sgst_rate, 2) }}</td>
                        <td>₹{{ number_format($item->tax_amount, 2) }}</td>
                        <td>₹{{ number_format($item->total_amount, 2) }}</td>
                    </tr>
                @endforeach</tbody>
            </table></div>
            <div class="form-actions"><strong>Subtotal: ₹{{ number_format($quotation->subtotal, 2) }} · Tax: ₹{{ number_format($quotation->tax_total, 2) }} · Grand Total: ₹{{ number_format($quotation->grand_total, 2) }}</strong></div>
            @if ($quotation->status === 'rejected')<p class="form-alert">Rejection reason: {{ $quotation->rejection_reason ?: '—' }}</p>@endif
        </section>
    @empty
        <section class="panel listing-panel"><div class="workspace-empty"><h2>No rejected quotations</h2><p>There are no rejected quotations for this request.</p></div></section>
    @endforelse
@endsection
