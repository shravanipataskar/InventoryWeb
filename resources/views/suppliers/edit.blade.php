@extends('layouts.app')

@section('title', 'Edit Supplier')
@section('topbar-title', 'Edit supplier')

@section('content')
    <div class="page-heading">
        <div><span class="section-kicker">PARTNERS / SUPPLIERS</span><h1>Edit Supplier</h1><p>Update the business details for {{ $supplier->name }}.</p></div>
        <a class="button button-light" href="{{ route('suppliers.index') }}">Back to suppliers</a>
    </div>
    @if ($errors->any())
        <div class="form-alert" role="alert"><strong>Please check the form:</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    <section class="form-card">
        <form action="{{ route('suppliers.update', $supplier->id) }}" method="POST" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            @include('suppliers._fields', ['supplier' => $supplier])
            <div class="form-actions"><a class="button button-light" href="{{ route('suppliers.index') }}">Cancel</a><button class="button button-primary" type="submit">Update Supplier</button></div>
        </form>
    </section>
@endsection
