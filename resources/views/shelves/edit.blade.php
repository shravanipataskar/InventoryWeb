@extends('layouts.app')

@section('title', 'Edit Shell')
@section('topbar-title', 'Edit Shell')

@section('content')
    <div class="page-heading">
        <div><span class="section-kicker">HALL {{ $shelf->rack->hall->name }} / RACK {{ $shelf->rack->name }}</span><h1>Edit Shell</h1><p>Update Shell {{ $shelf->name }}.</p></div>
        <a class="button button-light" href="{{ route('racks.show', $shelf->rack_id) }}">Back to Rack</a>
    </div>
    @if ($errors->any())
        <div class="form-alert" role="alert"><strong>Please check the form:</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    <section class="form-card">
        <div class="form-card-heading"><span class="form-section-icon"><svg><use href="#icon-layers"></use></svg></span><div><h2>Shell information</h2><p>Shell names must be unique within Rack {{ $shelf->rack->name }}.</p></div></div>
        <form action="{{ route('shelves.update', $shelf->id) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="form-grid">
                <div class="field">
                    <label>Hall</label>
                    <input class="field-control" type="text" value="{{ $shelf->rack->hall->name }}" disabled>
                </div>
                <div class="field">
                    <label>Rack</label>
                    <input class="field-control" type="text" value="{{ $shelf->rack->name }}" disabled>
                </div>
                <div class="field">
                    <label for="name">Shell name <span class="required-mark">*</span></label>
                    <input class="field-control" id="name" type="text" name="name" value="{{ old('name', $shelf->name) }}" maxlength="50" pattern="[A-Za-z0-9]+" title="Use letters and numbers only." required>
                    <small class="field-hint">Use letters and numbers only.</small>
                    @error('name')<small class="field-error">{{ $message }}</small>@enderror
                </div>
            </div>
            <div class="form-actions"><a class="button button-light" href="{{ route('racks.show', $shelf->rack_id) }}">Cancel</a><button class="button button-primary" type="submit">Update Shell</button></div>
        </form>
    </section>
@endsection
