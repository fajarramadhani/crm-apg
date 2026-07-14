<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (! $request->user()?->hasRole($roles)) {
            return ApiResponse::error($request, 'You are not authorized to access this resource.', 'FORBIDDEN', 403);
        }

        return $next($request);
    }
}
