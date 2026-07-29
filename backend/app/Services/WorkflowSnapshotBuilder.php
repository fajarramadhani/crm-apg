<?php

namespace App\Services;

use App\Models\WorkflowDefinition;

/**
 * Builds an immutable snapshot of a workflow definition.
 *
 * The snapshot is stored per-ticket and remains unchanged even when
 * the workflow is updated or a new version is published.
 */
final class WorkflowSnapshotBuilder
{
    private const MAX_SNAPSHOT_BYTES = 65536; // 64 KB

    public function build(WorkflowDefinition $workflow): array
    {
        $stages = $workflow->stages()
            ->with(['fields'])
            ->get();

        $transitions = $workflow->transitions()
            ->with(['permissions', 'notifications'])
            ->get();

        $approvalConfigs = $workflow->approvalConfigs()
            ->with(['steps'])
            ->get();

        $stagesSnapshot = $stages->map(function ($stage) use ($transitions, $approvalConfigs): array {
            $outgoing = $transitions->where('from_stage_id', $stage->id);

            $transitionsData = $outgoing->map(function ($t): array {
                $permissions = $t->permissions->map(fn ($p): array => [
                    'role_key' => $p->role_key,
                    'permission_code' => $p->permission_code,
                ])->values()->toArray();

                $notifications = $t->notifications->map(fn ($n): array => [
                    'recipient_type' => $n->recipient_type,
                    'channel' => $n->channel,
                    'template_code' => $n->template_code,
                ])->values()->toArray();

                return [
                    'id' => $t->id,
                    'action_key' => $t->action_key,
                    'name' => $t->name,
                    'to_stage_key' => null, // filled below
                    'to_stage_id' => $t->to_stage_id,
                    'requires_notes' => (bool) $t->requires_notes,
                    'permissions' => $permissions,
                    'notifications' => $notifications,
                    'metadata' => $t->metadata ?? [],
                ];
            })->values()->toArray();

            $fields = [];
            if ($stage->fields && $stage->fields->isNotEmpty()) {
                $fields = $stage->fields->map(fn ($f): array => [
                    'field_name' => $f->field_name,
                    'is_required' => (bool) $f->is_required,
                    'is_readonly' => (bool) $f->is_readonly,
                    'is_hidden' => (bool) $f->is_hidden,
                ])->values()->toArray();
            }

            $approval = null;
            $approvalConfig = $approvalConfigs->firstWhere('stage_id', $stage->id);
            if ($approvalConfig) {
                $approval = [
                    'approval_type' => $approvalConfig->approval_type,
                    'label' => $approvalConfig->label,
                    'notes_required' => (bool) $approvalConfig->notes_required,
                    'is_active' => (bool) $approvalConfig->is_active,
                    'steps' => $approvalConfig->steps->map(fn ($s): array => [
                        'step_order' => $s->step_order,
                        'approver_role_key' => $s->approver_role_key,
                    ])->sortBy('step_order')->values()->toArray(),
                ];
            }

            return [
                'id' => $stage->id,
                'stage_key' => $stage->stage_key,
                'name' => $stage->name,
                'description' => $stage->description,
                'order' => $stage->order,
                'stage_type' => $stage->stage_type,
                'is_initial' => (bool) $stage->is_initial,
                'is_terminal' => (bool) $stage->is_terminal,
                'transitions' => $transitionsData,
                'fields' => $fields,
                'approval' => $approval,
                'metadata' => $stage->metadata ?? [],
            ];
        })->values()->toArray();

        // Fill to_stage_key references
        $stageKeyById = collect($stagesSnapshot)->pluck('stage_key', 'id');
        foreach ($stagesSnapshot as &$stage) {
            foreach ($stage['transitions'] as &$t) {
                $t['to_stage_key'] = $stageKeyById[$t['to_stage_id']] ?? null;
            }
        }
        unset($stage, $t);

        $initialStage = collect($stagesSnapshot)->firstWhere('is_initial', true);
        $terminalStages = collect($stagesSnapshot)->where('is_terminal', true)->pluck('stage_key')->values()->toArray();

        $snapshot = [
            'workflow_code' => $workflow->code,
            'workflow_name' => $workflow->name,
            'workflow_version' => $workflow->version,
            'workflow_description' => $workflow->description,
            'initial_stage' => $initialStage ? $initialStage['stage_key'] : null,
            'terminal_stages' => $terminalStages,
            'stages' => $stagesSnapshot,
            'published_at' => $workflow->published_at?->toISOString(),
            'snapshot_built_at' => now()->toISOString(),
        ];

        // Guard: snapshot size
        $encoded = json_encode($snapshot);
        $size = strlen($encoded !== false ? $encoded : '');
        $maxSize = config('crm.workflow_snapshot_max_bytes', self::MAX_SNAPSHOT_BYTES);
        if ($size > $maxSize) {
            throw new \RuntimeException(
                "Workflow snapshot terlalu besar: {$size} bytes (maks {$maxSize} bytes)."
            );
        }

        return $snapshot;
    }

    /**
     * Extract a stage definition from a stored snapshot.
     */
    public function getStageFromSnapshot(array $snapshot, string $stageKey): ?array
    {
        foreach ($snapshot['stages'] ?? [] as $stage) {
            if ($stage['stage_key'] === $stageKey) {
                return $stage;
            }
        }

        return null;
    }

    /**
     * Get all available transitions from a given stage in the snapshot.
     */
    public function getTransitionsFromSnapshot(array $snapshot, string $stageKey): array
    {
        $stage = $this->getStageFromSnapshot($snapshot, $stageKey);

        return $stage['transitions'] ?? [];
    }

    /**
     * Check if the initial stage key matches.
     */
    public function getInitialStage(array $snapshot): ?string
    {
        return $snapshot['initial_stage'] ?? null;
    }

    /**
     * Check if a stage key is terminal in the snapshot.
     */
    public function isTerminal(array $snapshot, string $stageKey): bool
    {
        return in_array($stageKey, $snapshot['terminal_stages'] ?? [], true);
    }
}
