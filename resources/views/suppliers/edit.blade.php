@extends('layouts.app')

@section('title', 'Edit Supplier')
@section('topbar-title', 'Edit supplier')

@section('content')
    <div class="page-heading">
        <div><span class="section-kicker">PARTNERS / SUPPLIERS</span><h1>Edit Supplier</h1><p>Update the contact details for {{ $supplier->name }}.</p></div>
        <a class="button button-light" href="{{ route('suppliers.index') }}">Back to suppliers</a>
    </div>
    @if ($errors->any())
        <div class="form-alert" role="alert"><strong>Please check the form:</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    <section class="form-card">
        <div class="form-card-heading"><span class="form-section-icon"><svg><use href="#icon-users"></use></svg></span><div><h2>Supplier information</h2><p>Contact and company details for this supplier.</p></div></div>
        <form action="{{ route('suppliers.update', $supplier->id) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="form-grid">
                <div class="field"><label for="name">Supplier name <span class="required-mark">*</span></label><input class="field-control" id="name" type="text" name="name" value="{{ old('name', $supplier->name) }}" maxlength="255" required>@error('name')<small class="field-error">{{ $message }}</small>@enderror</div>
                <div class="field"><label for="company_name">Company name</label><input class="field-control" id="company_name" type="text" name="company_name" value="{{ old('company_name', $supplier->company_name) }}" maxlength="255">@error('company_name')<small class="field-error">{{ $message }}</small>@enderror</div>
                <div class="field"><label for="email">Email</label><input class="field-control" id="email" type="email" name="email" value="{{ old('email', $supplier->email) }}" maxlength="255">@error('email')<small class="field-error">{{ $message }}</small>@enderror</div>
                <div class="field"><label for="phone">Phone</label><input class="field-control" id="phone" type="text" name="phone" value="{{ old('phone', $supplier->phone) }}" maxlength="20">@error('phone')<small class="field-error">{{ $message }}</small>@enderror</div>
                <div class="field field-wide"><label for="address">Address</label><textarea class="field-control" id="address" name="address" rows="3">{{ old('address', $supplier->address) }}</textarea>@error('address')<small class="field-error">{{ $message }}</small>@enderror</div>
            </div>
            <div class="form-actions"><a class="button button-light" href="{{ route('suppliers.index') }}">Cancel</a><button class="button button-primary" type="submit">Update Supplier</button></div>
        </form>
    </section>
@endsection
