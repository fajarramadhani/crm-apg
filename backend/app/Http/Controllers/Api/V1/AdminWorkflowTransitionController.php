<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowTransition;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

final class AdminWorkflowTransitionController extends Controller
{
    public function store(Request $request, WorkflowDefinition $workflow): JsonResponse
    {
        Gate::authorize('workflow.transition.manage');

        if (! $workflow->isDraft()) {
            return ApiResponse::error($request, 'Hanya draft workflow yang dapat dimodifikasi.', 'WORKFLOW_NOT_DRAFT', 422);
        }

        $data = $request->validate([
            'from_stage_id' => ['required', 'integer', 'exists:workflow_stages,id'],
            'to_stage_id' => ['required', 'integer', 'exists:workflow_stages,id'],
            'action_key' => ['required', 'string', 'max:50', 'regex:/^[a-z0-9_]+$/'],
            'name' => ['required', 'string', 'max:150'],
            'requires_notes' => ['boolean'],
            'permissions' => ['required', 'array', 'min:1'],
            'permissions.*.role_key' => ['nullable', 'string', 'max:50'],
            'permissions.*.permission_code' => ['nullable', 'string', 'max:100'],
            'permissions.*' => [function (string $attribute, mixed $value, \Closure $fail): void {
                if (empty($value['role_key'] ?? null) && empty($value['permission_code'] ?? null)) {
                    $fail('Permission harus memiliki role_key atau permission_code.');
                }
            }],
            'notifications' => ['nullable', 'array'],
            'notifications.*.recipient_type' => ['required_with:notifications', 'string', 'in:requester,primary_pic,secondary_pics,supervisor_it,specific_role'],
            'notifications.*.channel' => ['sometimes', 'string', 'in:database,email'],
            'notifications.*.template_code' => ['nullable', 'string', 'max:100'],
        ]);

        // Validate from/to stages belong to this workflow
        $stageIds = $workflow->stages()->pluck('id')->toArray();
        if (! in_array($data['from_stage_id'], $stageIds)) {
            return ApiResponse::error($request, 'from_stage_id tidak ditemukan dalam workflow ini.', 'INVALID_STAGE', 422);
        }
        if (! in_array($data['to_stage_id'], $stageIds)) {
            return ApiResponse::error($request, 'to_stage_id tidak ditemukan dalam workflow ini.', 'INVALID_STAGE', 422);
        }

        $transition = $workflow->transitions()->create([
            'from_stage_id' => $data['from_stage_id'],
            'to_stage_id' => $data['to_stage_id'],
            'action_key' => $data['action_key'],
            'name' => $data['name'],
            'requires_notes' => $data['requires_notes'] ?? false,
        ]);

        // Store permissions
        foreach ($data['permissions'] as $perm) {
            $transition->permissions()->create([
                'role_key' => $perm['role_key'] ?? null,
                'permission_code' => $perm['permission_code'] ?? null,
            ]);
        }

        // Store notifications
        foreach ($data['notifications'] ?? [] as $notif) {
            $transition->notifications()->create([
                'recipient_type' => $notif['recipient_type'],
                'channel' => $notif['channel'] ?? 'database',
                'template_code' => $notif['template_code'] ?? null,
            ]);
        }

        Log::info('workflow.transition.created', [
            'workflow_id' => $workflow->id,
            'transition_id' => $transition->id,
            'action_key' => $transition->action_key,
            'actor_id' => $request->user()->id,
        ]);

        return ApiResponse::success(
            $request,
            'Workflow transition created successfully',
            ['transition' => $transition->load(['permissions', 'notifications'])],
            201
        );
    }

    public function update(Request $request, WorkflowDefinition $workflow, WorkflowTransition $transition): JsonResponse
    {
        Gate::authorize('workflow.transition.manage');

        if (! $workflow->isDraft()) {
            return ApiResponse::error($request, 'Hanya draft workflow yang dapat dimodifikasi.', 'WORKFLOW_NOT_DRAFT', 422);
        }

        if ($transition->workflow_id !== $workflow->id) {
            return ApiResponse::error($request, 'Transition tidak ditemukan dalam workflow ini.', 'TRANSITION_NOT_FOUND', 404);
        }

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:150'],
            'requires_notes' => ['sometimes', 'boolean'],
            'permissions' => ['sometimes', 'array', 'min:1'],
            'permissions.*.role_key' => ['nullable', 'string', 'max:50'],
            'permissions.*.permission_code' => ['nullable', 'string', 'max:100'],
            'permissions.*' => [function (string $attribute, mixed $value, \Closure $fail): void {
                if (empty($value['role_key'] ?? null) && empty($value['permission_code'] ?? null)) {
                    $fail('Permission harus memiliki role_key atau permission_code.');
                }
            }],
            'notifications' => ['sometimes', 'nullable', 'array'],
            'notifications.*.recipient_type' => ['required_with:notifications', 'string', 'in:requester,primary_pic,secondary_pics,supervisor_it,specific_role'],
            'notifications.*.channel' => ['sometimes', 'string', 'in:database,email'],
            'notifications.*.template_code' => ['nullable', 'string', 'max:100'],
        ]);

        $transition->update(array_filter([
            'name' => $data['name'] ?? null,
            'requires_notes' => $data['requires_notes'] ?? null,
        ], fn ($v) => $v !== null));

        if (isset($data['permissions'])) {
            $transition->permissions()->delete();
            foreach ($data['permissions'] as $perm) {
                $transition->permissions()->create([
                    'role_key' => $perm['role_key'] ?? null,
                    'permission_code' => $perm['permission_code'] ?? null,
                ]);
            }
        }

        if (array_key_exists('notifications', $data)) {
            $transition->notifications()->delete();
            foreach ($data['notifications'] ?? [] as $notif) {
                $transition->notifications()->create([
                    'recipient_type' => $notif['recipient_type'],
                    'channel' => $notif['channel'] ?? 'database',
                    'template_code' => $notif['template_code'] ?? null,
                ]);
            }
        }

        Log::info('workflow.transition.updated', [
            'workflow_id' => $workflow->id,
            'transition_id' => $transition->id,
            'actor_id' => $request->user()->id,
        ]);

        return ApiResponse::success($request, 'Workflow transition updated successfully', ['transition' => $transition->fresh()->load(['permissions', 'notifications'])]);
    }

    public function destroy(Request $request, WorkflowDefinition $workflow, WorkflowTransition $transition): JsonResponse
    {
        Gate::authorize('workflow.transition.manage');

        if (! $workflow->isDraft()) {
            return ApiResponse::error($request, 'Hanya draft workflow yang dapat dimodifikasi.', 'WORKFLOW_NOT_DRAFT', 422);
        }

        if ($transition->workflow_id !== $workflow->id) {
            return ApiResponse::error($request, 'Transition tidak ditemukan dalam workflow ini.', 'TRANSITION_NOT_FOUND', 404);
        }

        $transition->delete();

        Log::info('workflow.transition.deleted', [
            'workflow_id' => $workflow->id,
            'transition_id' => $transition->id,
            'actor_id' => $request->user()->id,
        ]);

        return ApiResponse::success($request, 'Workflow transition deleted successfully', []);
    }
}
