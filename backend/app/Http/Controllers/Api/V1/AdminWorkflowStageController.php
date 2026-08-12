<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowStage;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

final class AdminWorkflowStageController extends Controller
{
    public function store(Request $request, WorkflowDefinition $workflow): JsonResponse
    {
        Gate::authorize('workflow.stage.manage');

        if (! $workflow->isDraft()) {
            return ApiResponse::error($request, 'Hanya draft workflow yang dapat dimodifikasi.', 'WORKFLOW_NOT_DRAFT', 422);
        }

        $data = $request->validate([
            'stage_key' => ['required', 'string', 'max:50', 'regex:/^[a-z0-9_]+$/'],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:500'],
            'order' => ['integer', 'min:0'],
            'stage_type' => ['string', 'in:normal,special,approval'],
            'is_initial' => ['boolean'],
            'is_terminal' => ['boolean'],
        ]);

        // Ensure unique stage_key within workflow
        $exists = $workflow->stages()->where('stage_key', $data['stage_key'])->exists();
        if ($exists) {
            return ApiResponse::error($request, "Stage key '{$data['stage_key']}' sudah ada dalam workflow ini.", 'STAGE_KEY_DUPLICATE', 422);
        }

        $stage = $workflow->stages()->create($data);

        Log::info('workflow.stage.created', [
            'workflow_id' => $workflow->id,
            'stage_id' => $stage->id,
            'stage_key' => $stage->stage_key,
            'actor_id' => $request->user()->id,
        ]);

        return ApiResponse::success($request, 'Workflow stage created successfully', ['stage' => $stage], 201);
    }

    public function update(Request $request, WorkflowDefinition $workflow, WorkflowStage $stage): JsonResponse
    {
        Gate::authorize('workflow.stage.manage');

        if (! $workflow->isDraft()) {
            return ApiResponse::error($request, 'Hanya draft workflow yang dapat dimodifikasi.', 'WORKFLOW_NOT_DRAFT', 422);
        }

        if ($stage->workflow_id !== $workflow->id) {
            return ApiResponse::error($request, 'Stage tidak ditemukan dalam workflow ini.', 'STAGE_NOT_FOUND', 404);
        }

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:500'],
            'order' => ['sometimes', 'integer', 'min:0'],
            'stage_type' => ['sometimes', 'string', 'in:normal,special,approval'],
            'is_initial' => ['sometimes', 'boolean'],
            'is_terminal' => ['sometimes', 'boolean'],
        ]);

        $stage->update($data);

        Log::info('workflow.stage.updated', [
            'workflow_id' => $workflow->id,
            'stage_id' => $stage->id,
            'actor_id' => $request->user()->id,
        ]);

        return ApiResponse::success($request, 'Workflow stage updated successfully', ['stage' => $stage->fresh()]);
    }

    public function destroy(Request $request, WorkflowDefinition $workflow, WorkflowStage $stage): JsonResponse
    {
        Gate::authorize('workflow.stage.manage');

        if (! $workflow->isDraft()) {
            return ApiResponse::error($request, 'Hanya draft workflow yang dapat dimodifikasi.', 'WORKFLOW_NOT_DRAFT', 422);
        }

        if ($stage->workflow_id !== $workflow->id) {
            return ApiResponse::error($request, 'Stage tidak ditemukan dalam workflow ini.', 'STAGE_NOT_FOUND', 404);
        }

        $stage->delete();

        Log::info('workflow.stage.deleted', [
            'workflow_id' => $workflow->id,
            'stage_id' => $stage->id,
            'actor_id' => $request->user()->id,
        ]);

        return ApiResponse::success($request, 'Workflow stage deleted successfully', []);
    }

    // =========================================================================
    // Stage Fields
    // =========================================================================

    public function storeField(Request $request, WorkflowDefinition $workflow, WorkflowStage $stage): JsonResponse
    {
        Gate::authorize('workflow.field.manage');

        if (! $workflow->isDraft()) {
            return ApiResponse::error($request, 'Hanya draft workflow yang dapat dimodifikasi.', 'WORKFLOW_NOT_DRAFT', 422);
        }

        $data = $request->validate([
            'field_name' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9_]+$/'],
            'is_required' => ['boolean'],
            'is_readonly' => ['boolean'],
            'is_hidden' => ['boolean'],
        ]);

        $exists = $stage->fields()->where('field_name', $data['field_name'])->exists();
        if ($exists) {
            return ApiResponse::error($request, "Field name '{$data['field_name']}' sudah ada dalam stage ini.", 'FIELD_DUPLICATE', 422);
        }

        $field = $stage->fields()->create($data);

        return ApiResponse::success($request, 'Workflow stage field created successfully', ['field' => $field], 201);
    }

    public function destroyField(Request $request, WorkflowDefinition $workflow, WorkflowStage $stage, int $fieldId): JsonResponse
    {
        Gate::authorize('workflow.field.manage');

        if (! $workflow->isDraft()) {
            return ApiResponse::error($request, 'Hanya draft workflow yang dapat dimodifikasi.', 'WORKFLOW_NOT_DRAFT', 422);
        }

        $field = $stage->fields()->findOrFail($fieldId);
        $field->delete();

        return ApiResponse::success($request, 'Workflow stage field deleted successfully', []);
    }
}
