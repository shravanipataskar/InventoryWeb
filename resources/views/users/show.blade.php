@extends('layouts.app')
@section('title', $user->name)
@section('topbar-title', 'User details')
@section('content')
<div class="page-heading"><div><span class="section-kicker">ADMINISTRATION / USERS</span><h1>{{ $user->name }}</h1><p>Read-only account and role details.</p></div><div class="page-heading-actions"><a class="button button-light" href="{{ route('users.index') }}">Back to users</a>@if(auth()->user()->hasPermission('users','edit'))<a class="button button-primary" href="{{ route('users.edit', $user->id) }}">Edit User</a>@endif</div></div>
<section class="panel workspace-panel"><div class="form-grid">
<div class="field"><label>Name</label><div class="product-identifier-display">{{ $user->name }}</div></div>
<div class="field"><label>Email</label><div class="product-identifier-display">{{ $user->email }}</div></div>
<div class="field"><label>Phone</label><div class="product-identifier-display">{{ $user->phone ?: '—' }}</div></div>
<div class="field"><label>Role</label><div class="product-identifier-display">{{ optional($user->roles->first())->label ?: $user->role }}</div></div>
<div class="field"><label>Status</label><div class="product-identifier-display">{{ $user->is_active ? 'Active' : 'Inactive' }}</div></div>
<div class="field"><label>Created</label><div class="product-identifier-display">{{ optional($user->created_at)->format('d M Y, h:i A') ?: '—' }}</div></div>
<div class="field"><label>Updated</label><div class="product-identifier-display">{{ optional($user->updated_at)->format('d M Y, h:i A') ?: '—' }}</div></div>
</div></section>
@php($rolePermissions = optional($user->roles->first())->permissions ?: collect())
<section class="panel listing-panel"><div class="panel-heading"><h2>Role permissions</h2><p>Permissions are inherited from the assigned role.</p></div><div class="table-wrap"><table class="data-table listing-table"><thead><tr><th>MODULE</th><th>ACTIONS</th></tr></thead><tbody>@foreach($rolePermissions->groupBy('module') as $module => $permissions)<tr><td>{{ ucwords(str_replace('_',' ',$module)) }}</td><td>{{ $permissions->pluck('action')->sort()->implode(', ') }}</td></tr>@endforeach</tbody></table></div></section>
@endsection
