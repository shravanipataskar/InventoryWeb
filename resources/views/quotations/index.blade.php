@extends('layouts.app')

@section('title', 'Quotation Requests')
@section('topbar-title', 'Quotations')

@section('content')
    <div class="page-heading">
        <div><span class="section-kicker">PURCHASING / QUOTATIONS</span><h1>Quotation Requests</h1><p>Compare supplier quotations before creating a purchase order.</p></div>
        <div class="page-heading-actions">
            @if (auth()->user()->hasPermission('quotations', 'approve'))<a class="button button-light" href="{{ route('quotations.approval') }}">Approval Queue</a>@endif
            @if (auth()->user()->hasPermission('quotations', 'create'))<a class="button button-primary" href="{{ route('quotations.create') }}">+ Create Quotation Request</a>@endif
        </div>
    </div>
    @include('components.flash')
    <section class="panel listing-panel"><div class="table-wrap"><table class="data-table listing-table">
        <thead><tr><th>REQUEST CODE</th><th>DATE</th><th>SUPPLIERS</th><th>STATUS</th><th>CREATED BY</th><th>ACTION</th></tr></thead>
        <tbody>@forelse ($requests as $requestRecord)
            <tr><td><strong>{{ $requestRecord->quotation_request_code }}</strong></td><td>{{ \Carbon\Carbon::parse($requestRecord->request_date)->format('d M Y') }}</td><td>{{ $requestRecord->quotations_count }}</td><td>{{ ucfirst(str_replace('_', ' ', $requestRecord->status)) }}</td><td>{{ optional($requestRecord->creator)->name ?: '—' }}</td><td><a class="button button-small button-light" href="{{ route('quotations.show', $requestRecord->id) }}">View</a></td></tr>
        @empty<tr><td colspan="6">No quotation requests found.</td></tr>@endforelse</tbody>
    </table></div><footer class="workspace-footer">{{ $requests->links() }}</footer></section>
@endsection
