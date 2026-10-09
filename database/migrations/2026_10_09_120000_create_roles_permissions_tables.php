<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateRolesPermissionsTables extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('roles')) {
            Schema::create('roles', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('name')->unique();
                $table->string('label');
                $table->string('description')->nullable();
                $table->boolean('is_system')->default(false);
                $table->timestamps();
            });
        }
        if (!Schema::hasTable('permissions')) {
            Schema::create('permissions', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('module');
                $table->string('action');
                $table->string('label');
                $table->timestamps();
                $table->unique(['module', 'action']);
            });
        }
        if (!Schema::hasTable('role_user')) {
            Schema::create('role_user', function (Blueprint $table) {
                $table->unsignedBigInteger('role_id');
                $table->unsignedBigInteger('user_id');
                $table->primary(['role_id', 'user_id']);
                $table->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');
                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            });
        }
        if (!Schema::hasTable('permission_role')) {
            Schema::create('permission_role', function (Blueprint $table) {
                $table->unsignedBigInteger('permission_id');
                $table->unsignedBigInteger('role_id');
                $table->primary(['permission_id', 'role_id']);
                $table->foreign('permission_id')->references('id')->on('permissions')->onDelete('cascade');
                $table->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');
            });
        }

        $roles = [
            ['name' => 'ADMIN', 'label' => 'Admin', 'description' => 'Full system access', 'is_system' => true],
            ['name' => 'PURCHASE_OFFICER', 'label' => 'Store Manager / Purchase Officer', 'description' => 'Operational purchasing and inventory', 'is_system' => true],
            ['name' => 'OWNER', 'label' => 'Owner / Authority', 'description' => 'Quotation approval and oversight', 'is_system' => true],
        ];
        foreach ($roles as $role) {
            if (!DB::table('roles')->where('name', $role['name'])->exists()) {
                DB::table('roles')->insert($role + ['created_at' => now(), 'updated_at' => now()]);
            }
        }

        $modules = ['dashboard', 'suppliers', 'products', 'quotations', 'purchase', 'goods_receipts', 'stock', 'reports', 'settings', 'roles'];
        $actions = ['view', 'create', 'edit', 'delete', 'approve', 'reject'];
        foreach ($modules as $module) {
            foreach ($actions as $action) {
                if (!DB::table('permissions')->where('module', $module)->where('action', $action)->exists()) {
                    DB::table('permissions')->insert([
                        'module' => $module,
                        'action' => $action,
                        'label' => ucwords(str_replace('_', ' ', $module)) . ' ' . ucfirst($action),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        $roleIds = DB::table('roles')->pluck('id', 'name');
        $permissionIds = DB::table('permissions')->get(['id', 'module', 'action']);
        $assign = function ($roleName, $permissions) use ($roleIds, $permissionIds) {
            foreach ($permissionIds as $permission) {
                if (in_array($permission->id, $permissions, true)
                    && !DB::table('permission_role')->where('role_id', $roleIds[$roleName])->where('permission_id', $permission->id)->exists()) {
                    DB::table('permission_role')->insert([
                        'role_id' => $roleIds[$roleName],
                        'permission_id' => $permission->id,
                    ]);
                }
            }
        };
        $adminPermissions = $permissionIds->pluck('id')->all();
        $officerPermissions = $permissionIds->whereIn('module', ['dashboard', 'suppliers', 'products', 'quotations', 'purchase', 'goods_receipts', 'stock', 'reports'])
            ->whereIn('action', ['view', 'create', 'edit'])->pluck('id')->all();
        $ownerPermissions = $permissionIds->whereIn('module', ['dashboard', 'quotations', 'purchase', 'stock', 'reports'])
            ->whereIn('action', ['view', 'approve', 'reject'])->pluck('id')->all();
        $assign('ADMIN', $adminPermissions);
        $assign('PURCHASE_OFFICER', $officerPermissions);
        $assign('OWNER', $ownerPermissions);

        foreach (DB::table('users')->get(['id', 'role']) as $user) {
            $roleName = strtolower((string) $user->role) === 'admin' ? 'ADMIN' : 'PURCHASE_OFFICER';
            $roleId = $roleIds[$roleName];
            DB::table('role_user')->updateOrInsert(
                ['role_id' => $roleId, 'user_id' => $user->id],
                []
            );
        }
    }

    public function down()
    {
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }
}
