@if ($products->count())
    <div class="table-wrap company-products-wrap">
        <table class="data-table company-products-table">
            <thead><tr><th>PRODUCT</th><th>CODE</th><th>CATEGORY</th><th>CURRENT STOCK</th><th>STOCK VALUE</th><th>STATUS</th><th></th></tr></thead>
            <tbody>
            @foreach ($products as $product)
                <tr>
                    <td><span class="company-product-avatar">{{ strtoupper(substr($product->name, 0, 1)) }}</span><strong>{{ $product->name }}</strong></td>
                    <td>{{ $product->product_code }}</td>
                    <td>{{ optional($product->category)->name ?: '—' }}</td>
                    <td>{{ number_format($product->current_stock, 2) }} {{ optional($product->unit)->short_name }}</td>
                    <td>₹{{ number_format($product->current_stock * $product->purchase_price, 2) }}</td>
                    <td><span class="company-status {{ $product->is_active ? 'is-active' : 'is-inactive' }}">{{ $product->is_active ? 'Active' : 'Inactive' }}</span></td>
                    <td><a class="company-row-view" href="{{ route('products.edit', $product->id) }}">Edit</a></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@else
    <div class="company-tab-empty">No products are associated with this company yet. Assign it while creating or editing a product.</div>
@endif
