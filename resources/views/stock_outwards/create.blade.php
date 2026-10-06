<!DOCTYPE html>
<html>
<head>
    <title>Add Stock Outward - Inventory System</title>

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
            <h2>Add Stock Outward</h2>

            <p class="text-muted mb-0">
                Issue or sell inventory
            </p>
        </div>

        <a href="{{ route('stock-outwards.index') }}"
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

            <form action="{{ route('stock-outwards.store') }}"
                  method="POST">

                @csrf

                <div class="form-row">

                    <div class="form-group col-md-6">

                        <label>Product *</label>

                        <select name="product_id"
                                id="product_id"
                                class="form-control"
                                required>

                            <option value="">
                                Select Product
                            </option>

                            @foreach($products as $product)

                                <option value="{{ $product->id }}"
                                        data-stock="{{ $product->current_stock }}"
                                        data-price="{{ $product->selling_price }}"
                                    {{ old('product_id') == $product->id ? 'selected' : '' }}>

                                    {{ $product->name }}
                                    ({{ $product->product_code }})

                                </option>

                            @endforeach

                        </select>

                    </div>

                    <div class="form-group col-md-6">

                        <label>Available Stock</label>

                        <input type="text"
                               id="available_stock"
                               class="form-control"
                               value="0"
                               readonly>

                    </div>

                </div>

                <div class="form-row">

                    <div class="form-group col-md-6">

                        <label>Reference Number</label>

                        <input type="text"
                               name="reference_number"
                               class="form-control"
                               placeholder="OUT-001"
                               value="{{ old('reference_number') }}">

                    </div>

                    <div class="form-group col-md-6">

                        <label>Outward Date *</label>

                        <input type="date"
                               name="outward_date"
                               class="form-control"
                               value="{{ old('outward_date', date('Y-m-d')) }}"
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
                               placeholder="5"
                               value="{{ old('quantity') }}"
                               required>

                        <small id="stock_warning"
                               class="text-danger"
                               style="display:none;">
                            Quantity cannot exceed available stock.
                        </small>

                    </div>

                    <div class="form-group col-md-6">

                        <label>Selling Price *</label>

                        <input type="number"
                               name="selling_price"
                               id="selling_price"
                               class="form-control"
                               step="0.01"
                               min="0"
                               value="{{ old('selling_price') }}"
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

                    <label>Issued To</label>

                    <input type="text"
                           name="issued_to"
                           class="form-control"
                           placeholder="Customer / Department / Employee"
                           value="{{ old('issued_to') }}">

                </div>

                <div class="form-group">

                    <label>Remarks</label>

                    <textarea name="remarks"
                              class="form-control"
                              rows="3"
                              placeholder="Optional remarks">{{ old('remarks') }}</textarea>

                </div>

                <button type="submit"
                        id="saveButton"
                        class="btn btn-success">
                    Save Stock Outward
                </button>

                <a href="{{ route('stock-outwards.index') }}"
                   class="btn btn-secondary ml-2">
                    Cancel
                </a>

            </form>

        </div>

    </div>

</div>

<script>

    const productSelect =
        document.getElementById('product_id');

    const stockField =
        document.getElementById('available_stock');

    const priceField =
        document.getElementById('selling_price');

    const quantityField =
        document.getElementById('quantity');

    const totalField =
        document.getElementById('total_amount');

    const warning =
        document.getElementById('stock_warning');

    const saveButton =
        document.getElementById('saveButton');


    function updateProductDetails() {

        const option =
            productSelect.options[productSelect.selectedIndex];

        if (!option || !option.value) {

            stockField.value = '0';

            return;
        }

        const stock =
            parseFloat(option.dataset.stock) || 0;

        const price =
            parseFloat(option.dataset.price) || 0;

        stockField.value = stock;

        priceField.value = price.toFixed(2);

        calculateTotal();

        validateQuantity();
    }


    function calculateTotal() {

        const quantity =
            parseFloat(quantityField.value) || 0;

        const price =
            parseFloat(priceField.value) || 0;

        totalField.value =
            (quantity * price).toFixed(2);
    }


    function validateQuantity() {

        const quantity =
            parseFloat(quantityField.value) || 0;

        const stock =
            parseFloat(stockField.value) || 0;

        if (quantity > stock) {

            warning.style.display = 'block';

            saveButton.disabled = true;

        } else {

            warning.style.display = 'none';

            saveButton.disabled = false;
        }
    }


    productSelect.addEventListener(
        'change',
        updateProductDetails
    );

    quantityField.addEventListener(
        'input',
        function () {
            calculateTotal();
            validateQuantity();
        }
    );

    priceField.addEventListener(
        'input',
        calculateTotal
    );

    updateProductDetails();

</script>

</body>
</html>