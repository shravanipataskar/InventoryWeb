<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Purchase Order {{ $purchaseOrder->po_number }}</title>
    <style>
        :root{color-scheme:light;--navy:#17365f;--blue:#2375bd;--pale:#eaf3fc;--line:#dce5ee;--muted:#66788c;--ink:#26394d}
        *{box-sizing:border-box}
        body{margin:0;background:#eef2f6;color:var(--ink);font:12px/1.45 Arial,Helvetica,sans-serif}
        .print-actions{position:sticky;top:0;z-index:2;display:flex;justify-content:center;gap:10px;padding:12px;background:#17365f}
        .print-actions button,.print-actions a{border:0;border-radius:5px;padding:9px 16px;background:#fff;color:var(--navy);font:700 12px Arial,sans-serif;text-decoration:none;cursor:pointer}
        .print-actions .primary{background:#28a5b8;color:#fff}
        .document{width:210mm;min-height:297mm;margin:18px auto;padding:12mm;background:#fff;box-shadow:0 4px 22px rgba(26,48,75,.14)}
        .masthead{display:flex;justify-content:space-between;align-items:flex-start;gap:24px;padding-bottom:15px;border-bottom:3px solid var(--navy)}
        .brand{display:flex;align-items:center;gap:11px;color:var(--navy)}
        .brand-mark{display:grid;width:40px;height:40px;place-items:center;border-radius:10px;background:#f3a712;color:#17365f}
        .brand-mark svg{width:24px;height:24px;fill:none;stroke:currentColor;stroke-width:2}
        .brand-name{font-size:16px;font-weight:700;line-height:1.15}
        .brand-name span{color:#21899b}
        .brand-name small{display:block;margin-top:4px;color:var(--muted);font-size:8px;letter-spacing:1.2px;text-transform:uppercase}
        .company-address{max-width:360px;margin:8px 0 0;color:var(--muted);font-size:10px;white-space:pre-line}
        .document-title{text-align:right}
        .document-title h1{margin:0;color:var(--navy);font-size:23px;letter-spacing:.5px}
        .document-title strong{display:block;margin-top:4px;color:var(--blue);font-size:15px}
        .status{display:inline-block;margin-top:8px;padding:4px 9px;border:1px solid #cbdced;border-radius:20px;background:var(--pale);color:var(--navy);font-size:9px;font-weight:700;text-transform:uppercase}
        .meta-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:9px;margin:14px 0}
        .meta-cell{padding:9px 10px;border:1px solid var(--line);border-radius:5px}
        .meta-cell small,.field small{display:block;margin-bottom:3px;color:var(--muted);font-size:9px;text-transform:uppercase;letter-spacing:.35px}
        .meta-cell strong,.field strong{color:var(--navy);font-size:11px;overflow-wrap:anywhere}
        .section{margin-top:14px;break-inside:avoid}
        .section h2{margin:0 0 8px;padding-bottom:6px;border-bottom:1px solid var(--line);color:var(--navy);font-size:12px;text-transform:uppercase;letter-spacing:.5px}
        .two-column{display:grid;grid-template-columns:1fr 1fr;gap:18px}
        .fields{display:grid;grid-template-columns:1fr 1fr;gap:9px 16px}
        .field.wide{grid-column:1/-1}
        .bank-fields{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}
        table{width:100%;border-collapse:collapse}
        .items-table{width:100%;margin-top:14px;font-size:9px}
        .items-table thead{display:table-header-group}
        .items-table th{padding:8px 6px;background:var(--pale);color:var(--navy);font-size:8px;text-align:left;white-space:nowrap}
        .items-table td{padding:7px 6px;border-bottom:1px solid var(--line);vertical-align:top}
        .items-table tr{break-inside:avoid;page-break-inside:avoid}
        .items-table .number{text-align:right;white-space:nowrap}
        .product-name{color:var(--navy);font-weight:700}
        .product-image{display:block;width:32px;height:32px;object-fit:contain}
        .summary-wrap{display:flex;justify-content:flex-end;margin-top:10px;break-inside:avoid;page-break-inside:avoid}
        .summary{width:280px}
        .summary td{padding:4px 8px;border-bottom:1px solid #edf1f5}
        .summary td:last-child{text-align:right;white-space:nowrap}
        .summary .grand-total td{border-top:2px solid var(--navy);border-bottom:0;color:var(--navy);font-size:13px;font-weight:700}
        .terms{margin:0;padding-left:20px}
        .terms li{padding:2px 0}
        .footer{margin-top:20px;padding-top:10px;border-top:1px solid var(--line);color:var(--navy);font-size:10px;font-weight:700;text-align:center}
        @page{size:A4 portrait;margin:12mm;@bottom-right{content:"Page " counter(page) " of " counter(pages);color:#66788c;font:9px Arial,sans-serif}}
        @media print{
            body{background:#fff;font-size:10px;-webkit-print-color-adjust:exact;print-color-adjust:exact}
            .print-actions{display:none!important}
            .document{width:auto;min-height:0;margin:0;padding:0;box-shadow:none}
            .section{break-inside:avoid}
            .items-table{break-inside:auto}
            .items-table{table-layout:fixed;font-size:7px}
            .items-table th,.items-table td{padding:5px 3px;overflow-wrap:anywhere}
            .items-table th{font-size:6px;white-space:normal}
            .items-table th:nth-child(1),.items-table td:nth-child(1){width:4%}
            .items-table th:nth-child(2),.items-table td:nth-child(2){width:16%}
            .items-table th:nth-child(3),.items-table td:nth-child(3){width:9%}
            .items-table th:nth-child(4),.items-table td:nth-child(4){width:7%}
            .items-table th:nth-child(5),.items-table td:nth-child(5){width:6%}
            .items-table th:nth-child(6),.items-table td:nth-child(6){width:9%}
            .items-table th:nth-child(7),.items-table td:nth-child(7){width:7%}
            .items-table th:nth-child(8),.items-table td:nth-child(8){width:9%}
            .items-table th:nth-child(9),.items-table td:nth-child(9){width:6%}
            .items-table th:nth-child(10),.items-table td:nth-child(10){width:9%}
            .items-table th:nth-child(11),.items-table td:nth-child(11){width:10%}
            .items-table tr,.summary-wrap,.footer{break-inside:avoid;page-break-inside:avoid}
            a{color:inherit;text-decoration:none}
        }
        @media screen and (max-width:760px){
            .document{width:100%;min-height:0;margin:0;padding:22px}
            .masthead{flex-direction:column}
            .document-title{text-align:left}
            .meta-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
            .two-column{grid-template-columns:1fr}
            .bank-fields{grid-template-columns:repeat(2,minmax(0,1fr))}
            .items-table-wrap{overflow-x:auto}
            .items-table{min-width:780px}
        }
    </style>
</head>
<body>
    @php
        $supplier = $purchaseOrder->supplier;
        $lineSubtotal = 0;
        $lineDiscount = 0;
        $lineCgst = 0;
        $lineSgst = 0;
        $lineTax = 0;
        $lines = $purchaseOrder->items->map(function ($item) use (&$lineSubtotal, &$lineDiscount, &$lineCgst, &$lineSgst, &$lineTax) {
            $quantity = (float) $item->ordered_quantity;
            $subtotal = round($quantity * (float) $item->purchase_rate, 2);
            $discount = round($subtotal * (float) $item->discount_percent / 100, 2);
            $taxable = round($subtotal - $discount, 2);
            $cgst = (float) $item->cgst_amount;
            $sgst = (float) $item->sgst_amount;
            if ($cgst == 0.0 && $sgst == 0.0 && (float) $item->tax_percent > 0) {
                $cgstRate = (float) $item->cgst_rate;
                $sgstRate = (float) $item->sgst_rate;
                if ($cgstRate + $sgstRate <= 0) {
                    $cgstRate = round((float) $item->tax_percent / 2, 2);
                    $sgstRate = round((float) $item->tax_percent - $cgstRate, 2);
                }
                $cgst = round($taxable * $cgstRate / 100, 2);
                $sgst = round($taxable * $sgstRate / 100, 2);
            }
            $tax = round($cgst + $sgst, 2);
            $total = (float) $item->total_amount ?: round($taxable + $tax, 2);
            $lineSubtotal += $subtotal;
            $lineDiscount += $discount;
            $lineCgst += $cgst;
            $lineSgst += $sgst;
            $lineTax += $tax;
            return compact('item', 'quantity', 'subtotal', 'discount', 'taxable', 'cgst', 'sgst', 'tax', 'total');
        });
        $subtotal = $purchaseOrder->subtotal !== null ? (float) $purchaseOrder->subtotal : $lineSubtotal;
        $discount = (float) $purchaseOrder->discount_amount;
        if ($discount == 0.0 && $lineDiscount > 0) {
            $discount = $lineDiscount;
        }
        $taxableAmount = round($subtotal - $discount, 2);
        $cgst = (float) $purchaseOrder->cgst_total;
        $sgst = (float) $purchaseOrder->sgst_total;
        if ($cgst == 0.0 && $sgst == 0.0 && $lineTax > 0) {
            $cgst = $lineCgst;
            $sgst = $lineSgst;
        }
        $tax = $purchaseOrder->tax_amount !== null ? (float) $purchaseOrder->tax_amount : $lineTax;
        if ($tax == 0.0 && $lineTax > 0) {
            $tax = $lineTax;
        }
        $igst = max(0, round($tax - $cgst - $sgst, 2));
        $grandTotal = $purchaseOrder->grand_total !== null
            ? (float) $purchaseOrder->grand_total
            : round($taxableAmount + $tax, 2);
        $accountNumber = optional($supplier)->account_number;
        $maskedAccount = $accountNumber
            ? str_repeat('•', max(0, strlen($accountNumber) - 4)) . substr($accountNumber, -4)
            : '—';
        $sourceReferences = collect([
            optional($purchaseOrder->quotation)->quotation_code,
            optional(optional($purchaseOrder->quotation)->request)->quotation_request_code,
        ])->filter()->unique()->implode(', ');
        $deliveryLocation = collect([
            optional($purchaseOrder->store)->name,
            optional($purchaseOrder->store)->location,
        ])->filter()->implode(', ') ?: '—';
        $supplierAddress = collect([
            optional($supplier)->address,
            optional($supplier)->city,
            optional($supplier)->state,
            optional($supplier)->pincode,
        ])->filter()->implode(', ');
        $statusLabel = $purchaseOrder->status === 'received'
            ? 'Fully Received'
            : ucwords(str_replace('_', ' ', $purchaseOrder->status));
    @endphp
    <nav class="print-actions" aria-label="Print controls">
        <button class="primary" type="button" onclick="window.print()">Print / Save PDF</button>
        <a href="{{ route('purchase-orders.show', $purchaseOrder->id) }}">Back to Purchase Order</a>
    </nav>
    <main class="document">
        <header class="masthead">
            <div>
                <div class="brand">
                    <span class="brand-mark" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="m12 2 9 5v10l-9 5-9-5V7l9-5Zm-9 5 9 5 9-5m-9 5v10m-4-13 9-5"/></svg></span>
                    <div class="brand-name">Aayojan <span>Ai</span><small>Inventory System</small></div>
                </div>
                @if ($companyAddress)
                    <p class="company-address">{{ $companyAddress }}</p>
                @endif
            </div>
            <div class="document-title">
                <h1>PURCHASE ORDER</h1>
                <strong>{{ $purchaseOrder->po_number }}</strong>
                <span class="status">{{ $statusLabel }}</span>
            </div>
        </header>

        <section class="meta-grid" aria-label="Order summary">
            <div class="meta-cell"><small>Order Date</small><strong>{{ $purchaseOrder->order_date ? \Carbon\Carbon::parse($purchaseOrder->order_date)->format('d M Y') : '—' }}</strong></div>
            <div class="meta-cell"><small>Expected Delivery</small><strong>{{ $purchaseOrder->expected_date ? \Carbon\Carbon::parse($purchaseOrder->expected_date)->format('d M Y') : '—' }}</strong></div>
            <div class="meta-cell"><small>Delivery Location</small><strong>{{ $deliveryLocation }}</strong></div>
        </section>

        <div class="two-column">
            <section class="section">
                <h2>Supplier Details</h2>
                <div class="fields">
                    <div class="field"><small>Supplier</small><strong>{{ optional($supplier)->name ?: '—' }}</strong></div>
                    <div class="field"><small>Company</small><strong>{{ optional($supplier)->company_name ?: '—' }}</strong></div>
                    <div class="field"><small>Supplier Code</small><strong>{{ optional($supplier)->supplier_code ?: '—' }}</strong></div>
                    <div class="field"><small>Contact Person</small><strong>{{ optional($supplier)->name ?: '—' }}</strong></div>
                    <div class="field"><small>Phone</small><strong>{{ optional($supplier)->phone ?: '—' }}</strong></div>
                    <div class="field"><small>Email</small><strong>{{ optional($supplier)->email ?: '—' }}</strong></div>
                    <div class="field"><small>GSTIN / PAN</small><strong>{{ optional($supplier)->gst_number ?: '—' }} / {{ optional($supplier)->pan_number ?: '—' }}</strong></div>
                    <div class="field wide"><small>Address</small><strong>{{ $supplierAddress ?: '—' }}</strong></div>
                </div>
            </section>
            <section class="section">
                <h2>Order Information</h2>
                <div class="fields">
                    <div class="field"><small>Purchase Order</small><strong>{{ $purchaseOrder->po_number }}</strong></div>
                    <div class="field"><small>Source Quotation</small><strong>{{ $sourceReferences ?: '—' }}</strong></div>
                    <div class="field"><small>Order Date</small><strong>{{ $purchaseOrder->order_date ? \Carbon\Carbon::parse($purchaseOrder->order_date)->format('d M Y') : '—' }}</strong></div>
                    <div class="field"><small>Expected Delivery</small><strong>{{ $purchaseOrder->expected_date ? \Carbon\Carbon::parse($purchaseOrder->expected_date)->format('d M Y') : '—' }}</strong></div>
                    <div class="field wide"><small>Delivery Location</small><strong>{{ $deliveryLocation }}</strong></div>
                    @if ($purchaseOrder->notes)
                        <div class="field wide"><small>Remarks</small><strong>{{ $purchaseOrder->notes }}</strong></div>
                    @endif
                </div>
            </section>
        </div>

        <section class="section">
            <h2>Supplier Bank Details</h2>
            <div class="bank-fields">
                <div class="field"><small>Bank Name</small><strong>{{ optional($supplier)->bank_name ?: '—' }}</strong></div>
                <div class="field"><small>Account Holder</small><strong>{{ optional($supplier)->account_holder_name ?: '—' }}</strong></div>
                <div class="field"><small>Account Number</small><strong>{{ $maskedAccount }}</strong></div>
                <div class="field"><small>IFSC / Branch</small><strong>{{ optional($supplier)->ifsc_code ?: '—' }} / {{ optional($supplier)->branch_name ?: '—' }}</strong></div>
            </div>
        </section>

        <section class="section">
            <h2>Order Items</h2>
            <div class="items-table-wrap">
                <table class="items-table">
                    <thead><tr><th>Sr. No.</th><th>Product</th><th>SKU / Code</th><th class="number">Quantity</th><th>Unit</th><th class="number">Rate (₹)</th><th class="number">Discount %</th><th class="number">Discount (₹)</th><th class="number">GST %</th><th class="number">Tax (₹)</th><th class="number">Total (₹)</th></tr></thead>
                    <tbody>
                    @forelse ($lines as $line)
                        @php $product = $line['item']->product; @endphp
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td>
                                @if ($product && $product->image && \Illuminate\Support\Facades\Storage::disk('public')->exists($product->image))
                                    <img class="product-image" src="{{ asset('storage/' . $product->image) }}" alt="">
                                @endif
                                <span class="product-name">{{ $product ? $product->name : 'Product unavailable' }}</span>
                            </td>
                            <td>{{ optional($product)->product_code ?: '—' }}</td>
                            <td class="number">{{ number_format($line['quantity'], 2) }}</td>
                            <td>{{ optional(optional($product)->unit)->short_name ?: optional(optional($product)->unit)->name ?: '—' }}</td>
                            <td class="number">{{ number_format((float) $line['item']->purchase_rate, 2) }}</td>
                            <td class="number">{{ number_format((float) $line['item']->discount_percent, 2) }}%</td>
                            <td class="number">{{ number_format($line['discount'], 2) }}</td>
                            <td class="number">{{ number_format((float) $line['item']->tax_percent, 2) }}%</td>
                            <td class="number">{{ number_format($line['tax'], 2) }}</td>
                            <td class="number">{{ number_format($line['total'], 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="11">No order items are associated with this purchase order.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="summary-wrap" aria-label="Financial summary">
            <table class="summary">
                <tbody>
                    <tr><td>Subtotal</td><td>₹{{ number_format($subtotal, 2) }}</td></tr>
                    <tr><td>Discount</td><td>₹{{ number_format($discount, 2) }}</td></tr>
                    <tr><td>Taxable Amount</td><td>₹{{ number_format($taxableAmount, 2) }}</td></tr>
                    <tr><td>CGST</td><td>₹{{ number_format($cgst, 2) }}</td></tr>
                    <tr><td>SGST</td><td>₹{{ number_format($sgst, 2) }}</td></tr>
                    @if ($igst > 0)
                        <tr><td>IGST</td><td>₹{{ number_format($igst, 2) }}</td></tr>
                    @endif
                    <tr><td>Total Tax</td><td>₹{{ number_format($tax, 2) }}</td></tr>
                    <tr class="grand-total"><td>Grand Total</td><td>₹{{ number_format($grandTotal, 2) }}</td></tr>
                </tbody>
            </table>
        </section>

        <section class="section">
            <h2>Terms &amp; Conditions</h2>
            <ol class="terms">
                @foreach ($terms as $term)
                    <li>{{ $term }}</li>
                @endforeach
            </ol>
        </section>
        <footer class="footer">Thank you for your business with Aayojan AI Inventory System!</footer>
    </main>
</body>
</html>
