<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UpdateLocationPermissionsForOwnerRole extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('permissions') || !Schema::hasTable('roles')) {
            return;
        }

        $ownerRole = DB::table('roles')->where('name', 'OWNER')->first();
        $purchaseOfficerRole = DB::table('roles')->where('name', 'PURCHASE_OFFICER')->first();

        if (!$ownerRole) {
            return;
        }

        // Get all location permissions
        $locationPermissions = DB::table('permissions')
            ->where('module', 'locations')
            ->get(['id', 'action']);

        if ($locationPermissions->isEmpty()) {
            return;
        }

        // Grant OWNER all location permissions (view, create, edit, delete)
        foreach ($locationPermissions as $permission) {
            DB::table('permission_role')->updateOrInsert([
                'role_id' => $ownerRole->id,
                'permission_id' => $permission->id,
            ], []);
        }

        // Restrict PURCHASE_OFFICER to only view locations (remove create, edit, delete if present)
        if ($purchaseOfficerRole) {
            $viewPermission = DB::table('permissions')
                ->where('module', 'locations')
                ->where('action', 'view')
                ->first();

            if ($viewPermission) {
                // Keep only view permission
                DB::table('permission_role')->updateOrInsert([
                    'role_id' => $purchaseOfficerRole->id,
                    'permission_id' => $viewPermission->id,
                ], []);
            }

            // Remove create, edit, delete permissions for PURCHASE_OFFICER
            $restrictedActions = ['create', 'edit', 'delete'];
            $restrictedPermissionIds = DB::table('permissions')
                ->where('module', 'locations')
                ->whereIn('action', $restrictedActions)
                ->pluck('id');

            if ($restrictedPermissionIds->isNotEmpty()) {
                DB::table('permission_role')
                    ->where('role_id', $purchaseOfficerRole->id)
                    ->whereIn('permission_id', $restrictedPermissionIds)
                    ->delete();
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (!Schema::hasTable('permissions') || !Schema::hasTable('roles')) {
            return;
        }

        $ownerRole = DB::table('roles')->where('name', 'OWNER')->first();
        $purchaseOfficerRole = DB::table('roles')->where('name', 'PURCHASE_OFFICER')->first();

        if (!$ownerRole) {
            return;
        }

        // Remove location permissions from OWNER
        $locationPermissionIds = DB::table('permissions')
            ->where('module', 'locations')
            ->pluck('id');

        if ($locationPermissionIds->isNotEmpty()) {
            DB::table('permission_role')
                ->where('role_id', $ownerRole->id)
                ->whereIn('permission_id', $locationPermissionIds)
                ->delete();
        }

        // Restore PURCHASE_OFFICER permissions (view, create, edit)
        if ($purchaseOfficerRole) {
            $permissions = DB::table('permissions')
                ->where('module', 'locations')
                ->whereIn('action', ['view', 'create', 'edit'])
                ->get(['id']);

            foreach ($permissions as $permission) {
                DB::table('permission_role')->updateOrInsert([
                    'role_id' => $purchaseOfficerRole->id,
                    'permission_id' => $permission->id,
                ], []);
            }
        }
    }
}
