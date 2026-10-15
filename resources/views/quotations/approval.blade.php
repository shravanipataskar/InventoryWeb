@extends('layouts.app')

@section('title', 'Quotation Approval')
@section('topbar-title', 'Quotation approval')

@section('content')
    <div class="page-heading">
        <div>
            <span class="section-kicker">PURCHASING / AUTHORITY</span>
            <h1>Quotation Approval</h1>
            <p>Compare submitted supplier quotations and approve one supplier per request.</p>
        </div>
        <a class="button button-light" href="{{ route('quotations.index') }}">All requests</a>
    </div>

    @include('components.flash')

    <div class="approval-summary">
        <div class="approval-summary-card tone-primary">
            <div class="approval-summary-card-top">
                <div>
                    <span class="approval-summary-label">Total Requests</span>
                    <strong>{{ $stats['all'] }}</strong>
                </div>
                <div class="approval-summary-icon">
                    <svg viewBox="0 0 24 24"><path d="M7 3.5h7l5 5V19a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V5.5a2 2 0 0 1 2-2zm7 1.5v4h4"/><path d="M9 12h6M9 16h6"/></svg>
                </div>
            </div>
            <small>All quotation requests</small>
        </div>

        <div class="approval-summary-card tone-success">
            <div class="approval-summary-card-top">
                <div>
                    <span class="approval-summary-label">Approved</span>
                    <strong>{{ $stats['approved'] }}</strong>
                </div>
                <div class="approval-summary-icon">
                    <svg viewBox="0 0 24 24"><path d="M5 12.5l4.2 4.2L19 2.7"/></svg>
                </div>
            </div>
            <small>Requests approved</small>
        </div>

        <div class="approval-summary-card tone-warning">
            <div class="approval-summary-card-top">
                <div>
                    <span class="approval-summary-label">Pending</span>
                    <strong>{{ $stats['pending'] }}</strong>
                </div>
                <div class="approval-summary-icon">
                    <svg viewBox="0 0 24 24"><path d="M12 7v5l3 2"/><circle cx="12" cy="12" r="8"/></svg>
                </div>
            </div>
            <small>Awaiting approval</small>
        </div>

        <div class="approval-summary-card tone-danger">
            <div class="approval-summary-card-top">
                <div>
                    <span class="approval-summary-label">Rejected</span>
                    <strong>{{ $stats['rejected'] }}</strong>
                </div>
                <div class="approval-summary-icon">
                    <svg viewBox="0 0 24 24"><path d="M7 7l10 10M17 7L7 17"/></svg>
                </div>
            </div>
            <small>Requests rejected</small>
        </div>
    </div>

    <div class="panel approval-filter-panel">
        <div class="approval-tabs" role="tablist" aria-label="Quotation status filters">
            <a class="approval-tab {{ $statusFilter === 'all' ? 'is-active' : '' }}" href="{{ route('quotations.approval', ['status' => 'all']) }}">
                <span>All Requests</span>
                <em>{{ $stats['all'] }}</em>
            </a>
            <a class="approval-tab {{ $statusFilter === 'pending' ? 'is-active' : '' }}" href="{{ route('quotations.approval', ['status' => 'pending']) }}">
                <span>Pending</span>
                <em>{{ $stats['pending'] }}</em>
            </a>
            <a class="approval-tab {{ $statusFilter === 'approved' ? 'is-active' : '' }}" href="{{ route('quotations.approval', ['status' => 'approved']) }}">
                <span>Approved</span>
                <em>{{ $stats['approved'] }}</em>
            </a>
            <a class="approval-tab {{ $statusFilter === 'rejected' ? 'is-active' : '' }}" href="{{ route('quotations.approval', ['status' => 'rejected']) }}">
                <span>Rejected</span>
                <em>{{ $stats['rejected'] }}</em>
            </a>
        </div>

        <form class="approval-toolbar" method="GET" action="{{ route('quotations.approval') }}">
            <div class="search-field search-field-grow">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <circle cx="11" cy="11" r="6" fill="none" stroke="currentColor" stroke-width="1.8"></circle>
                    <path d="M16 16L21 21" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"></path>
                </svg>
                <input type="search" name="search" value="{{ old('search', $search) }}" placeholder="Search by request no., supplier, item..." aria-label="Search quotations">
            </div>

            <div class="approval-date-group">
                <label class="date-filter">
                    <span>From</span>
                    <input type="date" name="start_date" value="{{ old('start_date', $startDate) }}">
                </label>
                <label class="date-filter">
                    <span>To</span>
                    <input type="date" name="end_date" value="{{ old('end_date', $endDate) }}">
                </label>
            </div>

            @if ($statusFilter !== 'all')
                <input type="hidden" name="status" value="{{ $statusFilter }}">
            @endif

            <button class="button button-light" type="submit">Apply</button>
        </form>
    </div>

    @forelse ($quotations as $requestRecord)
        <section class="panel approval-panel">
            <div class="approval-request-header">
                <div class="approval-request-meta">
                    <h2>{{ $requestRecord->quotation_request_code }}</h2>
                    <div class="approval-request-meta-row">
                        <span>{{ \Carbon\Carbon::parse($requestRecord->request_date)->format('d M Y') }}</span>
                        <span>{{ $requestRecord->quotations_count }} supplier quotations</span>
                        <span>{{ $requestRecord->product_count }} items</span>
                    </div>
                </div>
                <span class="approval-request-status {{ $requestRecord->status === 'approved' ? 'approved' : ($requestRecord->status === 'rejected' ? 'rejected' : 'pending') }}">
                    {{ ucfirst(str_replace('_', ' ', $requestRecord->status)) }}
                </span>
            </div>

            <div class="table-wrap">
                <table class="data-table approval-table">
                    <thead>
                        <tr>
                            <th>REQUEST NO.</th>
                            <th>SUPPLIER</th>
                            <th>ITEMS</th>
                            <th>TOTAL AMOUNT</th>
                            <th>STATUS</th>
                            <th>ACTION</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($requestRecord->approval_quotations as $quotation)
                            <tr>
                                <td class="approval-request-number-cell">
                                    <strong>{{ $requestRecord->quotation_request_code }}</strong>
                                </td>
                                <td>
                                    <div class="approval-supplier-name">
                                        {{ optional($quotation->supplier)->company_name ?: optional($quotation->supplier)->name }}
                                    </div>
                                </td>
                                <td>
                                    @php $itemCount = $quotation->items->count(); @endphp
                                    @if ($itemCount)
                                        <span class="approval-item-count">{{ $itemCount }} item{{ $itemCount > 1 ? 's' : '' }}</span>
                                    @else
                                        <span class="approval-item-count muted">0 items</span>
                                    @endif
                                </td>
                                <td class="approval-amount">₹{{ number_format($quotation->grand_total, 2) }}</td>
                                <td>
                                    <span class="status-badge {{ $quotation->status === 'approved' ? 'status-success' : ($quotation->status === 'rejected' ? 'status-danger' : 'status-warning') }}">
                                        {{ ucfirst(str_replace('_', ' ', $quotation->status)) }}
                                    </span>
                                </td>
                                <td>
                                    <div class="approval-actions">
                                        <a class="button button-small button-light" href="{{ route('quotations.show', $requestRecord->id) }}">Review</a>

                                        @if (in_array($quotation->status, ['submitted', 'under_review'], true))
                                            <form method="POST" action="{{ route('quotations.approve', $quotation->id) }}" style="display:inline;">@csrf
                                                <button class="button button-small button-primary" type="submit">Approve</button>
                                            </form>
                                        @elseif ($quotation->status === 'approved' && $requestRecord->status === 'approved' && $requestRecord->quotations->where('status', 'rejected')->isNotEmpty())
                                            <a class="button button-small button-light" href="{{ route('quotations.rejected', $requestRecord->id) }}">View Rejected Quotation</a>
                                        @elseif ($quotation->status === 'rejected' && $requestRecord->status === 'approved' && !empty($quotation->rejection_reason))
                                            <a class="button button-small button-light" href="{{ route('quotations.rejected', $requestRecord->id) }}">View Rejection Reason</a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @empty
        <section class="panel listing-panel">
            <div class="workspace-empty">
                <h2>{{ $statusFilter === 'rejected' ? 'No rejected quotations found' : 'No quotation requests found' }}</h2>
                <p>{{ $statusFilter === 'rejected' ? 'Rejected supplier quotations will appear here.' : 'Submitted supplier quotations will appear here for authority review.' }}</p>
            </div>
        </section>
    @endforelse

    @if ($quotations->hasPages())
        <div class="approval-pagination">
            <span>Showing {{ $quotations->firstItem() }}–{{ $quotations->lastItem() }} of {{ $quotations->total() }} requests</span>
            {{ $quotations->links() }}
        </div>
    @endif
@endsection
