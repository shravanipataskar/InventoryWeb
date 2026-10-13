<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SyncUserRoleColumnWithRoleAssignments extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('users')
            || !Schema::hasTable('roles')
            || !Schema::hasTable('role_user')
            || !Schema::hasColumn('users', 'role')) {
            return;
        }

        $usersWithSingleRole = DB::table('role_user')
            ->select('user_id')
            ->groupBy('user_id')
            ->havingRaw('COUNT(*) = 1');

        $assignments = DB::table('role_user')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->whereIn('role_user.user_id', $usersWithSingleRole)
            ->get(['role_user.user_id', 'roles.name']);

        foreach ($assignments as $assignment) {
            DB::table('users')
                ->where('id', $assignment->user_id)
                ->where('role', '<>', $assignment->name)
                ->update(['role' => $assignment->name]);
        }
    }

    public function down()
    {
        // The prior denormalized values cannot be reconstructed after synchronization.
    }
}
