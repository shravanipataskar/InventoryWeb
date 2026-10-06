<!DOCTYPE html>
<html>
<head>
    <title>Edit Supplier - Inventory System</title>

    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
</head>

<body>

<div class="container mt-5">

    <h2 class="mb-4">Edit Supplier</h2>

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

        <div class="card-body">

            <form action="{{ route('suppliers.update', $supplier->id) }}"
                  method="POST">

                @csrf
                @method('PUT')

                <div class="form-group">
                    <label>Supplier Name</label>

                    <input type="text"
                           name="name"
                           class="form-control"
                           value="{{ old('name', $supplier->name) }}"
                           required>
                </div>

                <div class="form-group">
                    <label>Company Name</label>

                    <input type="text"
                           name="company_name"
                           class="form-control"
                           value="{{ old('company_name', $supplier->company_name) }}">
                </div>

                <div class="form-row">

                    <div class="form-group col-md-6">
                        <label>Email</label>

                        <input type="email"
                               name="email"
                               class="form-control"
                               value="{{ old('email', $supplier->email) }}">
                    </div>

                    <div class="form-group col-md-6">
                        <label>Phone</label>

                        <input type="text"
                               name="phone"
                               class="form-control"
                               value="{{ old('phone', $supplier->phone) }}">
                    </div>

                </div>

                <div class="form-group">
                    <label>Address</label>

                    <textarea name="address"
                              class="form-control"
                              rows="4">{{ old('address', $supplier->address) }}</textarea>
                </div>

                <button type="submit"
                        class="btn btn-success">
                    Update Supplier
                </button>

                <a href="{{ route('suppliers.index') }}"
                   class="btn btn-secondary">
                    Back
                </a>

            </form>

        </div>

    </div>

</div>

</body>
</html>