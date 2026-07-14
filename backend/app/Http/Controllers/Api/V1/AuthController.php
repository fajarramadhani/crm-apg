<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Resources\Api\V1\AuthenticatedUserResource;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::query()
            ->with('role')
            ->where('email', $request->string('email')->lower()->toString())
            ->first();

        if (! $user ||
            ! Hash::check($request->string('password')->toString(), $user->password) ||
            ! $user->is_active ||
            ! $user->role?->is_active) {
            return ApiResponse::error(
                $request,
                'Email atau password tidak sesuai.',
                'INVALID_CREDENTIALS',
                422,
            );
        }

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        $user->forceFill(['last_login_at' => now()])->save();

        return ApiResponse::success($request, 'Login successful', [
            'user' => (new AuthenticatedUserResource($user->fresh('role')))->resolve($request),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return ApiResponse::success($request, 'Authenticated user retrieved', [
            'user' => (new AuthenticatedUserResource($request->user()->loadMissing('role')))->resolve($request),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return ApiResponse::success($request, 'Logout successful');
    }
}
