@extends('layouts.app')

@section('title', 'Roles & Permissions')
@section('topbar-title', 'Roles & permissions')

@section('content')
    <div class="page-heading">
        <div><span class="section-kicker">SETTINGS / ACCESS CONTROL</span><h1>Roles & Permissions</h1><p>Manage module actions without hardcoding access rules.</p></div>
    </div>
    @include('components.flash')
    <section class="panel listing-panel">
        <div class="panel-heading">
            <h2>Select role</h2>
            <p>Permissions configured for a role apply to every user assigned to that role.</p>
        </div>
        <form method="GET" action="{{ route('roles.index') }}" class="form-actions">
            <label class="field">
                <span>Role</span>
                <select class="field-control" name="role_id" onchange="this.form.submit()">
                    @foreach ($roles as $role)
                        <option value="{{ $role->id }}" {{ optional($selectedRole)->id === $role->id ? 'selected' : '' }}>{{ $role->label }}</option>
                    @endforeach
                </select>
            </label>
            <noscript><button class="button button-light" type="submit">Select role</button></noscript>
        </form>
    </section>

    @if ($selectedRole)
        <section class="panel listing-panel">
            <div class="panel-heading"><h2>{{ $selectedRole->label }}</h2><p>{{ $selectedRole->description }}</p></div>
            <form method="POST" action="{{ route('roles.update', $selectedRole->id) }}">
                @csrf
                @method('PUT')
                <div class="table-wrap">
                    <table class="data-table listing-table">
                        <thead><tr><th>MODULE</th><th>VIEW</th><th>CREATE</th><th>EDIT</th><th>DELETE</th><th>APPROVE</th><th>REJECT</th></tr></thead>
                        <tbody>
                        @foreach ($permissions as $module => $modulePermissions)
                            <tr><td><strong>{{ ucwords(str_replace('_', ' ', $module)) }}</strong></td>
                                @foreach (['view', 'create', 'edit', 'delete', 'approve', 'reject'] as $action)
                                    @php $permission = $modulePermissions->firstWhere('action', $action); @endphp
                                    <td>
                                        @if ($permission)
                                            <input type="checkbox" name="permissions[]" value="{{ $permission->id }}" {{ $selectedRole->permissions->contains('id', $permission->id) ? 'checked' : '' }}>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="form-actions"><button class="button button-primary" type="submit">Save {{ $selectedRole->label }} permissions</button></div>
            </form>
        </section>
    @endif

    <section class="panel listing-panel">
        <div class="panel-heading"><h2>{{ optional($selectedRole)->label }} users</h2><p>These users share the selected role and its permissions.</p></div>
        <div class="table-wrap"><table class="data-table listing-table">
            <thead><tr><th>NAME</th><th>EMAIL</th><th>ROLE</th><th>ACTION</th></tr></thead>
            <tbody>
            @foreach ($users as $user)
                <tr><td>{{ $user->name }}</td><td>{{ $user->email }}</td><td>{{ optional($user->roles->first())->label ?: $user->role }}</td><td>
                    <form method="POST" action="{{ route('roles.users.update', $user->id) }}" class="inline-form">
                        @csrf @method('PUT')
                        <select class="field-control" name="role_id">@foreach ($roles as $roleOption)<option value="{{ $roleOption->id }}" {{ optional($user->roles->first())->id === $roleOption->id ? 'selected' : '' }}>{{ $roleOption->label }}</option>@endforeach</select>
                        <button class="button button-small button-light" type="submit">Assign</button>
                    </form>
                </td></tr>
            @endforeach
            @if ($users->isEmpty())
                <tr><td colspan="4">No users are assigned to this role yet.</td></tr>
            @endif
            </tbody>
        </table></div>
    </section>

    <section class="panel listing-panel">
        <div class="panel-heading"><h2>Assign users</h2><p>Choose a role for each account. Users immediately inherit that role's permissions.</p></div>
        <div class="table-wrap"><table class="data-table listing-table">
            <thead><tr><th>NAME</th><th>EMAIL</th><th>CURRENT ROLE</th><th>ASSIGN ROLE</th></tr></thead>
            <tbody>
            @foreach ($allUsers as $user)
                <tr><td>{{ $user->name }}</td><td>{{ $user->email }}</td><td>{{ optional($user->roles->first())->label ?: $user->role }}</td><td>
                    <form method="POST" action="{{ route('roles.users.update', $user->id) }}" class="inline-form">
                        @csrf @method('PUT')
                        <select class="field-control" name="role_id">@foreach ($roles as $roleOption)<option value="{{ $roleOption->id }}" {{ optional($user->roles->first())->id === $roleOption->id ? 'selected' : '' }}>{{ $roleOption->label }}</option>@endforeach</select>
                        <button class="button button-small button-light" type="submit">Assign</button>
                    </form>
                </td></tr>
            @endforeach
            </tbody>
        </table></div>
    </section>
@endsection
