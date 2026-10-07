@extends('layouts.app')

@section('title', 'Edit Company')
@section('topbar-title', 'Edit company')

@section('content')
    <div class="page-heading">
        <div><span class="section-kicker">CATALOGUE / COMPANIES</span><h1>Edit Company</h1><p>Update {{ $company->name }} company details.</p></div>
        <a class="button button-light" href="{{ route('companies.show', $company->id) }}">Back to company</a>
    </div>
    @include('companies._form', ['company' => $company])
@endsection
