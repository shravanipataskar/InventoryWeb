<!DOCTYPE html>
<html>
<head>
    <title>Stock Inward - Inventory System</title>

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
            <h2>Stock Inward</h2>

            <p class="text-muted mb-0">
                Record goods received from suppliers
            </p>
        </div>

        <a href="{{ route('stock-inwards.create') }}"
           class="btn btn-primary">
            + Add Stock Inward
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
                        <th>Supplier</th>
                        <th>Invoice</th>
                        <th>Quantity</th>
                        <th>Purchase Price</th>
                        <th>Total</th>
                    </tr>

                    </thead>

                    <tbody>

                    @forelse($stockInwards as $stock)

                        <tr>

                            <td>{{ $loop->iteration }}</td>

                            <td>
                                {{ $stock->inward_date }}
                            </td>

                            <td>
                                {{ $stock->product->name ?? '-' }}
                            </td>

                            <td>
                                {{ $stock->supplier->name ?? '-' }}
                            </td>

                            <td>
                                {{ $stock->invoice_number ?: '-' }}
                            </td>

                            <td>
                                {{ $stock->quantity }}
                            </td>

                            <td>
                                ₹{{ number_format($stock->purchase_price, 2) }}
                            </td>

                            <td>
                                ₹{{ number_format($stock->total_amount, 2) }}
                            </td>

                        </tr>

                    @empty

                        <tr>

                            <td colspan="8"
                                class="text-center text-muted py-4">

                                No stock inward records found.

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