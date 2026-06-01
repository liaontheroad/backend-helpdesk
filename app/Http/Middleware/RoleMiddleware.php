<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): mixed
    {
        if (! $request->user()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if (! in_array($request->user()->role, $roles)) {
            return response()->json([
                'message' => 'Akses ditolak. Role kamu tidak memiliki izin untuk aksi ini.',
            ], 403);
        }

        return $next($request);
    }
}
