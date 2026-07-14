<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        if (! $request->user()?->hasPermission($permission)) {
            return ApiResponse::error($request, 'You are not authorized to access this resource.', 'FORBIDDEN', 403);
        }

        return $next($request);
    }
}
