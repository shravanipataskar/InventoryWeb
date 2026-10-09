<?php

namespace App\Http\Middleware;

use Closure;

class PermissionMiddleware
{
    public function handle($request, Closure $next, $permission)
    {
        [$module, $action] = array_pad(explode('.', $permission, 2), 2, 'view');

        abort_unless($request->user() && $request->user()->hasPermission($module, $action), 403);

        return $next($request);
    }
}
