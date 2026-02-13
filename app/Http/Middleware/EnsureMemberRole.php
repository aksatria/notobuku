<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureMemberRole
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        // Safety jika auth belum jalan
        if (!$user) {
            return redirect()->route('login');
        }

        $role = (string) ($user->role ?? 'member');

        // Tolak role non-member
        if (in_array($role, ['super_admin', 'admin', 'staff'], true)) {
            return redirect()->route('app');
        }

        return $next($request);
    }
}
