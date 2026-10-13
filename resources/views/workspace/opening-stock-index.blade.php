@extends('layouts.app')

@section('title', 'Opening Stock')
@section('topbar-title', 'Opening Stock')

@section('content')
    <section class="panel opening-stock-list-card">
        <form class="opening-stock-list-toolbar" method="GET" action="{{ route('opening-stock.index') }}">
            @if ($errors->any())
                <div class="form-alert opening-stock-filter-errors" role="alert">
                    <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif
            <div class="opening-stock-list-heading">
                <span class="opening-stock-list-icon"><svg><use href="#icon-box"></use></svg></span>
                <div><h1>Opening Stock</h1><p>View all opening stock entries with location, quantity and opening value.</p></div>
            </div>
            <label class="opening-stock-search">
                <svg aria-hidden="true" viewBox="0 0 24 24"><circle cx="10.8" cy="10.8" r="6.8"></circle><path d="m16 16 5 5"></path></svg>
                <input type="search" name="search" value="{{ $filters['search'] }}" placeholder="Search by stock no., location or product..." aria-label="Search opening stock">
            </label>
            <label class="opening-stock-date-select">
                <svg aria-hidden="true" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="16" rx="2"></rect><path d="M16 3v4M8 3v4M3 10h18"></path></svg>
                <select name="date_range" id="opening-stock-date-range" aria-label="Filter opening stock by date">
                    <option value="all" {{ $filters['date_range'] === 'all' ? 'selected' : '' }}>All Dates</option>
                    <option value="today" {{ $filters['date_range'] === 'today' ? 'selected' : '' }}>Today</option>
                    <option value="this_month" {{ $filters['date_range'] === 'this_month' ? 'selected' : '' }}>This Month</option>
                    <option value="custom" {{ $filters['date_range'] === 'custom' ? 'selected' : '' }}>Custom Range</option>
                </select>
            </label>
            <button class="button button-light opening-stock-filter-button" type="submit">
                <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M3 4h18l-7 8v6l-4 2v-8L3 4z"></path></svg>
                Filter
            </button>
            <a class="button button-primary opening-stock-add-button" href="{{ route('opening-stock.create') }}"><span class="button-plus">+</span> Add Opening Stock</a>
            <div class="opening-stock-custom-dates" id="opening-stock-custom-dates" {{ $filters['date_range'] !== 'custom' ? 'hidden' : '' }}>
                <label for="opening-stock-date-from">From</label>
                <input id="opening-stock-date-from" type="date" name="date_from" value="{{ $filters['date_from'] }}">
                <label for="opening-stock-date-to">To</label>
                <input id="opening-stock-date-to" type="date" name="date_to" value="{{ $filters['date_to'] }}">
            </div>
        </form>

        <div class="opening-stock-list-table-wrap">
            <table class="opening-stock-list-table">
                <thead>
                    <tr>
                        <th># <span aria-hidden="true">↕</span></th>
                        <th>OPENING STOCK NO. <span aria-hidden="true">↕</span></th>
                        <th>LOCATION <span aria-hidden="true">↕</span></th>
                        <th>PRODUCTS <span aria-hidden="true">↕</span></th>
                        <th>TOTAL QUANTITY <span aria-hidden="true">↕</span></th>
                        <th>OPENING VALUE <span aria-hidden="true">↕</span></th>
                        <th>OPENING DATE <span aria-hidden="true">↕</span></th>
                        <th>ENTERED BY <span aria-hidden="true">↕</span></th>
                        <th>CREATED AT <span aria-hidden="true">↕</span></th>
                        <th>ACTION</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td><span class="opening-stock-row-number">{{ $rows->firstItem() + $loop->index }}</span></td>
                            <td>
                                @if ($row->reference_number)
                                    <span class="opening-stock-reference">{{ $row->reference_number }}</span>
                                @else
                                    <span class="opening-stock-no-reference">—</span>
                                @endif
                            </td>
                            <td>{{ $row->location_name ?: '—' }}</td>
                            <td class="opening-stock-products">{{ $row->product_names ?: '—' }}</td>
                            <td>{{ number_format((float) $row->total_quantity, 2) }}</td>
                            <td><span class="opening-stock-value">₹{{ number_format((float) $row->opening_value, 2) }}</span></td>
                            <td><span class="opening-stock-date"><svg aria-hidden="true" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="16" rx="2"></rect><path d="M16 3v4M8 3v4M3 10h18"></path></svg>{{ \Carbon\Carbon::parse($row->transaction_date)->format('d M Y') }}</span></td>
                            <td>
                                @if ($row->created_by_name)
                                    <span class="opening-stock-person"><span class="opening-stock-avatar">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($row->created_by_name, 0, 1)) }}</span>{{ $row->created_by_name }}</span>
                                @else
                                    —
                                @endif
                            </td>
                            <td>{{ $row->created_at ? \Carbon\Carbon::parse($row->created_at)->format('d M Y, h:i A') : '—' }}</td>
                            <td>
                                <details class="opening-stock-row-actions">
                                    <summary aria-label="Actions for opening stock record {{ $row->reference_number ?: $loop->iteration }}">⋮</summary>
                                    <div><a href="{{ route('stock-movement.index', ['search' => $row->reference_number ?: 'Opening Stock']) }}">View stock movements</a></div>
                                </details>
                            </td>
                        </tr>
                    @empty
                        <tr><td class="opening-stock-list-empty" colspan="10">No opening stock records match these filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <footer class="opening-stock-list-footer">
            <span>
                @if ($rows->total())
                    Showing {{ $rows->firstItem() }}–{{ $rows->lastItem() }} of {{ $rows->total() }} records
                @else
                    Showing 0 records
                @endif
                <span class="opening-stock-total-note">· {{ number_format($recordCount) }} total · Opening value ₹{{ number_format($openingValue, 2) }}</span>
            </span>
            @if ($rows->hasPages())
                <div class="opening-stock-pagination">{{ $rows->onEachSide(1)->links() }}</div>
            @endif
        </footer>
    </section>

    <style>
        .opening-stock-list-card{padding:0;border:1px solid #e8edf3;border-radius:16px;overflow:visible;box-shadow:0 5px 18px rgba(20,42,64,.04)}
        .opening-stock-list-toolbar{padding:22px 16px;display:flex;align-items:center;gap:14px;flex-wrap:wrap}
        .opening-stock-filter-errors{flex-basis:100%;margin:0}
        .opening-stock-filter-errors ul{margin:0;padding-left:20px}
        .opening-stock-list-heading{display:flex;align-items:center;gap:14px;flex:1 1 360px;min-width:280px}
        .opening-stock-list-icon{width:56px;height:56px;display:grid;place-items:center;border-radius:12px;background:#e9efff;color:#1454cc}
        .opening-stock-list-icon svg{width:27px;height:27px;fill:none;stroke:currentColor;stroke-width:2}
        .opening-stock-list-heading h1{margin:0 0 5px;color:#172b3d;font-size:20px}
        .opening-stock-list-heading p{margin:0;color:#73859a;font-size:13px}
        .opening-stock-search,.opening-stock-date-select{height:48px;padding:0 14px;display:flex;align-items:center;gap:11px;border:1px solid #dce4ed;border-radius:9px;background:#fff;color:#75879a}
        .opening-stock-search{flex:1 1 300px;max-width:460px}
        .opening-stock-search svg,.opening-stock-date-select svg,.opening-stock-filter-button svg,.opening-stock-date svg{width:20px;height:20px;flex:none;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
        .opening-stock-search input,.opening-stock-date-select select{width:100%;min-width:0;border:0;outline:0;background:transparent;color:#293f56;font:inherit}
        .opening-stock-date-select{min-width:170px}
        .opening-stock-date-select select{appearance:none;cursor:pointer}
        .opening-stock-filter-button{height:48px;display:flex;align-items:center;gap:9px;color:#20354a}
        .opening-stock-filter-button svg{width:18px;height:18px}
        .opening-stock-add-button{height:48px;display:flex;align-items:center;white-space:nowrap}
        .opening-stock-custom-dates{flex-basis:100%;display:flex;justify-content:flex-end;align-items:center;gap:9px;color:#64778b;font-size:13px}
        .opening-stock-custom-dates[hidden]{display:none}
        .opening-stock-custom-dates input{height:38px;padding:0 10px;border:1px solid #dce4ed;border-radius:8px;color:#293f56}
        .opening-stock-list-table-wrap{width:100%;overflow-x:auto}
        .opening-stock-list-table{width:100%;min-width:1240px;border-collapse:collapse}
        .opening-stock-list-table th{padding:15px 14px;background:#f5f8fc;color:#60738a;text-align:left;font-size:12px;font-weight:700;white-space:nowrap}
        .opening-stock-list-table th span{margin-left:4px;color:#99a9ba;font-size:13px}
        .opening-stock-list-table td{padding:17px 14px;border-top:1px solid #edf1f5;color:#263b51;font-size:14px;white-space:nowrap}
        .opening-stock-list-table td.opening-stock-products{min-width:180px;white-space:normal;line-height:1.5}
        .opening-stock-row-number{width:34px;height:34px;display:grid;place-items:center;border-radius:10px;background:#edf2ff;color:#183960;font-weight:700}
        .opening-stock-reference{padding:8px 11px;border-radius:9px;background:#edf3ff;color:#0754d8;font-weight:700}
        .opening-stock-value{padding:8px 11px;border-radius:8px;background:#e5f8ef;color:#14815c;font-weight:700}
        .opening-stock-date{padding:8px 10px;display:inline-flex;align-items:center;gap:8px;border-radius:8px;background:#f1f5fb;color:#354b63}
        .opening-stock-date svg{width:17px;height:17px;fill:none;stroke:#415872;stroke-width:2;stroke-linecap:round}
        .opening-stock-person{display:inline-flex;align-items:center;gap:9px}
        .opening-stock-avatar{width:38px;height:38px;display:inline-grid;place-items:center;border-radius:50%;background:#e0f7f8;color:#087d88;font-weight:700}
        .opening-stock-no-reference{color:#71839a;font-weight:700}
        .opening-stock-row-actions{position:relative}
        .opening-stock-row-actions summary{width:40px;height:40px;display:grid;place-items:center;border:1px solid #dce4ed;border-radius:9px;color:#263b51;font-size:23px;font-weight:700;line-height:1;cursor:pointer;list-style:none}
        .opening-stock-row-actions summary::-webkit-details-marker{display:none}
        .opening-stock-row-actions div{position:absolute;z-index:5;top:44px;right:0;min-width:170px;padding:6px;background:#fff;border:1px solid #e4eaf0;border-radius:9px;box-shadow:0 8px 24px rgba(23,43,61,.12)}
        .opening-stock-row-actions a{display:block;padding:9px 10px;border-radius:6px;color:#263b51;text-decoration:none;font-size:13px}
        .opening-stock-row-actions a:hover{background:#f3f6fa}
        .opening-stock-list-empty{padding:36px!important;text-align:center!important;color:#71839a!important}
        .opening-stock-list-footer{padding:18px 20px;display:flex;align-items:center;justify-content:space-between;gap:15px;color:#354b63;font-size:14px}
        .opening-stock-total-note{color:#8292a3}
        .opening-stock-pagination nav{display:flex;align-items:center;gap:6px}
        .opening-stock-pagination nav>div:first-child{display:none}
        .opening-stock-pagination nav span,.opening-stock-pagination nav a{min-width:38px;height:38px;padding:0 10px;display:inline-flex;align-items:center;justify-content:center;border:1px solid #e0e7ef;border-radius:8px;color:#354b63;text-decoration:none}
        .opening-stock-pagination nav span[aria-current=page]{border-color:#0754e8;background:#0754e8;color:#fff}
        @media(max-width:900px){.opening-stock-list-toolbar{align-items:stretch}.opening-stock-list-heading{flex-basis:100%}.opening-stock-search{max-width:none}.opening-stock-list-footer{align-items:flex-start;flex-direction:column}.opening-stock-total-note{display:block;margin-top:5px}}
        @media(max-width:560px){.opening-stock-list-heading{min-width:0}.opening-stock-list-heading p{line-height:1.5}.opening-stock-date-select,.opening-stock-filter-button,.opening-stock-add-button{flex:1}.opening-stock-custom-dates{justify-content:flex-start;flex-wrap:wrap}.opening-stock-list-footer{padding:16px}}
    </style>
    <script>
        (function () {
            var rangeSelect = document.getElementById('opening-stock-date-range');
            var customDates = document.getElementById('opening-stock-custom-dates');

            if (rangeSelect && customDates) {
                rangeSelect.addEventListener('change', function () {
                    customDates.hidden = rangeSelect.value !== 'custom';
                });
            }
        }());
    </script>
    <script>
        (function () {
            var range = document.getElementById('opening-stock-date-range');
            var customDates = document.getElementById('opening-stock-custom-dates');
            range.addEventListener('change', function () {
                customDates.hidden = range.value !== 'custom';
            });
        }());
    </script>
@endsection
