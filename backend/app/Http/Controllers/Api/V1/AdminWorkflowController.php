<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\WorkflowDefinition;
use App\Services\WorkflowActivationService;
use App\Services\WorkflowSnapshotBuilder;
use App\Services\WorkflowValidatorService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

final class AdminWorkflowController extends Controller
{
    public function __construct(
        private WorkflowValidatorService $validator,
        private WorkflowSnapshotBuilder $snapshotBuilder,
        private WorkflowActivationService $activationService,
    ) {}

    // =========================================================================
    // index
    // =========================================================================

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('workflow.view');

        $workflows = WorkflowDefinition::query()
            ->withCount(['stages', 'transitions'])
            ->with('createdBy:id,name')
            ->orderByDesc('updated_at')
            ->paginate(min($request->integer('per_page', 20), 100));

        return ApiResponse::success($request, 'Workflows retrieved successfully', [
            'workflows' => $workflows->items(),
            'meta' => [
                'total' => $workflows->total(),
                'per_page' => $workflows->perPage(),
                'current_page' => $workflows->currentPage(),
                'last_page' => $workflows->lastPage(),
            ],
        ]);
    }

    // =========================================================================
    // store (create draft)
    // =========================================================================

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('workflow.create');

        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', 'regex:/^[a-zA-Z0-9_]+$/', 'unique:workflow_definitions,code'],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'version' => ['integer', 'min:1'],
        ]);

        $workflow = DB::transaction(function () use ($data, $request): WorkflowDefinition {
            $workflow = WorkflowDefinition::query()->create([
                'code' => $data['code'],
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'version' => $data['version'] ?? 1,
                'is_active' => false,
                'config_status' => 'draft',
                'created_by' => $request->user()->id,
            ]);

            Log::info('workflow.created', [
                'workflow_id' => $workflow->id,
                'code' => $workflow->code,
                'version' => $workflow->version,
                'actor_id' => $request->user()->id,
            ]);

            return $workflow;
        });

        return ApiResponse::success($request, 'Workflow created successfully', ['workflow' => $workflow->load('createdBy:id,name')], 201);
    }

    // =========================================================================
    // show
    // =========================================================================

    public function show(Request $request, WorkflowDefinition $workflow): JsonResponse
    {
        Gate::authorize('workflow.view');

        $workflow->load([
            'stages.fields',
            'stages' => fn ($q) => $q->orderBy('order'),
            'transitions.permissions',
            'transitions.notifications',
            'approvalConfigs.steps',
            'createdBy:id,name',
        ]);

        return ApiResponse::success($request, 'Workflow retrieved successfully', [
            'workflow' => array_merge($workflow->toArray(), [
                'is_editable' => $workflow->isDraft(),
                'workflow_type_badge' => $workflow->config_status,
            ]),
        ]);
    }

    // =========================================================================
    // update (draft only)
    // =========================================================================

    public function update(Request $request, WorkflowDefinition $workflow): JsonResponse
    {
        Gate::authorize('workflow.update_draft');

        if (! $workflow->isDraft()) {
            return ApiResponse::error($request, 'Workflow yang sudah dipublikasikan tidak dapat diedit langsung. Buat versi baru.', 'WORKFLOW_NOT_DRAFT', 422);
        }

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $workflow->update($data);

        Log::info('workflow.updated', [
            'workflow_id' => $workflow->id,
            'actor_id' => $request->user()->id,
        ]);

        return ApiResponse::success($request, 'Workflow updated successfully', ['workflow' => $workflow->fresh()]);
    }

    // =========================================================================
    // destroy (draft only, never used by tickets)
    // =========================================================================

    public function destroy(Request $request, WorkflowDefinition $workflow): JsonResponse
    {
        Gate::authorize('workflow.update_draft');

        if (! $workflow->isDraft()) {
            return ApiResponse::error($request, 'Hanya draft workflow yang dapat dihapus.', 'WORKFLOW_NOT_DRAFT', 422);
        }

        // Check no tickets use this workflow
        $ticketCount = Ticket::query()
            ->where('workflow_id', $workflow->id)
            ->count();

        if ($ticketCount > 0) {
            return ApiResponse::error($request, 'Workflow tidak dapat dihapus karena digunakan oleh tiket.', 'WORKFLOW_IN_USE', 422);
        }

        $workflow->delete();

        Log::info('workflow.deleted', [
            'workflow_id' => $workflow->id,
            'actor_id' => $request->user()->id,
        ]);

        return ApiResponse::success($request, 'Workflow deleted successfully', []);
    }

    // =========================================================================
    // validate
    // =========================================================================

    public function validateWorkflow(Request $request, WorkflowDefinition $workflow): JsonResponse
    {
        Gate::authorize('workflow.validate');

        $errors = $this->validator->validate($workflow);

        Log::info('workflow.validated', [
            'workflow_id' => $workflow->id,
            'valid' => empty($errors),
            'error_count' => count($errors),
            'actor_id' => $request->user()->id,
        ]);

        return ApiResponse::success($request, 'Workflow validation completed', [
            'valid' => empty($errors),
            'errors' => $errors,
        ]);
    }

    // =========================================================================
    // publish
    // =========================================================================

    public function publish(Request $request, WorkflowDefinition $workflow): JsonResponse
    {
        Gate::authorize('workflow.publish');

        if (! $workflow->isDraft()) {
            return ApiResponse::error($request, 'Hanya draft workflow yang dapat dipublikasikan.', 'WORKFLOW_NOT_DRAFT', 422);
        }

        // Must pass validation
        $errors = $this->validator->validate($workflow);
        if (! empty($errors)) {
            return ApiResponse::error($request, 'Workflow tidak valid dan tidak dapat dipublikasikan.', 'WORKFLOW_INVALID', 422, [
                'validation_errors' => $errors,
            ]);
        }

        DB::transaction(function () use ($workflow, $request): void {
            $workflow->update([
                'config_status' => 'published',
                'published_at' => now(),
            ]);

            Log::info('workflow.published', [
                'workflow_id' => $workflow->id,
                'version' => $workflow->version,
                'actor_id' => $request->user()->id,
            ]);
        });

        return ApiResponse::success($request, 'Workflow published successfully', ['workflow' => $workflow->fresh()]);
    }

    // =========================================================================
    // activate
    // =========================================================================

    public function activate(Request $request, WorkflowDefinition $workflow): JsonResponse
    {
        Gate::authorize('workflow.activate');

        $activationError = $this->activationService->activate($workflow);

        if (! $activationError) {
            Log::info('workflow.activated', [
                'workflow_id' => $workflow->id,
                'version' => $workflow->version,
                'actor_id' => $request->user()->id,
            ]);
        }

        if ($activationError) {
            return ApiResponse::error($request, $activationError[0], $activationError[1], 422, $activationError[2] ?? []);
        }

        return ApiResponse::success($request, 'Workflow activated successfully', ['workflow' => $workflow->fresh()]);
    }

    // =========================================================================
    // deactivate
    // =========================================================================

    public function deactivate(Request $request, WorkflowDefinition $workflow): JsonResponse
    {
        Gate::authorize('workflow.deactivate');

        if (! $workflow->isActive()) {
            return ApiResponse::error($request, 'Workflow tidak dalam status aktif.', 'WORKFLOW_NOT_ACTIVE', 422);
        }

        DB::transaction(function () use ($workflow, $request): void {
            $workflow->update([
                'config_status' => 'inactive',
                'is_active' => false,
            ]);

            Log::info('workflow.deactivated', [
                'workflow_id' => $workflow->id,
                'actor_id' => $request->user()->id,
            ]);
        });

        return ApiResponse::success($request, 'Workflow deactivated successfully', ['workflow' => $workflow->fresh()]);
    }

    // =========================================================================
    // createVersion
    // =========================================================================

    public function createVersion(Request $request, WorkflowDefinition $workflow): JsonResponse
    {
        Gate::authorize('workflow.version.create');

        if (! $workflow->isPublished()) {
            return ApiResponse::error($request, 'Hanya workflow published yang dapat dibuat versi baru.', 'WORKFLOW_NOT_PUBLISHED', 422);
        }

        $workflow->load([
            'stages.fields',
            'transitions.permissions',
            'transitions.notifications',
            'approvalConfigs.steps',
        ]);

        $newWorkflow = DB::transaction(function () use ($workflow, $request): WorkflowDefinition {
            $maxVersion = WorkflowDefinition::query()
                ->where('code', $workflow->code)
                ->max('version');

            $newVersion = ($maxVersion ?? $workflow->version) + 1;

            // Copy workflow definition
            $newWorkflow = WorkflowDefinition::query()->create([
                'code' => $workflow->code,
                'name' => $workflow->name,
                'description' => $workflow->description,
                'version' => $newVersion,
                'is_active' => false,
                'config_status' => 'draft',
                'created_by' => $request->user()->id,
                'metadata' => array_merge($workflow->metadata ?? [], [
                    'copied_from_version' => $workflow->version,
                    'copied_from_id' => $workflow->id,
                ]),
            ]);

            // Copy stages
            $stageIdMap = [];
            foreach ($workflow->stages as $stage) {
                $newStage = $newWorkflow->stages()->create([
                    'stage_key' => $stage->stage_key,
                    'name' => $stage->name,
                    'description' => $stage->description,
                    'order' => $stage->order,
                    'stage_type' => $stage->stage_type,
                    'is_initial' => $stage->is_initial,
                    'is_terminal' => $stage->is_terminal,
                    'metadata' => $stage->metadata,
                ]);
                $stageIdMap[$stage->id] = $newStage->id;

                // Copy fields
                foreach ($stage->fields as $field) {
                    $newStage->fields()->create([
                        'field_name' => $field->field_name,
                        'is_required' => $field->is_required,
                        'is_readonly' => $field->is_readonly,
                        'is_hidden' => $field->is_hidden,
                    ]);
                }
            }

            // Copy transitions
            foreach ($workflow->transitions as $t) {
                $newTransition = $newWorkflow->transitions()->create([
                    'from_stage_id' => $stageIdMap[$t->from_stage_id] ?? $t->from_stage_id,
                    'to_stage_id' => $stageIdMap[$t->to_stage_id] ?? $t->to_stage_id,
                    'action_key' => $t->action_key,
                    'name' => $t->name,
                    'requires_notes' => $t->requires_notes,
                    'metadata' => $t->metadata,
                ]);

                // Copy permissions
                foreach ($t->permissions as $p) {
                    $newTransition->permissions()->create([
                        'role_key' => $p->role_key,
                        'permission_code' => $p->permission_code,
                    ]);
                }

                // Copy notifications
                foreach ($t->notifications as $n) {
                    $newTransition->notifications()->create([
                        'recipient_type' => $n->recipient_type,
                        'channel' => $n->channel,
                        'template_code' => $n->template_code,
                    ]);
                }
            }

            // Copy approval configs
            foreach ($workflow->approvalConfigs as $config) {
                $newConfig = $newWorkflow->approvalConfigs()->create([
                    'stage_id' => $stageIdMap[$config->stage_id] ?? $config->stage_id,
                    'approval_type' => $config->approval_type,
                    'label' => $config->label,
                    'notes_required' => $config->notes_required,
                    'is_active' => $config->is_active,
                ]);
                foreach ($config->steps as $step) {
                    $newConfig->steps()->create([
                        'step_order' => $step->step_order,
                        'approver_role_key' => $step->approver_role_key,
                    ]);
                }
            }

            Log::info('workflow.version_created', [
                'source_id' => $workflow->id,
                'source_version' => $workflow->version,
                'new_id' => $newWorkflow->id,
                'new_version' => $newVersion,
                'actor_id' => $request->user()->id,
            ]);

            return $newWorkflow;
        });

        return ApiResponse::success($request, 'Workflow version created successfully', ['workflow' => $newWorkflow->load('createdBy:id,name')], 201);
    }

    // =========================================================================
    // preview (snapshot preview)
    // =========================================================================

    public function preview(Request $request, WorkflowDefinition $workflow): JsonResponse
    {
        Gate::authorize('workflow.view');

        try {
            $snapshot = $this->snapshotBuilder->build($workflow);

            return ApiResponse::success($request, 'Workflow preview generated successfully', ['preview' => $snapshot]);
        } catch (\Throwable $e) {
            return ApiResponse::error($request, 'Gagal membuat preview: '.$e->getMessage(), 'PREVIEW_ERROR', 422);
        }
    }
}
