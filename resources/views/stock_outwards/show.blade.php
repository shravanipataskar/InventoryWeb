
@extends('layouts.app')

@section('title', 'Stock Outward Details')
@section('topbar-title', 'Stock outward details')

@section('content')
<div class="panel" style="padding:24px; background:#fff; border-radius:14px;">

    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:24px;">
        <div>
            <span style="color:#008b8c; font-size:12px; font-weight:700;">
                STOCK MOVEMENT
            </span>
            <h1 style="margin:6px 0; color:#102f40;">
                Stock Outward Details
            </h1>
            <p style="margin:0; color:#647b91;">
                Complete details of the selected outward record.
            </p>
        </div>

        <a class="button button-light"
           href="{{ route('stock-outwards.index', ['status' => $stock->is_active ? 'active' : 'inactive']) }}">
            ← Back to Stock Outward
        </a>
    </div>

    <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:20px;">

        <div>
            <small>Outward Number</small>
            <h3>OUT-{{ str_pad((string) $stock->id, 3, '0', STR_PAD_LEFT) }}</h3>
        </div>

        <div>
            <small>Outward Date</small>
            <h3>
                {{ $stock->outward_date
                    ? \Carbon\Carbon::parse($stock->outward_date)->format('d M Y')
                    : '—' }}
            </h3>
        </div>

        <div>
            <small>Product</small>
            <h3>{{ optional($stock->product)->name ?: '—' }}</h3>
        </div>

        <div>
            <small>Reference Number</small>
            <h3>{{ $stock->reference_number ?: '—' }}</h3>
        </div>

        <div>
            <small>Issued To</small>
            <h3>{{ optional($stock->customer)->name ?: ($stock->issued_to ?: '—') }}</h3>
        </div>

        <div>
            <small>Quantity</small>
            <h3>{{ number_format((float) $stock->quantity, 2) }}</h3>
        </div>

        <div>
            <small>Selling Price</small>
            <h3>₹{{ number_format((float) $stock->selling_price, 2) }}</h3>
        </div>

        <div>
            <small>Total Amount</small>
            <h3 style="color:#008b8c;">
                ₹{{ number_format((float) $stock->total_amount, 2) }}
            </h3>
        </div>

        <div>
            <small>Status</small>
            <h3 style="color:{{ $stock->is_active ? '#16865f' : '#b8771d' }};">
                {{ $stock->is_active ? 'Active' : 'Inactive' }}
            </h3>
        </div>
    </div>

    <hr style="margin:24px 0; border:0; border-top:1px solid #edf1f5;">

    <h3 style="color:#102f40;">Remarks</h3>
    <p style="white-space:pre-wrap; color:#52677b;">{{ $stock->remarks ?: 'No remarks added.' }}</p>

</div>
@endsection
