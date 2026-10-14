<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AllowOwnersToViewSuppliers extends Migration
{
    public function up()
    {
        $ownerId = DB::table('roles')->where('name', 'OWNER')->value('id');
        $permissionId = DB::table('permissions')
            ->where('module', 'suppliers')
            ->where('action', 'view')
            ->value('id');

        if ($ownerId && $permissionId && !DB::table('permission_role')
            ->where('role_id', $ownerId)
            ->where('permission_id', $permissionId)
            ->exists()) {
            DB::table('permission_role')->insert([
                'role_id' => $ownerId,
                'permission_id' => $permissionId,
            ]);
        }
    }

    public function down()
    {
        $ownerId = DB::table('roles')->where('name', 'OWNER')->value('id');
        $permissionId = DB::table('permissions')
            ->where('module', 'suppliers')
            ->where('action', 'view')
            ->value('id');

        if ($ownerId && $permissionId) {
            DB::table('permission_role')
                ->where('role_id', $ownerId)
                ->where('permission_id', $permissionId)
                ->delete();
        }
    }
}
