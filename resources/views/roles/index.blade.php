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
                        <thead><tr><th>MODULE</th><th>VIEW</th><th>CREATE</th><th>EDIT</th><th>DELETE</th><th>SELECT ALL</th></tr></thead>
                        <tbody>
                        @foreach ($moduleGroups as $module => $modules)
                            <tr class="permission-row">
                                <td><strong>{{ ucwords(str_replace('_', ' ', $module)) }}</strong></td>
                                @foreach (['view', 'create', 'edit', 'delete'] as $action)
                                    @php
                                        $modulePermissions = $selectedRole->permissions
                                            ->whereIn('module', $modules)
                                            ->where('action', $module === 'quotation_approval' && $action === 'view' ? 'approve' : $action);
                                        $hasPermission = $modulePermissions->count() === count($modules);
                                    @endphp
                                    <td>
                                        <input type="checkbox"
                                               class="permission-checkbox"
                                               name="permissions[{{ $module }}][{{ $action }}]"
                                               value="1"
                                               {{ $hasPermission ? 'checked' : '' }}>
                                    </td>
                                @endforeach
                                <td>
                                    <input type="checkbox" class="select-all-checkbox" aria-label="Select all {{ ucwords(str_replace('_', ' ', $module)) }} permissions">
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <script>
                    document.querySelectorAll('.permission-row').forEach(function (row) {
                        var permissions = row.querySelectorAll('.permission-checkbox');
                        var selectAll = row.querySelector('.select-all-checkbox');

                        var updateSelectAll = function () {
                            selectAll.checked = Array.from(permissions).every(function (permission) {
                                return permission.checked;
                            });
                        };

                        selectAll.addEventListener('change', function () {
                            permissions.forEach(function (permission) {
                                permission.checked = selectAll.checked;
                            });
                        });

                        permissions.forEach(function (permission) {
                            permission.addEventListener('change', updateSelectAll);
                        });

                        updateSelectAll();
                    });
                </script>
                <div class="form-actions"><button class="button button-primary" type="submit">Save {{ $selectedRole->label }} permissions</button></div>
            </form>
        </section>
    @endif

@endsection
