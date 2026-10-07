@extends('layouts.app')

@section('title', 'Add Customer')
@section('topbar-title', 'Add customer')

@section('content')
    <div class="page-heading">
        <div><span class="section-kicker">RELATIONSHIPS / CUSTOMERS</span><h1>Add Customer</h1><p>Add a customer or recipient to the master list.</p></div>
        <a class="button button-light" href="{{ route('customers.index') }}">Back to customers</a>
    </div>
    @if ($errors->any())
        <div class="form-alert" role="alert"><strong>Please check the form:</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    <section class="form-card">
        <form action="{{ route('customers.store') }}" method="POST">
            @csrf
            @include('customers._fields')
            <div class="form-actions"><a class="button button-light" href="{{ route('customers.index') }}">Cancel</a><button class="button button-primary" type="submit">Save Customer</button></div>
        </form>
    </section>
@endsection
