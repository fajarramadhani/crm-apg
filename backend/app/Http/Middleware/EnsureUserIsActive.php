<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user()?->loadMissing('role');

        if (! $user?->is_active || ! $user->role?->is_active) {
            Auth::guard('web')->logout();

            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            return ApiResponse::error($request, 'Unauthenticated.', 'UNAUTHENTICATED', 401);
        }

        return $next($request);
    }
}
