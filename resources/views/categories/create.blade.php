<!DOCTYPE html>
<html>
<head>
    <title>Add Category - Inventory System</title>

    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
</head>

<body>

<div class="container mt-5">

    <h2 class="mb-4">Add Category</h2>

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

            <form action="{{ route('categories.store') }}" method="POST">

                @csrf

                <div class="form-group">
                    <label>
                        Category Name
                        <span class="text-danger">*</span>
                    </label>

                    <input type="text"
                           name="name"
                           class="form-control"
                           placeholder="Enter category name"
                           value="{{ old('name') }}"
                           required>
                </div>

                <div class="form-group">
                    <label>Description</label>

                    <textarea name="description"
                              class="form-control"
                              rows="4"
                              placeholder="Enter category description">{{ old('description') }}</textarea>
                </div>

                <button type="submit"
                        class="btn btn-success">
                    Save Category
                </button>

                <a href="{{ route('categories.index') }}"
                   class="btn btn-secondary">
                    Back
                </a>

            </form>

        </div>
    </div>

</div>

</body>
</html>