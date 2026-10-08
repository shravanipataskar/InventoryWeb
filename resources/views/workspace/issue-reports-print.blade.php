<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Issue Reports</title>
    <style>
        body{font:12px Arial,sans-serif;color:#203944;margin:24px}h1{margin:0 0 6px}p{color:#60727a;margin:0 0 18px}.summary{margin:8px 0 18px;color:#60727a}table{width:100%;border-collapse:collapse}th,td{padding:7px;border:1px solid #dce4e7;text-align:left}th{background:#f1f6f7;font-size:10px}td{font-size:10px}.no-print{margin-bottom:16px}@media print{.no-print{display:none}body{margin:0}}
    </style>
</head>
<body>
    <div class="no-print"><button onclick="window.print()">Print / Save as PDF</button></div>
    <h1>Issue Reports</h1>
    <p>Active stock outward records · Cost valued at product purchase price</p>
    <div class="summary">Date: {{ $filters['date_from'] }} to {{ $filters['date_to'] }} · {{ $issues->count() }} product lines</div>
    <table>
        <thead><tr><th>Date</th><th>Reference</th><th>Product</th><th>SKU</th><th>Category</th><th>Recipient</th><th>Product Location</th><th>Qty</th><th>Unit Cost</th><th>Total Value</th></tr></thead>
        <tbody>
        @foreach ($issues as $issue)
            @php $location = implode(' / ', array_filter([$issue->hall_name, $issue->rack_name, $issue->shelf_name])); @endphp
            <tr><td>{{ $issue->issue_date ?: '—' }}</td><td>{{ $issue->reference_number ?: '—' }}</td><td>{{ $issue->product_name ?: '—' }}</td><td>{{ $issue->product_code ?: '—' }}</td><td>{{ $issue->category_name ?: '—' }}</td><td>{{ $issue->recipient_name ?: '—' }}</td><td>{{ $location ?: '—' }}</td><td>{{ number_format((float) $issue->quantity, 2) }}</td><td>₹{{ number_format((float) $issue->unit_cost, 2) }}</td><td>₹{{ number_format((float) $issue->total_value, 2) }}</td></tr>
        @endforeach
        </tbody>
    </table>
</body>
</html>
