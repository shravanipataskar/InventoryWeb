@extends('layouts.app')

@section('title', 'Add Category')
@section('topbar-title', 'Add category')

@section('content')
    <div class="page-heading">
        <div><span class="section-kicker">CATALOGUE / CATEGORIES</span><h1>Add Category</h1><p>Create a category to keep your inventory organized.</p></div>
        <a class="button button-light" href="{{ route('categories.index') }}">Back to categories</a>
    </div>

    @if ($errors->any())
        <div class="form-alert" role="alert"><strong>Please check the form:</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <section class="form-card">
        <div class="form-card-heading"><span class="form-section-icon"><svg><use href="#icon-layers"></use></svg></span><div><h2>Category information</h2><p>Enter the details for this inventory category.</p></div></div>
        <form action="{{ route('categories.store') }}" method="POST">
            @csrf
            <div class="form-grid">
                <div class="field field-wide">
                    <label for="name">Category name <span class="required-mark">*</span></label>
                    <input class="field-control" id="name" type="text" name="name" value="{{ old('name') }}" placeholder="e.g. Electronics" maxlength="255" required>
                    @error('name')<small class="field-error">{{ $message }}</small>@enderror
                </div>
                <div class="field field-wide">
                    <label for="description">Description</label>
                    <textarea class="field-control" id="description" name="description" rows="4" placeholder="Add a short description (optional)">{{ old('description') }}</textarea>
                    @error('description')<small class="field-error">{{ $message }}</small>@enderror
                </div>
            </div>
            <div class="form-actions"><a class="button button-light" href="{{ route('categories.index') }}">Cancel</a><button class="button button-primary" type="submit">Save Category</button></div>
        </form>
    </section>
@endsection
