@extends('layouts.app')

@section('title', 'Issue Details')
@section('topbar-title', 'Issue Details')

@section('content')
    @php
        $firstItem = $items->first();
        $location = $firstItem ? $firstItem->location_name : '';
    @endphp
    <div class="page-heading issue-report-heading">
        <div><span class="section-kicker">REPORTS / ISSUE DETAILS</span><h1>Issue Details</h1><p>Read-only details from the active stock outward records.</p></div>
        <a class="button button-light" href="{{ route('issue-reports.index') }}">Back to Issue Reports</a>
    </div>
    <section class="panel purchase-report-detail-card">
        <div class="purchase-report-section-heading"><span class="section-kicker">ISSUE SUMMARY</span><h2>{{ $issue->reference_number ?: 'Stock outward #' . $issue->id }}</h2></div>
        <div class="purchase-report-detail-grid">
            <div><span>Issue Reference</span><strong>{{ $issue->reference_number ?: '—' }}</strong></div>
            <div><span>Date</span><strong>{{ $issue->outward_date ? \Carbon\Carbon::parse($issue->outward_date)->format('d M Y') : '—' }}</strong></div>
            <div><span>Issue Type</span><strong>—</strong><small>Not recorded</small></div>
            <div><span>Recipient</span><strong>{{ $issue->recipient_name ?: '—' }}</strong></div>
            <div><span>Location</span><strong>{{ $location ?: '—' }}</strong><small>Product-assigned hall/rack/shelf</small></div>
            <div><span>Issued By</span><strong>—</strong><small>Not recorded</small></div>
            <div><span>Status</span><strong>Active</strong></div>
        </div>
        <div class="issue-report-detail-note"><strong>Unavailable transaction fields</strong><p>The existing outward records do not store issue type, issuing user, or source store. This report does not infer or create those values.</p></div>
    </section>
    <section class="panel issue-report-transactions">
        <div class="purchase-report-table-heading"><div><span class="section-kicker">PRODUCTS ISSUED</span><h2>Product Details</h2></div></div>
        <div class="table-wrap purchase-report-table-wrap">
            <table class="data-table purchase-report-table issue-report-detail-table">
                <thead><tr><th>PRODUCT</th><th>SKU</th><th>CATEGORY</th><th>QUANTITY</th><th>UNIT COST</th><th>TOTAL VALUE</th></tr></thead>
                <tbody>
                @foreach ($items as $item)
                    <tr><td><strong>{{ $item->product_name ?: '—' }}</strong></td><td>{{ $item->product_code ?: '—' }}</td><td>{{ $item->category_name ?: '—' }}</td><td>{{ number_format((float) $item->quantity, 2) }}</td><td>₹{{ number_format((float) $item->unit_cost, 2) }}</td><td><strong>₹{{ number_format((float) $item->total_value, 2) }}</strong></td></tr>
                @endforeach
                </tbody>
                <tfoot><tr><th colspan="5">Total Issued Value</th><th>₹{{ number_format($totalValue, 2) }}</th></tr></tfoot>
            </table>
        </div>
    </section>
@endsection
