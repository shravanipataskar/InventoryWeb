<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Purchase Transactions</title>
    <style>
        body{font:12px Arial,sans-serif;color:#20333d;margin:24px}h1{margin:0 0 5px}p{color:#60717a}.report-range{margin:15px 0}table{width:100%;border-collapse:collapse}th,td{border:1px solid #d9e1e4;padding:7px;text-align:left}th{background:#f1f5f6;font-size:10px}.print-action{margin:0 0 18px;padding:9px 14px;border:0;border-radius:5px;color:#fff;background:#0f8b8d;cursor:pointer}@media print{.print-action{display:none}body{margin:0}}
    </style>
</head>
<body>
    <button class="print-action" type="button" onclick="window.print()">Print / Save as PDF</button>
    <h1>Purchase Transactions</h1>
    <p>Track purchases, suppliers, quantities and purchase costs.</p>
    <div class="report-range">Report period: {{ \Carbon\Carbon::parse($filters['date_from'])->format('d M Y') }} – {{ \Carbon\Carbon::parse($filters['date_to'])->format('d M Y') }}</div>
    <table><thead><tr><th>Date</th><th>PO</th><th>GRN</th><th>Invoice</th><th>Supplier</th><th>Product / SKU</th><th>Category</th><th>Location</th><th>Ordered</th><th>Received</th><th>Rejected</th><th>Accepted</th><th>Unit Cost</th><th>Tax</th><th>Total</th><th>Status</th></tr></thead><tbody>
        @foreach ($transactions as $transaction)
            <tr><td>{{ $transaction->purchase_date ?: '—' }}</td><td>{{ $transaction->po_number ?: '—' }}</td><td>{{ $transaction->grn_number ?: '—' }}</td><td>{{ $transaction->invoice_number ?: '—' }}</td><td>{{ $transaction->supplier_name ?: '—' }}</td><td>{{ $transaction->product_name ?: '—' }} / {{ $transaction->product_code ?: '—' }}</td><td>{{ $transaction->category_name ?: '—' }}</td><td>{{ $transaction->location_name ?: '—' }}</td><td>{{ $transaction->ordered_quantity }}</td><td>{{ $transaction->received_quantity }}</td><td>{{ $transaction->rejected_quantity }}</td><td>{{ $transaction->accepted_quantity }}</td><td>{{ $transaction->unit_cost }}</td><td>{{ (float) $transaction->total_tax > 0 ? $transaction->total_tax : '—' }}</td><td>{{ $transaction->total_value }}</td><td>{{ $transaction->status }}</td></tr>
        @endforeach
    </tbody></table>
</body>
</html>
