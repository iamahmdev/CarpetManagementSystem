<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PermissionMiddleware
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        $role = $user->role?->value;

        if (! $role) {
            abort(403);
        }

        $permissions = config("roles.$role", []);

        if (! in_array($permission, $permissions, true)) {
            abort(403);
        }

        return $next($request);
    }
}
