<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ChangePasswordRequest;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Resources\Api\V1\AuthenticatedUserResource;
use App\Models\User;
use App\Support\ApiResponse;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        DB::transaction(function () use ($request, $user): void {
            /** @var User $locked */
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);

            $locked->forceFill([
                'password' => Hash::make($request->string('password')->toString()),
                'must_change_password' => false,
            ])->save();

            AuditLogger::record(
                'identity.password_changed',
                actor: $locked,
                auditable: $locked,
                metadata: ['source' => 'auth.change-password'],
            );
        });

        // Rotate the session identifier after a credential change. The session
        // store only exists when the request passed through stateful middleware.
        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        return ApiResponse::success($request, 'Password berhasil diubah.', [
            'user' => (new AuthenticatedUserResource($user->fresh('role')))->resolve($request),
        ]);
    }
}
