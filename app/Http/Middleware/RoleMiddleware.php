<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        if (! $request->user()) {
            abort(401);
        }

        if (! in_array($request->user()->role->value, $roles, true)) {
            abort(403);
        }

        return $next($request);
    }
}
