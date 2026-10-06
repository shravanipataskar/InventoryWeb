<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class RedirectIfAuthenticated
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string|null  ...$guards
     * @return mixed
     */
    public function handle($request, Closure $next, ...$guards)
    {
        $guards = empty($guards)
            ? [null]
            : $guards;

        foreach ($guards as $guard) {

            if (Auth::guard($guard)->check()) {

                /*
                |--------------------------------------------------------------------------
                | IMPORTANT
                |--------------------------------------------------------------------------
                | Never redirect authenticated users to /home.
                | This application uses /dashboard.
                |--------------------------------------------------------------------------
                */

                return redirect()->route('dashboard');
            }
        }

        return $next($request);
    }
}