<!DOCTYPE html>
<html>
<head>
    <title>Products - Inventory System</title>

    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">

    <style>
        body {
            background: #f5f6fa;
        }

        .container {
            margin-top: 40px;
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
            <h2>Products</h2>
            <p class="text-muted mb-0">
                Manage inventory products
            </p>
        </div>

        <a href="{{ route('products.create') }}"
           class="btn btn-primary">
            + Add Product
        </a>

    </div>

    @if(session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    <div class="card">
        <div class="card-body">

            <div class="table-responsive">

                <table class="table table-bordered table-hover">

                    <thead class="thead-light">
                    <tr>
                        <th>#</th>
                        <th>Code</th>
                        <th>Product</th>
                        <th>Category</th>
                        <th>Unit</th>
                        <th>Purchase Price</th>
                        <th>Selling Price</th>
                        <th>Min Stock</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                    </thead>

                    <tbody>

                    @forelse($products as $product)

                        <tr>

                            <td>{{ $loop->iteration }}</td>

                            <td>{{ $product->product_code }}</td>

                            <td>{{ $product->name }}</td>

                            <td>
                                {{ $product->category->name ?? '-' }}
                            </td>

                            <td>
                                {{ $product->unit->short_name ?? '-' }}
                            </td>

                            <td>
                                ₹{{ number_format($product->purchase_price, 2) }}
                            </td>

                            <td>
                                ₹{{ number_format($product->selling_price, 2) }}
                            </td>

                            <td>{{ $product->minimum_stock }}</td>

                            <td>
                                @if($product->is_active)
                                    <span class="badge badge-success">
                                        Active
                                    </span>
                                @else
                                    <span class="badge badge-danger">
                                        Inactive
                                    </span>
                                @endif
                            </td>

                            <td>

                                <a href="{{ route('products.edit', $product->id) }}"
                                   class="btn btn-sm btn-warning">
                                    Edit
                                </a>

                                <form action="{{ route('products.destroy', $product->id) }}"
                                      method="POST"
                                      style="display:inline-block;">

                                    @csrf
                                    @method('DELETE')

                                    <button type="submit"
                                            class="btn btn-sm btn-danger"
                                            onclick="return confirm('Are you sure you want to delete this product?')">
                                        Delete
                                    </button>

                                </form>

                            </td>

                        </tr>

                    @empty

                        <tr>
                            <td colspan="10"
                                class="text-center text-muted py-4">
                                No products found.
                            </td>
                        </tr>

                    @endforelse

                    </tbody>

                </table>

            </div>

        </div>
    </div>

</div>

</body>
</html>