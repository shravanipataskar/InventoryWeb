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
        <div class="form-card-heading"><span class="form-section-icon"><svg><use href="#icon-users"></use></svg></span><div><h2>Supplier information</h2><p>Contact and company details for this supplier.</p></div></div>
        <form action="{{ route('suppliers.store') }}" method="POST">
            @csrf
            <div class="form-grid">
                <div class="field"><label for="name">Supplier name <span class="required-mark">*</span></label><input class="field-control" id="name" type="text" name="name" value="{{ old('name') }}" placeholder="Supplier contact name" maxlength="255" required>@error('name')<small class="field-error">{{ $message }}</small>@enderror</div>
                <div class="field"><label for="company_name">Company name</label><input class="field-control" id="company_name" type="text" name="company_name" value="{{ old('company_name') }}" placeholder="Company or business name" maxlength="255">@error('company_name')<small class="field-error">{{ $message }}</small>@enderror</div>
                <div class="field"><label for="email">Email</label><input class="field-control" id="email" type="email" name="email" value="{{ old('email') }}" placeholder="name@company.com" maxlength="255">@error('email')<small class="field-error">{{ $message }}</small>@enderror</div>
                <div class="field"><label for="phone">Phone</label><input class="field-control" id="phone" type="text" name="phone" value="{{ old('phone') }}" placeholder="Phone number" maxlength="20">@error('phone')<small class="field-error">{{ $message }}</small>@enderror</div>
                <div class="field field-wide"><label for="address">Address</label><textarea class="field-control" id="address" name="address" rows="3" placeholder="Supplier address (optional)">{{ old('address') }}</textarea>@error('address')<small class="field-error">{{ $message }}</small>@enderror</div>
            </div>
            <div class="form-actions"><a class="button button-light" href="{{ route('suppliers.index') }}">Cancel</a><button class="button button-primary" type="submit">Save Supplier</button></div>
        </form>
    </section>
@endsection
