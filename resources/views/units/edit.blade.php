<!DOCTYPE html>
<html>
<head>
    <title>Edit Unit - Inventory System</title>

    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
</head>

<body>

<div class="container mt-5">

    <h2 class="mb-4">Edit Unit</h2>

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

            <form action="{{ route('units.update', $unit->id) }}"
                  method="POST">

                @csrf
                @method('PUT')

                <div class="form-group">

                    <label>Unit Name</label>

                    <input type="text"
                           name="name"
                           class="form-control"
                           value="{{ old('name', $unit->name) }}"
                           required>

                </div>

                <div class="form-group">

                    <label>Short Name</label>

                    <input type="text"
                           name="short_name"
                           class="form-control"
                           value="{{ old('short_name', $unit->short_name) }}"
                           required>

                </div>

                <button type="submit"
                        class="btn btn-success">
                    Update Unit
                </button>

                <a href="{{ route('units.index') }}"
                   class="btn btn-secondary">
                    Back
                </a>

            </form>

        </div>

    </div>

</div>

</body>
</html>