<!DOCTYPE html>
<html>
<head>
    <title>Stock Outward - Inventory System</title>

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
            <h2>Stock Outward</h2>

            <p class="text-muted mb-0">
                Record inventory issued or sold
            </p>
        </div>

        <a href="{{ route('stock-outwards.create') }}"
           class="btn btn-primary">
            + Add Stock Outward
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
                        <th>Date</th>
                        <th>Product</th>
                        <th>Reference</th>
                        <th>Issued To</th>
                        <th>Quantity</th>
                        <th>Selling Price</th>
                        <th>Total</th>
                    </tr>

                    </thead>

                    <tbody>

                    @forelse($stockOutwards as $stock)

                        <tr>

                            <td>{{ $loop->iteration }}</td>

                            <td>
                                {{ $stock->outward_date }}
                            </td>

                            <td>
                                {{ $stock->product->name ?? '-' }}
                            </td>

                            <td>
                                {{ $stock->reference_number ?: '-' }}
                            </td>

                            <td>
                                {{ $stock->issued_to ?: '-' }}
                            </td>

                            <td>
                                {{ $stock->quantity }}
                            </td>

                            <td>
                                ₹{{ number_format($stock->selling_price, 2) }}
                            </td>

                            <td>
                                ₹{{ number_format($stock->total_amount, 2) }}
                            </td>

                        </tr>

                    @empty

                        <tr>

                            <td colspan="8"
                                class="text-center text-muted py-4">

                                No stock outward records found.

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