<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RoleAnyMiddleware
{
    public function handle(Request $request, Closure $next, ...$roles)
    {
        $user = $request->user();

        if (!$user) {
            return redirect()->route('login');
        }

        if (empty($roles)) {
            return redirect()->route('app');
        }

        $role = (string) ($user->role ?? 'member');

        if (!in_array($role, $roles, true)) {
            return redirect()->route('app');
        }

        return $next($request);
    }
}
