<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ExpandRbacPermissions extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('permissions')) {
            return;
        }

        $modules = [
            'categories',
            'units',
            'locations',
            'companies',
            'customers',
            'users',
            'activity_log',
            'settings',
        ];
        $actions = ['view', 'create', 'edit', 'delete'];

        foreach ($modules as $module) {
            foreach ($actions as $action) {
                DB::table('permissions')->updateOrInsert(
                    ['module' => $module, 'action' => $action],
                    [
                        'label' => ucwords(str_replace('_', ' ', $module)) . ' ' . ucfirst($action),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }

        $roles = DB::table('roles')->pluck('id', 'name');
        $permissions = DB::table('permissions')
            ->whereIn('module', $modules)
            ->get(['id', 'module', 'action']);

        $assign = function ($roleName, $allowedModules, $allowedActions) use ($roles, $permissions) {
            if (!isset($roles[$roleName])) {
                return;
            }

            foreach ($permissions as $permission) {
                if (in_array($permission->module, $allowedModules, true)
                    && in_array($permission->action, $allowedActions, true)) {
                    DB::table('permission_role')->updateOrInsert([
                        'role_id' => $roles[$roleName],
                        'permission_id' => $permission->id,
                    ], []);
                }
            }
        };

        $assign(
            'ADMIN',
            $modules,
            $actions
        );
        $assign(
            'PURCHASE_OFFICER',
            ['categories', 'units', 'locations', 'companies', 'customers'],
            ['view', 'create', 'edit']
        );
        $assign(
            'OWNER',
            ['customers', 'companies'],
            ['view']
        );
    }

    public function down()
    {
        if (!Schema::hasTable('permissions')) {
            return;
        }

        $modules = [
            'categories',
            'units',
            'locations',
            'companies',
            'customers',
            'users',
            'activity_log',
            'settings',
        ];
        $permissionIds = DB::table('permissions')
            ->whereIn('module', $modules)
            ->pluck('id');

        DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
    }
}
