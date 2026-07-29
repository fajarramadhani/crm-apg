<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateWorkflowApprovalRequest;
use App\Models\WorkflowApprovalConfig;
use App\Models\WorkflowDefinition;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

final class AdminWorkflowApprovalController extends Controller
{
    public function show(Request $request, WorkflowDefinition $workflow): JsonResponse
    {
        Gate::authorize('viewApproval', $workflow);

        return ApiResponse::success($request, 'Workflow approval configuration retrieved successfully', [
            'approval' => $this->approvalData($workflow->approvalConfigs()->with('steps')->first()),
        ]);
    }

    public function update(UpdateWorkflowApprovalRequest $request, WorkflowDefinition $workflow): JsonResponse
    {
        if (! $workflow->isDraft()) {
            return ApiResponse::error($request, 'Hanya draft workflow yang dapat dimodifikasi.', 'WORKFLOW_NOT_DRAFT', 422);
        }

        $data = $request->validated();

        $config = DB::transaction(function () use ($workflow, $data): WorkflowApprovalConfig {
            $configs = $workflow->approvalConfigs()->with('steps')->lockForUpdate()->get();
            $config = $configs->first() ?? new WorkflowApprovalConfig(['workflow_id' => $workflow->id]);

            $config->fill([
                'stage_id' => $data['stage_id'],
                'approval_type' => 'single',
                'label' => $data['label'],
                'notes_required' => $data['notes_required'],
                'is_active' => $data['is_active'],
            ])->save();

            $workflow->approvalConfigs()->where('id', '!=', $config->id)->delete();
            $config->steps()->delete();
            $config->steps()->create([
                'step_order' => 1,
                'approver_role_key' => 'supervisor_it',
            ]);

            return $config->load('steps');
        });

        Log::info('workflow.approval.updated', [
            'workflow_id' => $workflow->id,
            'approval_config_id' => $config->id,
            'actor_id' => $request->user()->id,
        ]);

        return ApiResponse::success($request, 'Workflow approval configuration updated successfully', [
            'approval' => $this->approvalData($config),
        ]);
    }

    private function approvalData(?WorkflowApprovalConfig $config): ?array
    {
        if (! $config) {
            return null;
        }

        $step = $config->steps->sortBy('step_order')->first();

        return [
            'id' => $config->id,
            'workflow_id' => $config->workflow_id,
            'stage_id' => $config->stage_id,
            'approval_type' => 'single',
            'label' => $config->label,
            'notes_required' => (bool) $config->notes_required,
            'is_active' => (bool) $config->is_active,
            'approver_role_key' => $step?->approver_role_key,
        ];
    }
}
