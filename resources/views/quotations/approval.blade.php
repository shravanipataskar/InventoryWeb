@extends('layouts.app')

@section('title', 'Quotation Approval')
@section('topbar-title', 'Quotation approval')

@section('content')
    <div class="page-heading"><div><span class="section-kicker">PURCHASING / AUTHORITY</span><h1>Quotation Approval</h1><p>Compare submitted supplier quotations and approve one supplier per request.</p></div><a class="button button-light" href="{{ route('quotations.index') }}">All requests</a></div>
    @include('components.flash')
    @forelse ($quotations as $requestRecord)
        <section class="panel listing-panel"><div class="panel-heading"><h2>{{ $requestRecord->quotation_request_code }}</h2><p>{{ \Carbon\Carbon::parse($requestRecord->request_date)->format('d M Y') }} · {{ $requestRecord->quotations_count }} supplier quotations · {{ ucfirst(str_replace('_', ' ', $requestRecord->status)) }}</p></div>
            <div class="table-wrap"><table class="data-table listing-table"><thead><tr><th>REQUEST NO.</th><th>DATE</th><th>REQUESTED BY</th><th>SUPPLIERS</th><th>PRODUCTS</th><th>STATUS</th><th>ACTION</th></tr></thead><tbody>
                <tr><td>{{ $requestRecord->quotation_request_code }}</td><td>{{ \Carbon\Carbon::parse($requestRecord->request_date)->format('d M Y') }}</td><td>{{ optional($requestRecord->creator)->name ?: '—' }}</td><td>{{ $requestRecord->quotations_count }}</td><td>{{ $requestRecord->product_count }}</td><td>{{ ucfirst(str_replace('_', ' ', $requestRecord->status)) }}</td><td>
                    <a class="button button-small button-light" href="{{ route('quotations.show', $requestRecord->id) }}">Review</a>
                </td></tr>
            </tbody></table></div>
        </section>
    @empty
        <section class="panel listing-panel"><div class="workspace-empty"><h2>No submitted quotations</h2><p>Submitted supplier quotations will appear here for authority review.</p></div></section>
    @endforelse
@endsection
