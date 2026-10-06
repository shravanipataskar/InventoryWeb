<!DOCTYPE html>
<html>
<head>
    <title>Suppliers - Inventory System</title>

    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">

    <style>
        body {
            background: #f5f6fa;
        }

        .container {
            margin-top: 50px;
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
            <h2>Suppliers</h2>
            <p class="text-muted mb-0">
                Manage inventory suppliers
            </p>
        </div>

        <a href="{{ route('suppliers.create') }}"
           class="btn btn-primary">
            + Add Supplier
        </a>

    </div>

    @if(session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    <div class="card">

        <div class="card-body">

            <table class="table table-bordered table-hover">

                <thead class="thead-light">
                    <tr>
                        <th>#</th>
                        <th>Supplier Name</th>
                        <th>Company</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>

                @forelse($suppliers as $supplier)

                    <tr>

                        <td>{{ $loop->iteration }}</td>

                        <td>{{ $supplier->name }}</td>

                        <td>
                            {{ $supplier->company_name ?: '-' }}
                        </td>

                        <td>
                            {{ $supplier->email ?: '-' }}
                        </td>

                        <td>
                            {{ $supplier->phone ?: '-' }}
                        </td>

                        <td>

                            @if($supplier->is_active)

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

                            <a href="{{ route('suppliers.edit', $supplier->id) }}"
                               class="btn btn-sm btn-warning">
                                Edit
                            </a>

                            <form action="{{ route('suppliers.destroy', $supplier->id) }}"
                                  method="POST"
                                  style="display:inline-block;">

                                @csrf
                                @method('DELETE')

                                <button type="submit"
                                        class="btn btn-sm btn-danger"
                                        onclick="return confirm('Are you sure you want to delete this supplier?')">
                                    Delete
                                </button>

                            </form>

                        </td>

                    </tr>

                @empty

                    <tr>
                        <td colspan="7"
                            class="text-center text-muted py-4">
                            No suppliers found.
                        </td>
                    </tr>

                @endforelse

                </tbody>

            </table>

        </div>

    </div>

</div>

</body>
</html>