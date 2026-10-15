@extends('layouts.app')

@section('title', 'Payment History')
@section('topbar-title', 'Payment history')

@section('content')
    <style>
        .payment-history-page{max-width:1440px;margin:0 auto;padding-bottom:28px}
        .payment-history-heading{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin-bottom:18px}
        .payment-history-heading h1{margin:0;color:#172b3d;font-size:24px}
        .payment-history-heading p{margin:6px 0 0;color:#77879a;font-size:12px}
        .payment-history-counts{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:12px;margin-bottom:16px}
        .payment-history-count{min-width:0;padding:14px;border:1px solid #e7edf2;border-radius:12px;background:#fff;box-shadow:0 4px 16px rgba(22,46,67,.035)}
        .payment-history-count .count-title{display:flex;align-items:center;gap:8px;color:#718096;font-size:11px;font-weight:600}
        .payment-history-count .count-icon{display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:8px;background:#edf5f8;color:#08799b;font-size:16px;font-weight:700}
        .payment-history-count strong{display:block;margin-top:10px;color:#183247;font-size:22px;font-weight:700}
        .payment-history-count .count-note{margin-top:4px;color:#8a98a8;font-size:10px}
        .payment-history-count.count-paid{border-top:3px solid #20a66a}
        .payment-history-count.count-paid .count-icon{background:#e7f7ee;color:#16824f}
        .payment-history-count.count-unpaid{border-top:3px solid #dc5962}
        .payment-history-count.count-unpaid .count-icon{background:#ffeaec;color:#c9434d}
        .payment-history-count.count-partial{border-top:3px solid #7544b5}
        .payment-history-count.count-partial .count-icon{background:#f2eaff;color:#7544b5}
        .payment-history-count.count-overdue{border-top:3px solid #ed922b}
        .payment-history-count.count-overdue .count-icon{background:#fff3e2;color:#b86c12}
        .payment-history-count.count-pending{border-top:3px solid #4389d7}
        .payment-history-count.count-pending .count-icon{background:#eaf2ff;color:#326fc2}
        .payment-history-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:13px;margin-bottom:18px}
        .payment-history-kpi{min-width:0;padding:16px;border:1px solid #e7edf2;border-radius:12px;background:#fff;box-shadow:0 4px 16px rgba(22,46,67,.035)}
        .payment-history-kpi small{display:block;margin-bottom:9px;color:#77879a;font-size:11px}
        .payment-history-kpi strong{display:block;color:#18385f;font-size:21px;font-weight:700}
        .payment-history-kpi .kpi-note{margin-top:6px;color:#8a98a8;font-size:10px}
        .payment-history-kpi.kpi-paid{border-top:3px solid #20a66a}
        .payment-history-kpi.kpi-outstanding{border-top:3px solid #15999b}
        .payment-history-kpi.kpi-partial{border-top:3px solid #4389d7}
        .payment-history-kpi.kpi-overdue{border-top:3px solid #dc5962}
        .payment-history-panel{overflow:hidden;border:1px solid #e7edf2;border-radius:13px;background:#fff;box-shadow:0 4px 16px rgba(22,46,67,.035)}
        .payment-history-filter-form{padding:16px 16px 0}
        .payment-history-tabs{display:flex;gap:7px;overflow-x:auto;padding-bottom:13px;border-bottom:1px solid #edf1f5}
        .payment-history-tab{display:inline-flex;align-items:center;gap:6px;white-space:nowrap;padding:8px 12px;border:1px solid #e5eaf0;border-radius:8px;color:#617185;background:#fff;text-decoration:none;font-size:11px;font-weight:600}
        .payment-history-tab:hover{border-color:#9bd3d4;color:#087f83}
        .payment-history-tab.is-active{border-color:#148f92;background:#eaf8f7;color:#087f83}
        .payment-history-tab-count{display:inline-flex;align-items:center;justify-content:center;min-width:19px;height:19px;padding:0 5px;border-radius:10px;background:#eef2f6;color:#64748b;font-size:9px;font-weight:700}
        .payment-history-tab.is-active .payment-history-tab-count{background:#d2efed;color:#087f83}
        .payment-history-toolbar{display:grid;grid-template-columns:minmax(220px,1.5fr) minmax(145px,1fr) repeat(2,minmax(130px,.75fr)) minmax(100px,.5fr) auto auto;gap:9px;align-items:center;padding:14px 0}
        .payment-history-toolbar .field-control,.payment-history-toolbar .filter-select{width:100%;min-height:38px}
        .payment-history-search{position:relative}
        .payment-history-search input{padding-left:34px}
        .payment-history-search span{position:absolute;left:12px;top:9px;color:#8291a2;font-size:17px}
        .payment-history-table-wrap{overflow-x:auto}
        .payment-history-table{width:100%;min-width:1180px;border-collapse:collapse}
        .payment-history-table th{padding:11px 9px;background:#f7f9fc;color:#748398;font-size:9px;text-align:left;white-space:nowrap}
        .payment-history-table td{padding:11px 9px;border-top:1px solid #eef1f5;color:#40556c;font-size:10px;vertical-align:middle}
        .payment-history-table tbody tr:hover{background:#fbfcfe}
        .payment-history-voucher{display:block;color:#18385f;font-size:10px;font-weight:700;white-space:nowrap}
        .payment-history-subtext{display:block;margin-top:4px;color:#8b98a8;font-size:9px;white-space:nowrap}
        .payment-history-po{display:block;color:#284d72;font-weight:600}
        .payment-history-bill{display:block;margin-top:3px;color:#8b98a8;font-size:9px}
        .payment-history-money{white-space:nowrap;font-variant-numeric:tabular-nums}
        .payment-history-progress{display:flex;align-items:center;gap:7px;min-width:105px}
        .payment-history-progress-track{width:48px;height:5px;overflow:hidden;border-radius:5px;background:#e9eef3}
        .payment-history-progress-track span{display:block;height:100%;border-radius:5px;background:#20a66a}
        .payment-history-progress strong{color:#53677d;font-size:9px;white-space:nowrap}
        .payment-history-badge{display:inline-flex;align-items:center;white-space:nowrap;padding:5px 8px;border-radius:12px;font-size:9px;font-weight:700}
        .payment-history-badge.is-paid{background:#e7f7ee;color:#16824f}
        .payment-history-badge.is-partial{background:#eaf2ff;color:#326fc2}
        .payment-history-badge.is-unpaid{background:#fff3e2;color:#b86c12}
        .payment-history-badge.is-overdue{background:#ffeaec;color:#c9434d}
        .payment-history-badge.is-pending{background:#f2eaff;color:#7544b5}
        .payment-history-badge.is-neutral{background:#eef1f5;color:#657386}
        .payment-history-footer{display:flex;align-items:center;justify-content:space-between;gap:14px;padding:12px 16px;border-top:1px solid #edf1f5;color:#78879a;font-size:10px}
        .payment-history-footer .pagination{margin:0}
        .payment-history-empty{padding:30px 14px;text-align:center;color:#8391a1;font-size:11px}
        .payment-history-empty strong{display:block;margin-bottom:5px;color:#324b65;font-size:13px}
        @media(max-width:1100px){.payment-history-counts{grid-template-columns:repeat(3,minmax(0,1fr))}.payment-history-toolbar{grid-template-columns:repeat(3,minmax(0,1fr))}.payment-history-search{grid-column:span 2}}
        @media(max-width:700px){.payment-history-counts,.payment-history-kpis{grid-template-columns:repeat(2,minmax(0,1fr))}.payment-history-toolbar{grid-template-columns:repeat(2,minmax(0,1fr))}.payment-history-search{grid-column:span 2}.payment-history-heading{flex-direction:column}}
        @media(max-width:430px){.payment-history-counts,.payment-history-kpis{gap:8px}.payment-history-count,.payment-history-kpi{padding:12px}.payment-history-count strong,.payment-history-kpi strong{font-size:17px}.payment-history-toolbar{grid-template-columns:1fr}.payment-history-search{grid-column:auto}.payment-history-footer{align-items:flex-start;flex-direction:column}}
    </style>

    <main class="payment-history-page">
        <div class="payment-history-heading">
            <div>
                <span class="section-kicker">PURCHASING / PAYABLES</span>
                <h1>Payment History</h1>
                <p>Track confirmed purchase payments and supplier payment requests with their related bill balances.</p>
            </div>
            @if (auth()->user()->hasPermission('payments', 'view'))
                <a class="button button-light" href="{{ route('supplier-payments.index') }}">Manage Payments</a>
            @endif
        </div>
        @include('components.flash')
        @if ($errors->any())
            <div class="form-alert" role="alert">{{ $errors->first() }}</div>
        @endif

        <section class="payment-history-counts" aria-label="Payment record counts">
            <article class="payment-history-count">
                <div class="count-title"><span class="count-icon" aria-hidden="true">▤</span>Total Payments</div>
                <strong>{{ number_format($statusCounts['all']) }}</strong>
                <div class="count-note">All payment history entries</div>
            </article>
            <article class="payment-history-count count-paid">
                <div class="count-title"><span class="count-icon" aria-hidden="true">✓</span>Paid</div>
                <strong>{{ number_format($statusCounts['paid']) }}</strong>
                <div class="count-note">Fully settled records</div>
            </article>
            <article class="payment-history-count count-unpaid">
                <div class="count-title"><span class="count-icon" aria-hidden="true">×</span>Unpaid</div>
                <strong>{{ number_format($statusCounts['unpaid']) }}</strong>
                <div class="count-note">No confirmed payment</div>
            </article>
            <article class="payment-history-count count-partial">
                <div class="count-title"><span class="count-icon" aria-hidden="true">◒</span>Partially Paid</div>
                <strong>{{ number_format($statusCounts['partially_paid']) }}</strong>
                <div class="count-note">Open balance remains</div>
            </article>
            <article class="payment-history-count count-overdue">
                <div class="count-title"><span class="count-icon" aria-hidden="true">!</span>Overdue</div>
                <strong>{{ number_format($statusCounts['overdue']) }}</strong>
                <div class="count-note">Past due with a balance</div>
            </article>
            <article class="payment-history-count count-pending">
                <div class="count-title"><span class="count-icon" aria-hidden="true">◷</span>Pending Approval</div>
                <strong>{{ number_format($statusCounts['pending_approval']) }}</strong>
                <div class="count-note">Awaiting payment approval</div>
            </article>
        </section>

        <section class="payment-history-kpis" aria-label="Financial summary">
            <article class="payment-history-kpi kpi-paid">
                <small>Total Paid</small>
                <strong>₹{{ number_format((float) $summary['paid'], 2) }}</strong>
                <div class="kpi-note">Confirmed supplier and purchase payments</div>
            </article>
            <article class="payment-history-kpi kpi-outstanding">
                <small>Outstanding Amount</small>
                <strong>₹{{ number_format((float) $summary['outstanding'], 2) }}</strong>
                <div class="kpi-note">Approved bills and purchases not yet billed</div>
            </article>
            <article class="payment-history-kpi kpi-partial">
                <small>Partially Paid Bills</small>
                <strong>{{ number_format($summary['partially_paid']) }}</strong>
                <div class="kpi-note">Confirmed payments with an open balance</div>
            </article>
            <article class="payment-history-kpi kpi-overdue">
                <small>Overdue Bills</small>
                <strong>{{ number_format($summary['overdue']) }}</strong>
                <div class="kpi-note">Past due date with an outstanding balance</div>
            </article>
        </section>

        <section class="payment-history-panel" aria-label="Payment transactions">
            <form class="payment-history-filter-form" method="GET" action="{{ route('supplier-bills.index') }}">
                <nav class="payment-history-tabs" aria-label="Payment status filters">
                    @foreach ([
                        'all' => 'All Payments',
                        'paid' => 'Paid',
                        'partially_paid' => 'Partially Paid',
                        'unpaid' => 'Unpaid',
                        'overdue' => 'Overdue',
                        'pending_approval' => 'Pending Approval',
                    ] as $key => $label)
                        <a class="payment-history-tab {{ $filter === $key ? 'is-active' : '' }}"
                           href="{{ route('supplier-bills.index', array_merge(request()->except('page', 'filter', 'export'), ['filter' => $key])) }}"
                           {{ $filter === $key ? 'aria-current=page' : '' }}>{{ $label }} <span class="payment-history-tab-count">{{ number_format($statusCounts[$key]) }}</span></a>
                    @endforeach
                </nav>
                <div class="payment-history-toolbar">
                    <label class="payment-history-search">
                        <span aria-hidden="true">⌕</span>
                        <input class="field-control" type="search" name="search" value="{{ request('search') }}" placeholder="Search by payment voucher, supplier, PO or bill..." aria-label="Search payment history">
                    </label>
                    <select class="filter-select" name="supplier_id" aria-label="Filter by supplier">
                        <option value="">All suppliers</option>
                        @foreach ($suppliers as $supplier)
                            <option value="{{ $supplier->id }}" {{ (int) request('supplier_id') === $supplier->id ? 'selected' : '' }}>{{ $supplier->name }}</option>
                        @endforeach
                    </select>
                    <input class="field-control" type="date" name="from_date" value="{{ request('from_date') }}" aria-label="From date">
                    <input class="field-control" type="date" name="to_date" value="{{ request('to_date') }}" aria-label="To date">
                    <select class="field-control" name="per_page" aria-label="Records per page">
                        @foreach ([10, 20, 50] as $pageSize)
                            <option value="{{ $pageSize }}" {{ (int) request('per_page', 20) === $pageSize ? 'selected' : '' }}>{{ $pageSize }} per page</option>
                        @endforeach
                    </select>
                    <input type="hidden" name="filter" value="{{ $filter }}">
                    <button class="button button-primary button-small" type="submit">Apply Filters</button>
                    <button class="button button-light button-small" type="submit" name="export" value="csv">Export CSV</button>
                </div>
            </form>

            <div class="payment-history-table-wrap">
                <table class="payment-history-table">
                    <thead>
                        <tr>
                            <th>SR. NO.</th>
                            <th>PAYMENT VOUCHER NO.</th>
                            <th>ACTUAL PAYMENT DATE</th>
                            <th>SUPPLIER</th>
                            <th>PURCHASE ORDER / BILL NO.</th>
                            <th>TOTAL BILL AMOUNT (₹)</th>
                            <th>TOTAL PAID (₹)</th>
                            <th>OUTSTANDING (₹)</th>
                            <th>PAYMENT PERCENTAGE</th>
                            <th>STATUS</th>
                            <th>RECORDED BY</th>
                            <th>ACTION</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse ($records as $record)
                        @php
                            $statusClass = [
                                'Paid' => 'is-paid',
                                'Partially Paid' => 'is-partial',
                                'Unpaid' => 'is-unpaid',
                                'Overdue' => 'is-overdue',
                                'Pending Approval' => 'is-pending',
                            ][$record->display_status] ?? 'is-neutral';
                            $voucher = $record->source_type === 'purchase_payment'
                                ? 'PP-' . str_pad($record->source_id, 8, '0', STR_PAD_LEFT)
                                : $record->voucher_number;
                        @endphp
                        <tr>
                            <td>{{ $records->firstItem() + $loop->index }}</td>
                            <td>
                                <span class="payment-history-voucher">{{ $voucher ?: '—' }}</span>
                                @if ($record->source_type !== 'bill')
                                    <span class="payment-history-subtext">Payment: ₹{{ number_format((float) $record->payment_amount, 2) }}</span>
                                @else
                                    <span class="payment-history-subtext">No confirmed payment yet</span>
                                @endif
                            </td>
                            <td>{{ $record->payment_date ? \Carbon\Carbon::parse($record->payment_date)->format('d M Y') : '—' }}</td>
                            <td>{{ $record->supplier_name ?: '—' }}</td>
                            <td>
                                <span class="payment-history-po">{{ $record->purchase_order_number ?: '—' }}</span>
                                @if ($record->bill_number)
                                    <span class="payment-history-bill">{{ $record->bill_number }}{{ (int) $record->bill_count > 1 ? ' +' . ((int) $record->bill_count - 1) . ' more' : '' }}</span>
                                @endif
                            </td>
                            <td class="payment-history-money">₹{{ number_format((float) $record->bill_total, 2) }}</td>
                            <td class="payment-history-money">₹{{ number_format((float) $record->bill_paid, 2) }}</td>
                            <td class="payment-history-money">₹{{ number_format((float) $record->bill_outstanding, 2) }}</td>
                            <td>
                                <div class="payment-history-progress" role="progressbar" aria-label="Bill payment completion" aria-valuenow="{{ number_format($record->payment_percentage, 2, '.', '') }}" aria-valuemin="0" aria-valuemax="100">
                                    <span class="payment-history-progress-track"><span style="width:{{ min(100, $record->payment_percentage) }}%"></span></span>
                                    <strong>{{ number_format($record->payment_percentage, 2) }}%</strong>
                                </div>
                            </td>
                            <td><span class="payment-history-badge {{ $statusClass }}">{{ $record->display_status }}</span></td>
                            <td>{{ $record->recorded_by_name ?: '—' }}</td>
                            <td>
                                @if ($record->source_type === 'supplier_payment')
                                    <a class="button button-small button-light" href="{{ route('supplier-payments.show', $record->source_id) }}">View</a>
                                @elseif ($record->source_type === 'purchase_payment')
                                    <a class="button button-small button-light" href="{{ route('payment-history.purchase-payments.show', $record->source_id) }}">View</a>
                                @elseif ($record->source_type === 'bill')
                                    <a class="button button-small button-light" href="{{ route('supplier-bills.show', $record->source_id) }}">View</a>
                                @else
                                    <span>—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="12">
                                <div class="payment-history-empty">
                                    <strong>No payment history found</strong>
                                    @if ($filter === 'unpaid')
                                        No approved bills without confirmed payments match these filters.
                                    @elseif ($filter === 'overdue')
                                        No overdue bills with an outstanding balance match these filters.
                                    @elseif ($filter === 'pending_approval')
                                        No payment requests are waiting for approval.
                                    @else
                                        Try changing your search, supplier, or date filters.
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="payment-history-footer">
                <span>
                    @if ($records->total())
                        Showing {{ number_format($records->firstItem()) }}–{{ number_format($records->lastItem()) }} of {{ number_format($records->total()) }} records
                    @else
                        Showing 0 records
                    @endif
                </span>
                {{ $records->links() }}
            </div>
        </section>
    </main>
@endsection
