@php
    $formatAmount = function ($amount) {
        return $amount === null ? '—' : '₹' . number_format((float) $amount, 2);
    };
    $status = !$stockInward->is_active
        ? 'Inactive'
        : ($stockInward->status ? ucfirst(str_replace('_', ' ', $stockInward->status)) : 'Active');
    $canViewPurchaseOrder = $purchaseOrder && auth()->user()->hasPermission('purchase', 'view');
    $canViewGoodsReceipt = $goodsReceipt && auth()->user()->hasPermission('goods_receipts', 'view');
    $canViewSupplierBill = $supplierBill && auth()->user()->hasPermission('bills', 'view');
    $canEditStockInward = !$stockInward->isGoodsReceiptGenerated() && !$stockInward->is_active && auth()->user()->isAdmin();
@endphp

<article class="stock-inward-modal-card" role="document">
    <header class="stock-inward-modal-header">
        <div>
            <span class="stock-inward-modal-kicker">STOCK MOVEMENT / INWARD</span>
            <h2 id="stock-inward-modal-title">View Stock Inward Details</h2>
            <p>Complete information about the stock inward entry and received items.</p>
        </div>
        <button class="stock-inward-modal-close" type="button" data-stock-inward-close aria-label="Close details">×</button>
    </header>

    <div class="stock-inward-modal-content">
        <section class="stock-inward-detail-section">
            <div class="stock-inward-detail-heading"><span>01</span><div><h3>Inward Information</h3><p>Receipt, supplier and location details</p></div></div>
            <div class="stock-inward-info-grid">
                <div><small>Inward Number</small><strong>{{ $stockInward->inward_number ?: '—' }}</strong></div>
                <div><small>Inward Date</small><strong>{{ $stockInward->inward_date ? \Carbon\Carbon::parse($stockInward->inward_date)->format('d M Y') : '—' }}</strong></div>
                <div><small>GRN Number</small><strong>{{ optional($goodsReceipt)->grn_number ?: '—' }}</strong></div>
                <div><small>Purchase Order</small><strong>{{ optional($purchaseOrder)->po_number ?: '—' }}</strong></div>
                <div><small>Supplier Name</small><strong>{{ optional($stockInward->supplier)->name ?: '—' }}</strong></div>
                <div><small>Supplier Contact</small><strong>{{ optional($stockInward->supplier)->phone ?: '—' }}</strong></div>
                <div><small>Warehouse / Location</small><strong>{{ optional($stockInward->store)->name ?: '—' }}</strong></div>
                <div><small>Supplier Invoice Number</small><strong>{{ optional($goodsReceipt)->invoice_number ?: ($stockInward->invoice_number ?: '—') }}</strong></div>
                <div><small>Supplier Invoice Date</small><strong>{{ $supplierBill && $supplierBill->invoice_date ? \Carbon\Carbon::parse($supplierBill->invoice_date)->format('d M Y') : '—' }}</strong></div>
                <div><small>Current Status</small><strong><span class="stock-inward-modal-status {{ strtolower(str_replace(' ', '-', $status)) }}">{{ $status }}</span></strong></div>
                <div class="stock-inward-info-wide"><small>Remarks</small><strong>{{ optional($goodsReceipt)->remarks ?: ($stockInward->remarks ?: '—') }}</strong></div>
            </div>
        </section>

        <section class="stock-inward-detail-section">
            <div class="stock-inward-detail-heading"><span>02</span><div><h3>Items Received</h3><p>Quantities and amounts recorded for this inward</p></div></div>
            <div class="stock-inward-modal-table-wrap">
                <table class="stock-inward-modal-table">
                    <thead><tr>
                        <th>Sr. No.</th><th>Product Image</th><th>Product Name</th><th>Product Code / SKU</th>
                        <th>Ordered Qty</th><th>Previously Received</th><th>Current Received</th><th>Accepted Qty</th>
                        <th>Rejected Qty</th><th>Unit</th><th>Purchase Price</th><th>Subtotal</th><th>Tax Amount</th><th>Total Amount</th>
                    </tr></thead>
                    <tbody>
                    @foreach ($items as $item)
                        @php $product = $item['product']; @endphp
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td>
                                @if ($product && $product->image && \Illuminate\Support\Facades\Storage::disk('public')->exists($product->image))
                                    <img class="stock-inward-modal-product-image" src="{{ asset('storage/' . $product->image) }}" alt="{{ $product->name }}">
                                @else
                                    <span class="stock-inward-modal-product-fallback" aria-label="No product image">▣</span>
                                @endif
                            </td>
                            <td><strong>{{ $product ? $product->name : '—' }}</strong></td>
                            <td>{{ $product ? ($product->product_code ?: '—') : '—' }}</td>
                            <td>{{ $item['ordered_quantity'] === null ? '—' : number_format((float) $item['ordered_quantity'], 2) }}</td>
                            <td>{{ $item['previously_received_quantity'] === null ? '—' : number_format((float) $item['previously_received_quantity'], 2) }}</td>
                            <td>{{ $item['received_quantity'] === null ? '—' : number_format((float) $item['received_quantity'], 2) }}</td>
                            <td>{{ $item['accepted_quantity'] === null ? '—' : number_format((float) $item['accepted_quantity'], 2) }}</td>
                            <td>{{ $item['rejected_quantity'] === null ? '—' : number_format((float) $item['rejected_quantity'], 2) }}</td>
                            <td>{{ $item['unit'] ? ($item['unit']->short_name ?: $item['unit']->name) : '—' }}</td>
                            <td>{{ $formatAmount($item['purchase_rate']) }}</td>
                            <td>{{ $formatAmount($item['subtotal']) }}</td>
                            <td>{{ $formatAmount($item['tax_amount']) }}</td>
                            <td>{{ $formatAmount($item['grand_total']) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <section class="stock-inward-detail-section">
            <div class="stock-inward-detail-heading"><span>03</span><div><h3>Quantity Summary</h3><p>Totals are grouped by unit to avoid mixing unlike quantities</p></div></div>
            <div class="stock-inward-quantity-groups">
                @foreach ($quantitySummary as $unit => $quantity)
                    <div class="stock-inward-unit-group">
                        <strong class="stock-inward-unit-label">{{ $unit }}</strong>
                        <div class="stock-inward-quantity-cards">
                            <div><small>Total Ordered Qty</small><strong>{{ $quantity['ordered'] === null ? '—' : number_format((float) $quantity['ordered'], 2) }}</strong></div>
                            <div><small>Total Received Qty</small><strong>{{ number_format((float) $quantity['received'], 2) }}</strong></div>
                            <div><small>Total Accepted Qty</small><strong>{{ $quantity['accepted'] === null ? '—' : number_format((float) $quantity['accepted'], 2) }}</strong></div>
                            <div><small>Total Rejected Qty</small><strong>{{ number_format((float) $quantity['rejected'], 2) }}</strong></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="stock-inward-detail-section">
            <div class="stock-inward-detail-heading"><span>04</span><div><h3>Financial Summary</h3><p>Stored inward tax is used where available; missing legacy tax is derived from linked purchase order rates</p></div></div>
            <div class="stock-inward-financial-grid">
                <div><small>Subtotal Before Tax</small><strong>{{ $formatAmount($financialSummary['subtotal']) }}</strong></div>
                <div><small>Total Discount</small><strong>{{ $formatAmount($financialSummary['discount']) }}</strong></div>
                <div><small>CGST Amount (₹)</small><strong>{{ $formatAmount($financialSummary['cgst']) }}</strong></div>
                <div><small>SGST Amount (₹)</small><strong>{{ $formatAmount($financialSummary['sgst']) }}</strong></div>
                <div><small>IGST Amount (₹)</small><strong>{{ $formatAmount($financialSummary['igst']) }}</strong></div>
                <div><small>Total Tax</small><strong>{{ $formatAmount($financialSummary['tax']) }}</strong></div>
                <div class="stock-inward-grand-total-card"><small>Grand Total</small><strong>{{ $formatAmount($financialSummary['grand_total']) }}</strong></div>
            </div>
        </section>

        <section class="stock-inward-detail-section">
            <div class="stock-inward-detail-heading"><span>05</span><div><h3>Related Documents</h3><p>Open related records when your permissions allow</p></div></div>
            <div class="stock-inward-document-grid">
                <article><span class="stock-inward-document-icon">PO</span><div><small>Purchase Order</small><strong>{{ optional($purchaseOrder)->po_number ?: '—' }}</strong><small>{{ $purchaseOrder && $purchaseOrder->po_date ? \Carbon\Carbon::parse($purchaseOrder->po_date)->format('d M Y') : '—' }}</small></div>
                    @if ($canViewPurchaseOrder)<a href="{{ route('purchase-orders.show', $purchaseOrder->id) }}" target="_blank" rel="noopener">View</a>@endif
                </article>
                <article><span class="stock-inward-document-icon">GRN</span><div><small>Goods Received Note</small><strong>{{ optional($goodsReceipt)->grn_number ?: '—' }}</strong><small>{{ $goodsReceipt && $goodsReceipt->received_date ? \Carbon\Carbon::parse($goodsReceipt->received_date)->format('d M Y') : '—' }}</small></div>
                    @if ($canViewGoodsReceipt)<a href="{{ route('goods-receipts.show', $goodsReceipt->id) }}" target="_blank" rel="noopener">View</a>@endif
                </article>
                <article><span class="stock-inward-document-icon">INV</span><div><small>Supplier Invoice</small><strong>{{ $supplierBill ? $supplierBill->invoice_number : (optional($goodsReceipt)->invoice_number ?: ($stockInward->invoice_number ?: '—')) }}</strong><small>{{ $supplierBill && $supplierBill->invoice_date ? \Carbon\Carbon::parse($supplierBill->invoice_date)->format('d M Y') : '—' }}</small></div>
                    @if ($canViewSupplierBill)<a href="{{ route('supplier-bills.show', $supplierBill->id) }}" target="_blank" rel="noopener">View</a>@endif
                </article>
            </div>
        </section>

        <section class="stock-inward-detail-section">
            <div class="stock-inward-detail-heading"><span>06</span><div><h3>Stock Movement Timeline</h3><p>Events recorded in the stock ledger</p></div></div>
            @if ($stockMovements->isNotEmpty())
                <ol class="stock-inward-timeline">
                    @foreach ($stockMovements as $movement)
                        <li><span class="stock-inward-timeline-dot"></span><div><strong>Stock inward posted</strong><small>{{ \Carbon\Carbon::parse($movement->transaction_date)->format('d M Y') }}{{ $movement->remarks ? ' · ' . $movement->remarks : '' }}</small><p>{{ number_format((float) $movement->quantity_in, 2) }} added to {{ optional($stockInward->store)->name ?: 'the inventory location' }} at {{ $formatAmount($movement->unit_price) }} per unit.</p></div></li>
                    @endforeach
                </ol>
            @else
                <p class="stock-inward-no-timeline">No stock movement entries are recorded for this inward.</p>
            @endif
        </section>
    </div>
    <footer class="stock-inward-modal-footer">
        @if ($canEditStockInward)
            <a class="button button-light" href="{{ route('stock-inwards.edit', $stockInward->id) }}">Edit Inward</a>
        @endif
        <button class="button button-light" type="button" data-stock-inward-print>Print</button>
        <button class="button button-primary" type="button" data-stock-inward-close>Close</button>
    </footer>
</article>
