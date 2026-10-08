@extends('layouts.app')

@section('title', 'Stock Transfer Details')
@section('topbar-title', 'Stock transfer details')

@section('content')
    <div class="page-heading workspace-page-heading">
        <div>
            <span class="section-kicker">INVENTORY &nbsp; › &nbsp; STOCK TRANSFER</span>
            <h1>Transfer {{ $transfer->transfer_number }}</h1>
            <p>Review the transfer details, affected location balances, and stock movement entries.</p>
        </div>
        <a class="button button-light" href="{{ route('stock-transfers.index') }}">← Back to Stock Transfers</a>
    </div>

    <section class="form-card">
        <div class="form-section">
            <div class="form-card-heading">
                <span class="form-section-icon"><svg><use href="#icon-transfer"></use></svg></span>
                <div><h2>Transfer Information</h2><p>Transfer header and audit information.</p></div>
            </div>
            <div class="form-grid">
                <div class="field"><label>Transfer No.</label><strong>{{ $transfer->transfer_number }}</strong></div>
                <div class="field"><label>Transfer Date</label><strong>{{ \Carbon\Carbon::parse($transfer->transfer_date)->format('d M Y') }}</strong></div>
                <div class="field"><label>Transfer Type</label><strong>{{ $transfer->transfer_type ?? 'Internal Stock Transfer' }}</strong></div>
                <div class="field"><label>Status</label><strong>{{ ucfirst($transfer->status ?? ($transfer->is_active ? 'completed' : 'cancelled')) }}</strong></div>
                <div class="field"><label>From Location</label><strong>{{ $transfer->from_location_label }}</strong></div>
                <div class="field"><label>To Location</label><strong>{{ $transfer->to_location_label }}</strong></div>
                <div class="field"><label>Transfer Reason</label><strong>{{ $transfer->transfer_reason ?? '—' }}</strong></div>
                <div class="field"><label>Reference No.</label><strong>{{ $transfer->reference_no ?? '—' }}</strong></div>
                <div class="field"><label>Requested By</label><strong>{{ $transfer->requested_by_name ?? '—' }}</strong></div>
                <div class="field"><label>Approved By</label><strong>{{ $transfer->approved_by_name ?? '—' }}</strong></div>
                <div class="field field-wide"><label>Notes</label><p>{{ $transfer->remarks ?: 'No notes provided.' }}</p></div>
            </div>
        </div>

        <div class="form-section">
            <div class="form-card-heading">
                <span class="form-section-icon form-icon-violet"><svg><use href="#icon-box"></use></svg></span>
                <div><h2>Transfer Items</h2><p>{{ $items->count() }} product line(s) recorded.</p></div>
            </div>
            <div class="table-wrap">
                <table class="data-table workspace-table">
                    <thead><tr><th>Category</th><th>Product</th><th>SKU</th><th>Batch / Lot</th><th>Serial Numbers</th><th>Available Before</th><th>Transfer Qty</th><th>Unit</th><th>Remarks</th></tr></thead>
                    <tbody>
                        @foreach ($items as $item)
                            <tr>
                                <td>{{ $item->category_name ?: '—' }}</td>
                                <td>{{ $item->product_name }}</td>
                                <td>{{ $item->sku ?: $item->product_code }}</td>
                                <td>—</td>
                                <td>Not required</td>
                                <td>{{ $item->source_before === null ? '—' : number_format((float) $item->source_before, 2) }}</td>
                                <td>{{ number_format((float) $item->quantity, 2) }}</td>
                                <td>{{ $item->unit_name ?: '—' }}</td>
                                <td>{{ $item->remarks ?: '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="summary-row">
            <div class="summary-card">
                <h3>Stock Preview</h3>
                @foreach ($items as $item)
                    <dl>
                        <div><dt>{{ $transfer->from_location_label }} — {{ $item->product_name }}</dt><dd>Before {{ $item->source_before === null ? '—' : number_format((float) $item->source_before, 2) }} &nbsp; −{{ number_format((float) $item->quantity, 2) }} &nbsp; After {{ $item->source_before === null ? '—' : number_format((float) $item->source_before - (float) $item->quantity, 2) }}</dd></div>
                        <div><dt>{{ $transfer->to_location_label }} — {{ $item->product_name }}</dt><dd>Before {{ $item->destination_before === null ? '—' : number_format((float) $item->destination_before, 2) }} &nbsp; +{{ number_format((float) $item->quantity, 2) }} &nbsp; After {{ $item->destination_before === null ? '—' : number_format((float) $item->destination_before + (float) $item->quantity, 2) }}</dd></div>
                    </dl>
                @endforeach
            </div>
            <div class="validation-card">
                <h3>Stock Movement References</h3>
                @if ($movements->count())
                    <ul class="stock-checks">
                        @foreach ($movements as $movement)
                            <li><span class="status {{ $movement->transaction_type === 'transfer_out' ? 'invalid' : 'valid' }}">{{ $movement->transaction_type === 'transfer_out' ? 'Out' : 'In' }}</span><div><strong>{{ $movement->location_name }}</strong><small>{{ $movement->transaction_type === 'transfer_out' ? '-' : '+' }}{{ number_format((float) ($movement->quantity_out ?: $movement->quantity_in), 2) }} · {{ \Carbon\Carbon::parse($movement->transaction_date)->format('d M Y') }}</small></div></li>
                        @endforeach
                    </ul>
                @else
                    <p>No linked stock ledger movement is available for this legacy transfer.</p>
                @endif
            </div>
        </div>
    </section>
@endsection
