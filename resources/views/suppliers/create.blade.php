<!DOCTYPE html>
<html>
<head>
    <title>Add Supplier - Inventory System</title>

    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">

    <style>
        body {
            background: #f5f6fa;
        }

        .container {
            margin-top: 50px;
            max-width: 900px;
        }

        .card {
            border: none;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }

        .page-title {
            font-weight: 600;
        }
    </style>
</head>

<body>

<div class="container">

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>
            <h2 class="page-title">Add Supplier</h2>

            <p class="text-muted mb-0">
                Add a new supplier to the inventory system
            </p>
        </div>

        <a href="{{ route('suppliers.index') }}"
           class="btn btn-secondary">
            Back
        </a>

    </div>

    @if($errors->any())

        <div class="alert alert-danger">

            <strong>Please fix the following errors:</strong>

            <ul class="mb-0 mt-2">

                @foreach($errors->all() as $error)

                    <li>{{ $error }}</li>

                @endforeach

            </ul>

        </div>

    @endif

    <div class="card">

        <div class="card-body p-4">

            <form action="{{ route('suppliers.store') }}"
                  method="POST">

                @csrf

                <div class="form-row">

                    <div class="form-group col-md-6">

                        <label>
                            Supplier Name
                            <span class="text-danger">*</span>
                        </label>

                        <input type="text"
                               name="name"
                               class="form-control"
                               placeholder="Enter supplier name"
                               value="{{ old('name') }}"
                               required>

                    </div>

                    <div class="form-group col-md-6">

                        <label>Company Name</label>

                        <input type="text"
                               name="company_name"
                               class="form-control"
                               placeholder="Enter company name"
                               value="{{ old('company_name') }}">

                    </div>

                </div>


                <div class="form-row">

                    <div class="form-group col-md-6">

                        <label>Email</label>

                        <input type="email"
                               name="email"
                               class="form-control"
                               placeholder="supplier@example.com"
                               value="{{ old('email') }}">

                    </div>

                    <div class="form-group col-md-6">

                        <label>Phone</label>

                        <input type="text"
                               name="phone"
                               class="form-control"
                               placeholder="9876543210"
                               value="{{ old('phone') }}">

                    </div>

                </div>


                <div class="form-group">

                    <label>Address</label>

                    <textarea name="address"
                              class="form-control"
                              rows="4"
                              placeholder="Enter supplier address">{{ old('address') }}</textarea>

                </div>


                <div class="mt-4">

                    <button type="submit"
                            class="btn btn-success">
                        Save Supplier
                    </button>

                    <a href="{{ route('suppliers.index') }}"
                       class="btn btn-secondary ml-2">
                        Cancel
                    </a>

                </div>

            </form>

        </div>

    </div>

</div>

</body>
</html>