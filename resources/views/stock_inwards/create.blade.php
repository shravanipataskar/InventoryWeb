<!DOCTYPE html>
<html>
<head>
    <title>Add Stock Inward - Inventory System</title>

    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">

    <style>
        body {
            background: #f5f6fa;
        }

        .container {
            margin-top: 40px;
            max-width: 900px;
        }

        .card {
            border: none;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
    </style>
</head>

<body>

<div class="container">

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>
            <h2>Add Stock Inward</h2>

            <p class="text-muted mb-0">
                Record received inventory
            </p>
        </div>

        <a href="{{ route('stock-inwards.index') }}"
           class="btn btn-secondary">
            Back
        </a>

    </div>

    @if($errors->any())

        <div class="alert alert-danger">

            <ul class="mb-0">

                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach

            </ul>

        </div>

    @endif

    <div class="card">

        <div class="card-body p-4">

            <form action="{{ route('stock-inwards.store') }}"
                  method="POST">

                @csrf

                <div class="form-row">

                    <div class="form-group col-md-6">

                        <label>Product *</label>

                        <select name="product_id"
                                class="form-control"
                                required>

                            <option value="">
                                Select Product
                            </option>

                            @foreach($products as $product)

                                <option value="{{ $product->id }}"
                                    {{ old('product_id') == $product->id ? 'selected' : '' }}>

                                    {{ $product->name }}
                                    ({{ $product->product_code }})

                                </option>

                            @endforeach

                        </select>

                    </div>

                    <div class="form-group col-md-6">

                        <label>Supplier *</label>

                        <select name="supplier_id"
                                class="form-control"
                                required>

                            <option value="">
                                Select Supplier
                            </option>

                            @foreach($suppliers as $supplier)

                                <option value="{{ $supplier->id }}"
                                    {{ old('supplier_id') == $supplier->id ? 'selected' : '' }}>

                                    {{ $supplier->name }}

                                </option>

                            @endforeach

                        </select>

                    </div>

                </div>

                <div class="form-row">

                    <div class="form-group col-md-6">

                        <label>Invoice Number</label>

                        <input type="text"
                               name="invoice_number"
                               class="form-control"
                               placeholder="INV-001"
                               value="{{ old('invoice_number') }}">

                    </div>

                    <div class="form-group col-md-6">

                        <label>Inward Date *</label>

                        <input type="date"
                               name="inward_date"
                               class="form-control"
                               value="{{ old('inward_date', date('Y-m-d')) }}"
                               required>

                    </div>

                </div>

                <div class="form-row">

                    <div class="form-group col-md-6">

                        <label>Quantity *</label>

                        <input type="number"
                               name="quantity"
                               id="quantity"
                               class="form-control"
                               step="0.01"
                               min="0.01"
                               placeholder="50"
                               value="{{ old('quantity') }}"
                               required>

                    </div>

                    <div class="form-group col-md-6">

                        <label>Purchase Price *</label>

                        <input type="number"
                               name="purchase_price"
                               id="purchase_price"
                               class="form-control"
                               step="0.01"
                               min="0"
                               placeholder="450"
                               value="{{ old('purchase_price') }}"
                               required>

                    </div>

                </div>

                <div class="form-group">

                    <label>Total Amount</label>

                    <input type="text"
                           id="total_amount"
                           class="form-control"
                           value="0.00"
                           readonly>

                </div>

                <div class="form-group">

                    <label>Remarks</label>

                    <textarea name="remarks"
                              class="form-control"
                              rows="3"
                              placeholder="Optional remarks">{{ old('remarks') }}</textarea>

                </div>

                <button type="submit"
                        class="btn btn-success">
                    Save Stock Inward
                </button>

                <a href="{{ route('stock-inwards.index') }}"
                   class="btn btn-secondary ml-2">
                    Cancel
                </a>

            </form>

        </div>

    </div>

</div>

<script>

    function calculateTotal() {

        let quantity =
            parseFloat(document.getElementById('quantity').value) || 0;

        let price =
            parseFloat(document.getElementById('purchase_price').value) || 0;

        let total = quantity * price;

        document.getElementById('total_amount').value =
            total.toFixed(2);
    }

    document.getElementById('quantity')
        .addEventListener('input', calculateTotal);

    document.getElementById('purchase_price')
        .addEventListener('input', calculateTotal);

</script>

</body>
</html>