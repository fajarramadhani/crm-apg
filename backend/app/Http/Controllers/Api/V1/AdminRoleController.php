<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AdminRoleController extends Controller
{
    public function destroy(Request $request, Role $role): JsonResponse
    {
        if (! $request->user()->hasRole('superadmin')) {
            return ApiResponse::error($request, 'Hanya Super Admin yang dapat menghapus role.', 'FORBIDDEN', 403);
        }

        if ($role->key === 'superadmin') {
            return ApiResponse::error($request, 'Role Super Admin tidak dapat dihapus.', 'PROTECTED_ROLE', 409);
        }

        return DB::transaction(function () use ($request, $role): JsonResponse {
            $lockedRole = Role::query()->lockForUpdate()->findOrFail($role->id);
            $userCount = $lockedRole->users()->count();
            $transitionCount = Schema::hasTable('workflow_transition_permissions')
                ? DB::table('workflow_transition_permissions')->where('role_key', $lockedRole->key)->count()
                : 0;
            $approvalCount = Schema::hasTable('workflow_approval_steps')
                ? DB::table('workflow_approval_steps')->where('approver_role_key', $lockedRole->key)->count()
                : 0;

            if ($userCount + $transitionCount + $approvalCount > 0) {
                return ApiResponse::error($request, 'Role tidak dapat dihapus karena masih digunakan oleh akun atau workflow.', 'ROLE_IN_USE', 409, [
                    'references' => [
                        'users' => $userCount,
                        'workflow_transitions' => $transitionCount,
                        'workflow_approvals' => $approvalCount,
                    ],
                ]);
            }

            $lockedRole->delete();

            return ApiResponse::success($request, 'Role berhasil dihapus.');
        });
    }
}
