@extends('layouts.app')

@section('title', 'Add Unit')
@section('topbar-title', 'Add unit')

@section('content')
    <div class="page-heading">
        <div><span class="section-kicker">CATALOGUE / UNITS</span><h1>Add Unit</h1><p>Add a measurement unit for your products.</p></div>
        <a class="button button-light" href="{{ route('units.index') }}">Back to units</a>
    </div>
    @if ($errors->any())
        <div class="form-alert" role="alert"><strong>Please check the form:</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    <section class="form-card">
        <div class="form-card-heading"><span class="form-section-icon"><svg><use href="#icon-ruler"></use></svg></span><div><h2>Unit information</h2><p>Use a clear name and short code for quick identification.</p></div></div>
        <form action="{{ route('units.store') }}" method="POST">
            @csrf
            <div class="form-grid">
                <div class="field">
                    <label for="name">Unit name <span class="required-mark">*</span></label>
                    <input class="field-control" id="name" type="text" name="name" value="{{ old('name') }}" placeholder="e.g. Kilogram" maxlength="255" required>
                    @error('name')<small class="field-error">{{ $message }}</small>@enderror
                </div>
                <div class="field">
                    <label for="short_name">Short name <span class="required-mark">*</span></label>
                    <input class="field-control" id="short_name" type="text" name="short_name" value="{{ old('short_name') }}" placeholder="e.g. KG" maxlength="20" required>
                    @error('short_name')<small class="field-error">{{ $message }}</small>@enderror
                </div>
            </div>
            <div class="form-actions"><a class="button button-light" href="{{ route('units.index') }}">Cancel</a><button class="button button-primary" type="submit">Save Unit</button></div>
        </form>
    </section>
@endsection
