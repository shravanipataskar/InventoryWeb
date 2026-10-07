@extends('layouts.app')

@section('title', 'Add Rack')
@section('topbar-title', 'Add Rack')

@section('content')
    <div class="page-heading">
        <div><span class="section-kicker">HALL / {{ $hall->name }}</span><h1>Add Rack</h1><p>Add a Rack under Hall {{ $hall->name }}.</p></div>
        <a class="button button-light" href="{{ route('halls.show', $hall->id) }}">Back to Hall</a>
    </div>
    @if ($errors->any())
        <div class="form-alert" role="alert"><strong>Please check the form:</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    <section class="form-card">
        <div class="form-card-heading"><span class="form-section-icon"><svg><use href="#icon-layers"></use></svg></span><div><h2>Rack information</h2><p>Rack names must be unique within Hall {{ $hall->name }}.</p></div></div>
        <form action="{{ route('racks.store', $hall->id) }}" method="POST">
            @csrf
            <div class="form-grid">
                <div class="field">
                    <label>Hall</label>
                    <input class="field-control" type="text" value="{{ $hall->name }}" disabled>
                </div>
                <div class="field">
                    <label for="name">Rack name <span class="required-mark">*</span></label>
                    <input class="field-control" id="name" type="text" name="name" value="{{ old('name') }}" placeholder="e.g. AR1" maxlength="50" pattern="[A-Za-z0-9]+" title="Use letters and numbers only." required>
                    <small class="field-hint">Use letters and numbers only.</small>
                    @error('name')<small class="field-error">{{ $message }}</small>@enderror
                </div>
            </div>
            <div class="form-actions"><a class="button button-light" href="{{ route('halls.show', $hall->id) }}">Cancel</a><button class="button button-primary" type="submit">Save Rack</button></div>
        </form>
    </section>
@endsection
