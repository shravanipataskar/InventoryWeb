<?php

namespace App\Support;

use App\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ActivityLogger
{
    public static function log($action, $subject, $description, User $user = null)
    {
        $user = $user ?: Auth::user();
        $values = [
            'action' => $action,
            'description' => $description,
        ];

        if (Schema::hasColumn('activity_logs', 'actor_name')) {
            $values['actor_name'] = $user ? $user->name : 'System';
            $values['subject'] = $subject;
        } else {
            $values['user_id'] = $user ? $user->id : null;
            $values['module'] = $subject;
        }

        if (Schema::hasColumn('activity_logs', 'ip_address')) {
            $values['ip_address'] = request()->ip();
        }

        $now = now();
        $values['created_at'] = $now;
        $values['updated_at'] = $now;

        DB::table('activity_logs')->insert($values);
    }
}
