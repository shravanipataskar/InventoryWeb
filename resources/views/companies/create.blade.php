@extends('layouts.app')

@section('title', 'Add Company')
@section('topbar-title', 'Add company')

@section('content')
    <div class="page-heading">
        <div><span class="section-kicker">CATALOGUE / COMPANIES</span><h1>Add Company</h1><p>Create a company or brand to associate with products.</p></div>
        <a class="button button-light" href="{{ route('companies.index') }}">Back to companies</a>
    </div>
    @include('companies._form', ['company' => null])
@endsection
