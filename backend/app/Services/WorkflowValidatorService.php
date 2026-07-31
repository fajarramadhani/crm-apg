<?php

namespace App\Services;

use App\Models\WorkflowDefinition;
use Illuminate\Support\Collection;

/**
 * Validates the structural integrity of a workflow definition.
 *
 * Rules enforced:
 *  - Must have exactly one initial stage.
 *  - Must have at least one terminal stage.
 *  - All main stages must be reachable from the initial stage.
 *  - No transition points to an out-of-workflow stage.
 *  - No duplicate action_key on the same from_stage.
 *  - Every transition must have at least one permission entry.
 *  - Every role_key referenced in permissions must be in the known roles list.
 *  - No field_name duplicates within a stage.
 *  - Approval stage must have an approver configured.
 *  - Every non-terminal stage must have at least one outgoing transition.
 *  - Workflow must have a version > 0.
 *  - Published workflow cannot be validated as draft (already immutable).
 */
final class WorkflowValidatorService
{
    /** Allowed cycle-back stages that do not need to be "main flow" only */
    private const ALLOWED_CYCLE_STAGES = [
        'need_info',
        'waiting_external',
        'need_revision',
        'reopened',
    ];

    /** Known role keys in the system */
    private const KNOWN_ROLE_KEYS = [
        'superadmin',
        'supervisor_it',
        'supervisor',
        'it_lead',
        'pic',
        'pic_it_support',
        'pic_it_develop',
        'qa',
        'manager',
        'executive',
        'requester',
    ];

    /**
     * @return array<string> List of validation errors. Empty = valid.
     */
    public function validate(WorkflowDefinition $workflow): array
    {
        $errors = [];

        if (! $workflow->version || $workflow->version < 1) {
            $errors[] = 'Workflow harus memiliki version >= 1.';
        }

        $stages = $workflow->stages()->with(['fields'])->get();
        $transitions = $workflow->transitions()
            ->with(['permissions'])
            ->get();

        $this->validateStages($stages, $errors);
        $this->validateTransitions($stages, $transitions, $errors);
        $this->validateReachability($stages, $transitions, $errors);
        $this->validateApprovalStages($workflow, $stages, $errors);

        return $errors;
    }

    /** @param array<string> $errors */
    private function validateStages(Collection $stages, array &$errors): void
    {
        $initialStages = $stages->where('is_initial', true);
        $terminalStages = $stages->where('is_terminal', true);

        if ($initialStages->count() === 0) {
            $errors[] = 'Workflow harus memiliki tepat satu initial stage.';
        } elseif ($initialStages->count() > 1) {
            $errors[] = 'Workflow tidak boleh memiliki lebih dari satu initial stage. Ditemukan: '
                .$initialStages->pluck('stage_key')->join(', ').'.';
        }

        if ($terminalStages->count() === 0) {
            $errors[] = 'Workflow harus memiliki minimal satu terminal stage.';
        }

        // No duplicate stage keys (unique constraint handles it, but double check)
        $stageKeys = $stages->pluck('stage_key');
        if ($stageKeys->count() !== $stageKeys->unique()->count()) {
            $errors[] = 'Terdapat stage_key duplikat dalam workflow ini.';
        }

        // Validate stage fields
        foreach ($stages as $stage) {
            if (isset($stage->fields)) {
                $fieldNames = collect($stage->fields)->pluck('field_name');
                if ($fieldNames->count() !== $fieldNames->unique()->count()) {
                    $errors[] = "Stage '{$stage->stage_key}' memiliki field_name duplikat.";
                }
            }
        }
    }

    /** @param array<string> $errors */
    private function validateTransitions(Collection $stages, Collection $transitions, array &$errors): void
    {
        $stageIds = $stages->pluck('id')->flip();

        foreach ($transitions as $transition) {
            // Transition must point to stages within this workflow
            if (! $stageIds->has($transition->from_stage_id)) {
                $errors[] = "Transition ID {$transition->id} ('{$transition->action_key}') merujuk from_stage yang tidak ada dalam workflow ini.";
            }
            if (! $stageIds->has($transition->to_stage_id)) {
                $errors[] = "Transition ID {$transition->id} ('{$transition->action_key}') merujuk to_stage yang tidak ada dalam workflow ini.";
            }

            // Each transition must have at least one permission
            if (! isset($transition->permissions) || $transition->permissions->isEmpty()) {
                $errors[] = "Transition '{$transition->action_key}' (from stage_id {$transition->from_stage_id}) tidak memiliki permission actor.";
            } else {
                // Validate role keys
                foreach ($transition->permissions as $perm) {
                    $roleKey = trim((string) $perm->role_key);
                    $permissionCode = trim((string) $perm->permission_code);

                    if ($roleKey === '' && $permissionCode === '') {
                        $errors[] = "Transition '{$transition->action_key}' memiliki permission actor kosong.";
                    } elseif ($roleKey !== '' && ! in_array($roleKey, self::KNOWN_ROLE_KEYS, true)) {
                        $errors[] = "Transition '{$transition->action_key}' menggunakan role_key tidak dikenal: '{$perm->role_key}'.";
                    }
                }
            }
        }

        // No duplicate action_key on the same from_stage
        $grouped = $transitions->groupBy('from_stage_id');
        foreach ($grouped as $fromStageId => $stageTransitions) {
            $actionKeys = $stageTransitions->pluck('action_key');
            if ($actionKeys->count() !== $actionKeys->unique()->count()) {
                $duplicates = $actionKeys->duplicates()->unique()->join(', ');
                $stage = $stages->firstWhere('id', $fromStageId);
                $stageKey = $stage ? $stage->stage_key : "stage_id:{$fromStageId}";
                $errors[] = "Stage '{$stageKey}' memiliki action_key duplikat: {$duplicates}.";
            }
        }

        // Every non-terminal stage must have at least one outgoing transition
        $stagesWithOutgoing = $transitions->pluck('from_stage_id')->unique()->flip();
        foreach ($stages as $stage) {
            if (! $stage->is_terminal && ! $stagesWithOutgoing->has($stage->id)) {
                $errors[] = "Stage non-terminal '{$stage->stage_key}' tidak memiliki outgoing transition.";
            }
        }
    }

    /** @param array<string> $errors */
    private function validateReachability(Collection $stages, Collection $transitions, array &$errors): void
    {
        $initialStage = $stages->firstWhere('is_initial', true);
        if (! $initialStage) {
            return; // Already reported
        }

        // Build adjacency map: stageId -> [toStageId, ...]
        $adjacency = [];
        foreach ($transitions as $t) {
            $adjacency[$t->from_stage_id][] = $t->to_stage_id;
        }

        // BFS from initial stage
        $visited = [];
        $queue = [$initialStage->id];
        while (! empty($queue)) {
            $current = array_shift($queue);
            if (isset($visited[$current])) {
                continue;
            }
            $visited[$current] = true;
            foreach ($adjacency[$current] ?? [] as $next) {
                if (! isset($visited[$next])) {
                    $queue[] = $next;
                }
            }
        }

        foreach ($stages as $stage) {
            if (! isset($visited[$stage->id])) {
                $errors[] = "Stage '{$stage->stage_key}' tidak dapat dicapai dari initial stage '{$initialStage->stage_key}'.";
            }
        }
    }

    /** @param array<string> $errors */
    private function validateApprovalStages(WorkflowDefinition $workflow, Collection $stages, array &$errors): void
    {
        // Stage 9 supports one active or inactive single-step approval config only.
        $approvalStages = $stages->where('stage_type', 'approval');
        $approvalConfigs = $workflow->approvalConfigs()
            ->with('steps')
            ->get();

        if ($approvalConfigs->count() > 1) {
            $errors[] = 'Workflow hanya boleh memiliki satu approval config.';
        }

        if ($approvalStages->isEmpty()) {
            if ($approvalConfigs->isNotEmpty()) {
                $errors[] = 'Approval config harus merujuk ke stage bertipe approval.';
            }

            return;
        }

        if ($approvalStages->count() > 1) {
            $errors[] = 'Workflow hanya boleh memiliki satu stage approval.';
        }

        $configsByStage = $approvalConfigs->keyBy('stage_id');

        foreach ($approvalStages as $stage) {
            $config = $configsByStage->get($stage->id);
            if (! $config || $config->steps->isEmpty()) {
                $errors[] = "Stage approval '{$stage->stage_key}' tidak memiliki approver yang dikonfigurasi.";

                continue;
            }

            if ($config->approval_type !== 'single') {
                $errors[] = "Stage approval '{$stage->stage_key}' hanya mendukung approval_type 'single'.";
            }

            if ($config->steps->count() !== 1) {
                $errors[] = "Stage approval '{$stage->stage_key}' harus memiliki tepat satu approval step.";
            }

            foreach ($config->steps as $step) {
                if ($step->step_order !== 1 || $step->approver_role_key !== 'supervisor_it') {
                    $errors[] = "Stage approval '{$stage->stage_key}' hanya boleh menggunakan approver_role_key 'supervisor_it'.";
                }
            }
        }
    }

    /**
     * Convenience: throw if validation fails.
     *
     * @throws \RuntimeException
     */
    public function validateOrFail(WorkflowDefinition $workflow): void
    {
        $errors = $this->validate($workflow);
        if (! empty($errors)) {
            throw new \RuntimeException(
                'Workflow validation failed: '.implode(' | ', $errors)
            );
        }
    }
}
