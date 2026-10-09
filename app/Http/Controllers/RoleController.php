<?php

namespace App\Http\Controllers;

use App\Permission;
use App\Role;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RoleController extends Controller
{
    public function index(Request $request)
    {
        $roles = Role::with('permissions')->orderBy('label')->get();
        $permissions = Permission::orderBy('module')->orderBy('action')->get()->groupBy('module');
        $selectedRole = $roles->firstWhere('id', (int) $request->query('role_id')) ?: $roles->first();
        $users = User::with('roles')
            ->whereHas('roles', function ($query) use ($selectedRole) {
                $query->where('roles.id', optional($selectedRole)->id);
            })
            ->orderBy('name')
            ->get();
        $allUsers = User::with('roles')->orderBy('name')->get();

        return view('roles.index', compact('roles', 'permissions', 'selectedRole', 'users', 'allUsers'));
    }

    public function update(Request $request, $id)
    {
        $role = Role::findOrFail($id);
        $permissionIds = $request->input('permissions', []);
        $role->permissions()->sync(array_map('intval', $permissionIds));

        return redirect()
            ->route('roles.index', ['role_id' => $role->id])
            ->with('success', 'Permissions updated for ' . $role->label . '.');
    }

    public function assignUser(Request $request, $id)
    {
        $validated = $request->validate(['role_id' => 'required|exists:roles,id']);
        $user = User::findOrFail($id);
        $user->roles()->sync([$validated['role_id']]);
        $user->role = Role::findOrFail($validated['role_id'])->name;
        $user->save();

        return redirect()
            ->route('roles.index', ['role_id' => $validated['role_id']])
            ->with('success', 'User role updated.');
    }
}
