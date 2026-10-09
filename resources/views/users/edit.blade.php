@extends('layouts.app')
@section('title', 'Edit User')
@section('topbar-title', 'Edit user')
@section('content')
<div class="page-heading"><div><span class="section-kicker">ADMINISTRATION / USERS</span><h1>Edit User</h1><p>Update account details, status, or role. Password is optional.</p></div><a class="button button-light" href="{{ route('users.index') }}">Back to users</a></div>
<section class="panel workspace-panel"><form method="POST" action="{{ route('users.update', $user->id) }}" autocomplete="off">@csrf @method('PUT')
<div class="form-grid">
<div class="field"><label>Name *</label><input class="field-control" name="name" value="{{ old('name', $user->name) }}" required>@error('name')<small class="field-error">{{ $message }}</small>@enderror</div>
<div class="field"><label>Email *</label><input class="field-control" type="email" name="email" value="{{ old('email', $user->email) }}" required>@error('email')<small class="field-error">{{ $message }}</small>@enderror</div>
<div class="field"><label>Phone</label><input class="field-control" name="phone" value="{{ old('phone', $user->phone) }}"></div>
<div class="field"><label>Role *</label><select class="field-control" name="role_id" required>@foreach($roles as $role)<option value="{{ $role->id }}" {{ optional($user->roles->first())->id === $role->id ? 'selected' : '' }}>{{ $role->label }}</option>@endforeach</select></div>
<div class="field"><label>Status *</label><select class="field-control" name="is_active" required><option value="1" {{ $user->is_active ? 'selected' : '' }}>Active</option><option value="0" {{ !$user->is_active ? 'selected' : '' }}>Inactive</option></select></div>
<div class="field"><label>New Password</label><input class="field-control" type="password" name="password" autocomplete="new-password"><small>Leave blank to keep the existing password.</small>@error('password')<small class="field-error">{{ $message }}</small>@enderror</div>
<div class="field"><label>Confirm New Password</label><input class="field-control" type="password" name="password_confirmation" autocomplete="new-password"></div>
</div><div class="form-actions"><button class="button button-primary" type="submit">Save User</button></div>
</form></section>
@endsection
