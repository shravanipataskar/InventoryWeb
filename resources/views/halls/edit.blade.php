@extends('layouts.app')

@section('title', 'Edit Hall')
@section('topbar-title', 'Edit Hall')

@section('content')
    <div class="page-heading">
        <div><span class="section-kicker">CATALOGUE / HALLS</span><h1>Edit Hall</h1><p>Update the details for Hall {{ $hall->name }}.</p></div>
        <a class="button button-light" href="{{ route('halls.index') }}">Back to halls</a>
    </div>
    @if ($errors->any())
        <div class="form-alert" role="alert"><strong>Please check the form:</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    <section class="form-card">
        <div class="form-card-heading"><span class="form-section-icon"><svg><use href="#icon-layers"></use></svg></span><div><h2>Hall information</h2><p>Hall names must be unique and alphanumeric.</p></div></div>
        <form action="{{ route('halls.update', $hall->id) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="form-grid">
                <div class="field">
                    <label for="name">Hall name <span class="required-mark">*</span></label>
                    <input class="field-control" id="name" type="text" name="name" value="{{ old('name', $hall->name) }}" maxlength="50" pattern="[A-Za-z0-9]+" title="Use letters and numbers only." required>
                    <small class="field-hint">Use letters and numbers only.</small>
                    @error('name')<small class="field-error">{{ $message }}</small>@enderror
                </div>
            </div>
            <div class="form-actions"><a class="button button-light" href="{{ route('halls.index') }}">Cancel</a><button class="button button-primary" type="submit">Update Hall</button></div>
        </form>
    </section>
@endsection
