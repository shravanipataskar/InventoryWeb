@extends('layouts.app')

@section('title', 'Add Hall')
@section('topbar-title', 'Add Hall')

@section('content')
    <div class="page-heading">
        <div><span class="section-kicker">CATALOGUE / HALLS</span><h1>Add Hall</h1><p>Add a storage Hall for your inventory.</p></div>
        <a class="button button-light" href="{{ route('halls.index') }}">Back to halls</a>
    </div>
    @if ($errors->any())
        <div class="form-alert" role="alert"><strong>Please check the form:</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    <section class="form-card">
        <div class="form-card-heading"><span class="form-section-icon"><svg><use href="#icon-layers"></use></svg></span><div><h2>Hall information</h2><p>Use a unique alphanumeric name, such as A1.</p></div></div>
        <form action="{{ route('halls.store') }}" method="POST">
            @csrf
            <div class="form-grid">
                <div class="field">
                    <label for="name">Hall name <span class="required-mark">*</span></label>
                    <input class="field-control" id="name" type="text" name="name" value="{{ old('name') }}" placeholder="e.g. A1" maxlength="50" pattern="[A-Za-z0-9]+" title="Use letters and numbers only." required>
                    <small class="field-hint">Use letters and numbers only.</small>
                    @error('name')<small class="field-error">{{ $message }}</small>@enderror
                </div>
            </div>
            <div class="form-actions"><a class="button button-light" href="{{ route('halls.index') }}">Cancel</a><button class="button button-primary" type="submit">Save Hall</button></div>
        </form>
    </section>
@endsection
