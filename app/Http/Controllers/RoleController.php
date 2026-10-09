<?php

namespace App\Http\Controllers;

use App\Permission;
use App\Role;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RoleController extends Controller
{
    private $moduleGroups = [
        'dashboard' => ['dashboard'],
        'master_data' => ['categories', 'units', 'locations', 'companies', 'customers', 'products', 'suppliers'],
        'purchase' => ['quotations', 'purchase', 'goods_receipts'],
        'quotation_approval' => ['quotations'],
        'inventory' => ['stock'],
        'reports' => ['reports'],
        'administration' => ['users', 'roles', 'activity_log', 'settings'],
    ];

    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $user = $request->user();

            abort_unless($user && $user->canManageRoles(), 403);

            return $next($request);
        });
    }

    public function index(Request $request)
    {
        $roles = Role::with('permissions')->orderBy('label')->get();
        $selectedRole = $roles->firstWhere('id', (int) $request->query('role_id')) ?: $roles->first();
        $availableModules = Permission::query()->pluck('module')->unique()->all();
        $moduleGroups = collect($this->moduleGroups)
            ->map(function ($modules) use ($availableModules) {
                return array_values(array_intersect($modules, $availableModules));
            })
            ->filter(function ($modules) {
                return count($modules) > 0;
            })
            ->all();

        return view('roles.index', compact('roles', 'moduleGroups', 'selectedRole'));
    }

    public function update(Request $request, $id)
    {
        $role = Role::findOrFail($id);
        $selectedPermissions = $request->input('permissions', []);
        $permissionIds = [];

        foreach ($this->moduleGroups as $moduleKey => $modules) {
            foreach (['view', 'create', 'edit', 'delete'] as $action) {
                $permissionAction = $moduleKey === 'quotation_approval' && $action === 'view'
                    ? 'approve'
                    : $action;

                if (!isset($selectedPermissions[$moduleKey][$action])) {
                    continue;
                }

                $permissionIds = array_merge(
                    $permissionIds,
                    Permission::whereIn('module', $modules)
                        ->where('action', $permissionAction)
                        ->pluck('id')
                        ->all()
                );
            }
        }

        $permissionIds = array_merge(
            $permissionIds,
            $role->permissions()
                ->whereNotIn('action', ['view', 'create', 'edit', 'delete'])
                ->pluck('permissions.id')
                ->all()
        );

        $role->permissions()->sync($permissionIds);

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
