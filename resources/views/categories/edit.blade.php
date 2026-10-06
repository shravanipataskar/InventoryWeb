@extends('layouts.app')

@section('title', 'Edit Category')
@section('topbar-title', 'Edit category')

@section('content')
    <div class="page-heading">
        <div><span class="section-kicker">CATALOGUE / CATEGORIES</span><h1>Edit Category</h1><p>Update the details for {{ $category->name }}.</p></div>
        <a class="button button-light" href="{{ route('categories.index') }}">Back to categories</a>
    </div>

    @if ($errors->any())
        <div class="form-alert" role="alert"><strong>Please check the form:</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <section class="form-card">
        <div class="form-card-heading"><span class="form-section-icon"><svg><use href="#icon-layers"></use></svg></span><div><h2>Category information</h2><p>Update the details for this inventory category.</p></div></div>
        <form action="{{ route('categories.update', $category->id) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="form-grid">
                <div class="field field-wide">
                    <label for="name">Category name <span class="required-mark">*</span></label>
                    <input class="field-control" id="name" type="text" name="name" value="{{ old('name', $category->name) }}" maxlength="255" required>
                    @error('name')<small class="field-error">{{ $message }}</small>@enderror
                </div>
                <div class="field field-wide">
                    <label for="description">Description</label>
                    <textarea class="field-control" id="description" name="description" rows="4" placeholder="Add a short description (optional)">{{ old('description', $category->description) }}</textarea>
                    @error('description')<small class="field-error">{{ $message }}</small>@enderror
                </div>
            </div>
            <div class="form-actions"><a class="button button-light" href="{{ route('categories.index') }}">Cancel</a><button class="button button-primary" type="submit">Update Category</button></div>
        </form>
    </section>
@endsection
