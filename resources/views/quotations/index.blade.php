@extends('layouts.app')

@section('title', 'Quotation Requests')
@section('topbar-title', 'Quotations')

@section('content')
    @php
        $statusLabels = [
            'draft' => 'Draft',
            'submitted' => 'Submitted',
            'under_review' => 'Under review',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
        ];
    @endphp

    <div class="page-heading quotation-page-heading">
        <div><span class="section-kicker">PURCHASING / QUOTATIONS</span><h1>Quotation Requests</h1><p>Compare supplier quotations before creating a purchase order.</p></div>
        <div class="page-heading-actions">
            @if (auth()->user()->hasPermission('quotations', 'approve'))
                <a class="button button-light" href="{{ route('quotations.approval') }}"><span aria-hidden="true">◷</span> Approval Queue</a>
            @endif
            @if (auth()->user()->hasPermission('quotations', 'create'))
                <a class="button button-primary" href="{{ route('quotations.create') }}"><span aria-hidden="true">＋</span> Create Quotation Request</a>
            @endif
        </div>
    </div>

    @include('components.flash')

    <section class="quotation-stats" aria-label="Quotation request summary">
        <article class="quotation-stat-card quotation-stat-blue">
            <span class="quotation-stat-icon" aria-hidden="true">▤</span>
            <span><strong>{{ number_format($stats['total']) }}</strong><small>Total Requests</small></span>
        </article>
        <article class="quotation-stat-card quotation-stat-amber">
            <span class="quotation-stat-icon" aria-hidden="true">◷</span>
            <span><strong>{{ number_format($stats['draft']) }}</strong><small>Draft</small></span>
        </article>
        <article class="quotation-stat-card quotation-stat-blue">
            <span class="quotation-stat-icon" aria-hidden="true">↗</span>
            <span><strong>{{ number_format($stats['submitted_quotations']) }}</strong><small>Submitted Quotations</small></span>
        </article>
        <article class="quotation-stat-card quotation-stat-green">
            <span class="quotation-stat-icon" aria-hidden="true">✓</span>
            <span><strong>{{ number_format($stats['approved']) }}</strong><small>Approved</small></span>
        </article>
        <article class="quotation-stat-card quotation-stat-purple">
            <span class="quotation-stat-icon" aria-hidden="true">♙</span>
            <span><strong>{{ number_format($stats['suppliers']) }}</strong><small>Total Suppliers</small></span>
        </article>
    </section>

    <section class="panel quotation-list-panel">
        <form class="quotation-filter-form" action="{{ route('quotations.index') }}" method="GET">
            <label class="quotation-filter-field quotation-search-field">
                <span>Search</span>
                <span class="quotation-input-wrap"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.8" cy="10.8" r="6.3"></circle><path d="m16 16 4.2 4.2"></path></svg><input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search by request code, supplier..." aria-label="Search by request code or supplier"></span>
            </label>
            <label class="quotation-filter-field">
                <span>Status</span>
                <select name="status">
                    <option value="all" {{ ($filters['status'] ?? 'all') === 'all' ? 'selected' : '' }}>All Status</option>
                    @foreach ($statusLabels as $status => $label)
                        <option value="{{ $status }}" {{ ($filters['status'] ?? '') === $status ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="quotation-filter-field">
                <span>From Date</span>
                <input type="date" name="from_date" value="{{ $filters['from_date'] ?? '' }}" aria-label="From date">
            </label>
            <label class="quotation-filter-field">
                <span>To Date</span>
                <input type="date" name="to_date" value="{{ $filters['to_date'] ?? '' }}" aria-label="To date">
            </label>
            <button class="button button-primary quotation-filter-button" type="submit"><span aria-hidden="true">▽</span> Filter</button>
            <a class="button button-light quotation-reset-button" href="{{ route('quotations.index') }}"><span aria-hidden="true">↻</span> Reset</a>
        </form>
    </section>

    <section class="panel quotation-list-panel">
        <div class="table-wrap">
            <table class="data-table listing-table quotation-table">
                <thead>
                    <tr><th>#</th><th>REQUEST CODE</th><th>DATE <span class="quotation-sort-indicator" aria-hidden="true">↓</span></th><th>SUPPLIERS</th><th>STATUS</th><th>CREATED BY</th><th>ACTION</th></tr>
                </thead>
                <tbody>
                    @forelse ($requests as $requestRecord)
                        @php
                            $requestStatus = $requestRecord->status;
                            $statusClass = in_array($requestStatus, ['submitted', 'under_review'], true) ? 'is-pending' : 'is-' . $requestStatus;
                        @endphp
                        <tr>
                            <td class="muted-cell">{{ $requests->firstItem() + $loop->index }}</td>
                            <td><strong class="quotation-request-code">{{ $requestRecord->quotation_request_code }}</strong></td>
                            <td>{{ \Carbon\Carbon::parse($requestRecord->request_date)->format('d M Y') }}</td>
                            <td>{{ $requestRecord->quotations_count }}</td>
                            <td><span class="quotation-status {{ $statusClass }}"><span aria-hidden="true">{{ $requestStatus === 'approved' ? '✓' : ($requestStatus === 'rejected' ? '×' : '◷') }}</span>{{ $statusLabels[$requestStatus] ?? ucfirst(str_replace('_', ' ', $requestStatus)) }}</span></td>
                            <td>{{ optional($requestRecord->creator)->name ?: '—' }}</td>
                            <td><a class="button button-small button-light quotation-view-button" href="{{ route('quotations.show', $requestRecord->id) }}"><span aria-hidden="true">⊙</span> View</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><div class="quotation-empty-state">No quotation requests match your search and filters.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <footer class="quotation-table-footer">
            <span>Showing {{ $requests->firstItem() ?: 0 }}–{{ $requests->lastItem() ?: 0 }} of {{ $requests->total() }} entries</span>
            <div class="quotation-pagination">{{ $requests->links() }}</div>
        </footer>
    </section>

    <style>
        .quotation-page-heading{align-items:center}
        .quotation-stats{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:12px;margin:0 0 16px}
        .quotation-stat-card{display:flex;min-width:0;min-height:74px;align-items:center;gap:12px;padding:12px 14px;border:1px solid #e8edf2;border-radius:10px;background:#fff;box-shadow:0 2px 9px rgba(23,48,66,.025)}
        .quotation-stat-icon{width:42px;height:42px;flex:0 0 42px;display:grid;place-items:center;border-radius:10px;font-size:22px;font-weight:700}
        .quotation-stat-card>span:last-child{display:grid;gap:3px}
        .quotation-stat-card strong{color:#19385e;font-size:20px;line-height:1.1}
        .quotation-stat-card small{color:#718196;font-size:11px}
        .quotation-stat-blue .quotation-stat-icon{background:#e8f2ff;color:#2780ed}
        .quotation-stat-amber .quotation-stat-icon{background:#fff5dc;color:#e99a00}
        .quotation-stat-green .quotation-stat-icon{background:#e2f8ef;color:#17855c}
        .quotation-stat-purple .quotation-stat-icon{background:#f1eaff;color:#8052d6}
        .quotation-list-panel{margin-bottom:14px;overflow:hidden;border-radius:12px}
        .quotation-filter-form{display:grid;grid-template-columns:minmax(200px,1.5fr) minmax(135px,1fr) minmax(145px,1.1fr) minmax(145px,1.1fr) auto auto;align-items:end;gap:10px;padding:14px}
        .quotation-filter-field{display:grid;min-width:0;gap:6px;color:#344b62;font-size:11px;font-weight:700}
        .quotation-filter-field input,.quotation-filter-field select,.quotation-input-wrap{width:100%;height:38px;min-width:0;border:1px solid #dce4ed;border-radius:7px;background:#fff;color:#40566c;font:inherit;font-size:11px}
        .quotation-filter-field input,.quotation-filter-field select{padding:0 10px}
        .quotation-filter-field select{cursor:pointer}
        .quotation-input-wrap{display:flex;align-items:center;gap:8px;padding:0 10px}
        .quotation-input-wrap svg{width:16px;height:16px;flex:0 0 16px;fill:none;stroke:#8293a5;stroke-width:2}
        .quotation-input-wrap input{height:100%;padding:0;border:0;outline:0}
        .quotation-filter-button,.quotation-reset-button{height:38px;display:inline-flex;align-items:center;justify-content:center;gap:6px;white-space:nowrap}
        .quotation-table{min-width:700px}
        .quotation-table th{background:#f5f8fb;color:#64788d;font-size:10px}
        .quotation-table td{height:40px}
        .quotation-sort-indicator{margin-left:4px;color:#718196;font-size:13px}
        .quotation-request-code{color:#223b58}
        .quotation-status{display:inline-flex;min-height:25px;align-items:center;gap:6px;padding:4px 10px;border-radius:20px;font-size:10px;font-weight:700}
        .quotation-status>span{font-size:12px}
        .quotation-status.is-draft{background:#fff2d0;color:#966300}
        .quotation-status.is-approved{background:#dff7eb;color:#14734e}
        .quotation-status.is-rejected{background:#ffebea;color:#b9433f}
        .quotation-status.is-pending{background:#e8f2ff;color:#286bc0}
        .quotation-view-button{min-width:68px;display:inline-flex;align-items:center;justify-content:center;gap:6px;color:#286bc0}
        .quotation-empty-state{padding:24px;color:#7b8998;text-align:center}
        .quotation-table-footer{min-height:48px;display:flex;align-items:center;justify-content:space-between;gap:12px;padding:8px 14px;border-top:1px solid #edf1f5;color:#788898;font-size:11px}
        .quotation-pagination .pagination{margin:0}
        @media(max-width:1050px){.quotation-filter-form{grid-template-columns:repeat(3,minmax(0,1fr))}.quotation-filter-button,.quotation-reset-button{width:100%}}
        @media(max-width:900px){.quotation-stats{grid-template-columns:repeat(3,minmax(0,1fr))}}
        @media(max-width:700px){.quotation-stats{grid-template-columns:repeat(2,minmax(0,1fr))}.quotation-page-heading{align-items:flex-start}.quotation-filter-form{grid-template-columns:repeat(2,minmax(0,1fr))}.quotation-search-field{grid-column:1/-1}.quotation-table-footer{align-items:flex-start;flex-direction:column}}
        @media(max-width:420px){.quotation-stat-card{gap:8px;padding:10px}.quotation-stat-icon{width:34px;height:34px;flex-basis:34px;font-size:18px}.quotation-stat-card strong{font-size:17px}.quotation-filter-form{grid-template-columns:1fr}.quotation-search-field{grid-column:auto}}
    </style>
@endsection
