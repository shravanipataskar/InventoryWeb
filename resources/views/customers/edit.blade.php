@extends('layouts.app')

@section('title', 'Edit Customer')
@section('topbar-title', 'Edit customer')

@section('content')
    <div class="page-heading">
        <div><span class="section-kicker">RELATIONSHIPS / CUSTOMERS</span><h1>Edit Customer</h1><p>Update contact details for {{ $customer->name }}.</p></div>
        <a class="button button-light" href="{{ route('customers.show', $customer->id) }}">Back to customer</a>
    </div>
    @if ($errors->any())
        <div class="form-alert" role="alert"><strong>Please check the form:</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    <section class="form-card">
        <form action="{{ route('customers.update', $customer->id) }}" method="POST">
            @csrf
            @method('PUT')
            @include('customers._fields')
            <div class="form-actions"><a class="button button-light" href="{{ route('customers.show', $customer->id) }}">Cancel</a><button class="button button-primary" type="submit">Update Customer</button></div>
        </form>
    </section>
@endsection
