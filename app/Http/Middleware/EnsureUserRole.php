<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        if ($user->hasAnyRole(...$roles)) {
            return $next($request);
        }

        return response()->json([
            'message' => __('edubridge.unauthorized_role'),
        ], 403);
    }
}
