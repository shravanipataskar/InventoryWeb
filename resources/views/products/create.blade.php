<!DOCTYPE html>
<html>
<head>
    <title>Add Product - Inventory System</title>

    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">

    <style>
        body {
            background: #f5f6fa;
        }

        .container {
            margin-top: 40px;
            max-width: 1000px;
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
            <h2>Add Product</h2>
            <p class="text-muted mb-0">
                Add a new product to inventory
            </p>
        </div>

        <a href="{{ route('products.index') }}"
           class="btn btn-secondary">
            Back
        </a>

    </div>

    @if($errors->any())

        <div class="alert alert-danger">

            <strong>Please fix the following:</strong>

            <ul class="mb-0 mt-2">

                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach

            </ul>

        </div>

    @endif

    <div class="card">

        <div class="card-body p-4">

            <form action="{{ route('products.store') }}"
                  method="POST">

                @csrf

                <div class="form-row">

                    <div class="form-group col-md-6">
                        <label>Product Code *</label>

                        <input type="text"
                               name="product_code"
                               class="form-control"
                               placeholder="PROD-001"
                               value="{{ old('product_code') }}"
                               required>
                    </div>

                    <div class="form-group col-md-6">
                        <label>Product Name *</label>

                        <input type="text"
                               name="name"
                               class="form-control"
                               placeholder="Wireless Mouse"
                               value="{{ old('name') }}"
                               required>
                    </div>

                </div>

                <div class="form-row">

                    <div class="form-group col-md-6">

                        <label>Category *</label>

                        <select name="category_id"
                                class="form-control"
                                required>

                            <option value="">
                                Select Category
                            </option>

                            @foreach($categories as $category)

                                <option value="{{ $category->id }}"
                                    {{ old('category_id') == $category->id ? 'selected' : '' }}>

                                    {{ $category->name }}

                                </option>

                            @endforeach

                        </select>

                    </div>

                    <div class="form-group col-md-6">

                        <label>Unit *</label>

                        <select name="unit_id"
                                class="form-control"
                                required>

                            <option value="">
                                Select Unit
                            </option>

                            @foreach($units as $unit)

                                <option value="{{ $unit->id }}"
                                    {{ old('unit_id') == $unit->id ? 'selected' : '' }}>

                                    {{ $unit->name }} ({{ $unit->short_name }})

                                </option>

                            @endforeach

                        </select>

                    </div>

                </div>

                <div class="form-row">

                    <div class="form-group col-md-6">
                        <label>Barcode</label>

                        <input type="text"
                               name="barcode"
                               class="form-control"
                               placeholder="8901234567890"
                               value="{{ old('barcode') }}">
                    </div>

                    <div class="form-group col-md-6">
                        <label>Minimum Stock</label>

                        <input type="number"
                               name="minimum_stock"
                               step="0.01"
                               min="0"
                               class="form-control"
                               value="{{ old('minimum_stock', 0) }}">
                    </div>

                </div>

                <div class="form-row">

                    <div class="form-group col-md-6">
                        <label>Purchase Price *</label>

                        <input type="number"
                               name="purchase_price"
                               step="0.01"
                               min="0"
                               class="form-control"
                               value="{{ old('purchase_price', 0) }}"
                               required>
                    </div>

                    <div class="form-group col-md-6">
                        <label>Selling Price *</label>

                        <input type="number"
                               name="selling_price"
                               step="0.01"
                               min="0"
                               class="form-control"
                               value="{{ old('selling_price', 0) }}"
                               required>
                    </div>

                </div>

                <div class="form-group">

                    <label>Description</label>

                    <textarea name="description"
                              class="form-control"
                              rows="4"
                              placeholder="Enter product description">{{ old('description') }}</textarea>

                </div>

                <button type="submit"
                        class="btn btn-success">
                    Save Product
                </button>

                <a href="{{ route('products.index') }}"
                   class="btn btn-secondary ml-2">
                    Cancel
                </a>

            </form>

        </div>

    </div>

</div>

</body>
</html>