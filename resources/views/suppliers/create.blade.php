@extends('layouts.app')

@section('title', 'Add Supplier')
@section('topbar-title', 'Add supplier')

@section('content')
    <div class="page-heading">
        <div><span class="section-kicker">PARTNERS / SUPPLIERS</span><h1>Add Supplier</h1><p>Add a supplier to your business directory.</p></div>
        <a class="button button-light" href="{{ route('suppliers.index') }}">Back to suppliers</a>
    </div>
    @if ($errors->any())
        <div class="form-alert" role="alert"><strong>Please check the form:</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    <section class="form-card">
        <form action="{{ route('suppliers.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            @include('suppliers._fields')
            <div class="form-actions"><a class="button button-light" href="{{ route('suppliers.index') }}">Cancel</a><button class="button button-primary" type="submit">Save Supplier</button></div>
        </form>
    </section>
@endsection
