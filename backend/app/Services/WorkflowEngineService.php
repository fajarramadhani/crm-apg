<?php

namespace App\Services;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Notifications\TicketAlertNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Central orchestrator for the dynamic workflow engine.
 *
 * Responsibilities:
 *  - Resolve default active workflow for new tickets.
 *  - Build and validate snapshots.
 *  - Determine available actions for a given actor.
 *  - Authorize and execute transitions atomically.
 *
 * Contract:
 *  - Tiket legacy (workflow_mode = null | 'legacy') → LegacyTransitionHandler.
 *  - Tiket dynamic (workflow_mode = 'dynamic') → this service.
 *  - Snapshot is read from the ticket; never from live config during transition.
 */
final class WorkflowEngineService
{
    public function __construct(
        private WorkflowSnapshotBuilder $snapshotBuilder,
    ) {}

    // =========================================================================
    // Resolve workflow for new tickets
    // =========================================================================

    /**
     * Find the default active workflow. Returns null when feature flag is off
     * or no active workflow exists.
     */
    public function resolveWorkflowForNewTicket(): ?WorkflowDefinition
    {
        if (! config('crm.dynamic_workflow_enabled', false)) {
            return null;
        }

        return WorkflowDefinition::query()
            ->where('config_status', 'active')
            ->latest('published_at')
            ->first();
    }

    /**
     * Build and store workflow data on a new ticket (within an open transaction).
     *
     * @throws \RuntimeException when snapshot cannot be built.
     */
    public function attachWorkflowToTicket(Ticket $ticket, WorkflowDefinition $workflow): void
    {
        $snapshot = $this->snapshotBuilder->build($workflow);

        $initialStage = $snapshot['initial_stage']
            ?? throw new \RuntimeException('Workflow snapshot tidak memiliki initial stage.');

        $ticket->forceFill([
            'workflow_id' => $workflow->id,
            'workflow_version' => $workflow->version,
            'workflow_snapshot' => $snapshot,
            'workflow_mode' => 'dynamic',
            'current_workflow_stage' => $initialStage,
            'workflow_stage_entered_at' => now(),
        ])->save();

        // Status column: map initial stage key to legacy TicketStatus value.
        // submitted → TicketStatus::Submitted
        $status = $this->mapStageToLegacyStatus($initialStage);
        if ($status) {
            $ticket->status = $status;
            $ticket->save();
        }
    }

    // =========================================================================
    // Runtime helpers
    // =========================================================================

    /**
     * Get the current workflow stage key from a dynamic ticket.
     */
    public function getCurrentStage(Ticket $ticket): ?string
    {
        $this->assertDynamicTicket($ticket);

        return $ticket->current_workflow_stage;
    }

    /**
     * List available actions for a given actor on a dynamic ticket.
     *
     * @return array<int, array{code: string, label: string, to_stage: string, requires_notes: bool, required_fields: list<string>}>
     */
    public function availableActions(Ticket $ticket, User $actor): array
    {
        $this->assertDynamicTicket($ticket);

        $snapshot = $ticket->workflow_snapshot;
        $stageKey = $ticket->current_workflow_stage;

        if (! $snapshot || ! $stageKey) {
            return [];
        }

        $transitions = $this->snapshotBuilder->getTransitionsFromSnapshot($snapshot, $stageKey);
        $result = [];

        foreach ($transitions as $t) {
            if ($this->actorCanExecuteTransition($t, $ticket, $actor)) {
                $result[] = [
                    'code' => $t['action_key'],
                    'label' => $t['name'],
                    'to_stage' => $t['to_stage_key'],
                    'requires_notes' => $t['requires_notes'],
                    'required_fields' => $this->getRequiredFieldsForTransition($t),
                ];
            }
        }

        return $result;
    }

    /**
     * Check if an actor can execute a specific transition on a ticket.
     */
    public function canTransition(Ticket $ticket, User $actor, string $actionKey): bool
    {
        if ($ticket->workflow_mode !== 'dynamic') {
            return false;
        }

        $snapshot = $ticket->workflow_snapshot;
        $stageKey = $ticket->current_workflow_stage;
        if (! $snapshot || ! $stageKey) {
            return false;
        }

        $transitions = $this->snapshotBuilder->getTransitionsFromSnapshot($snapshot, $stageKey);
        $transition = collect($transitions)->firstWhere('action_key', $actionKey);
        if (! $transition) {
            return false;
        }

        return $this->actorCanExecuteTransition($transition, $ticket, $actor);
    }

    /**
     * Validate the payload for a given transition.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string> Validation errors.
     */
    public function validateTransitionPayload(Ticket $ticket, string $actionKey, array $payload): array
    {
        $snapshot = $ticket->workflow_snapshot;
        $stageKey = $ticket->current_workflow_stage;
        if (! $snapshot || ! $stageKey) {
            return ['Snapshot tiket tidak tersedia.'];
        }

        $transitions = $this->snapshotBuilder->getTransitionsFromSnapshot($snapshot, $stageKey);
        $transition = collect($transitions)->firstWhere('action_key', $actionKey);
        if (! $transition) {
            return ["Aksi '{$actionKey}' tidak tersedia pada stage ini."];
        }

        $errors = [];

        $approvalRequiresNotes = $actionKey === 'approve'
            && (bool) data_get(
                $this->snapshotBuilder->getStageFromSnapshot($snapshot, $stageKey),
                'approval.is_active',
                false
            )
            && (bool) data_get(
                $this->snapshotBuilder->getStageFromSnapshot($snapshot, $stageKey),
                'approval.notes_required',
                false
            );

        if (($transition['requires_notes'] || $approvalRequiresNotes) && empty(trim((string) ($payload['notes'] ?? '')))) {
            $errors[] = "Aksi '{$actionKey}' memerlukan catatan.";
        }

        $requiredFields = $this->getRequiredFieldsForTransition($transition);
        foreach ($requiredFields as $field) {
            if (empty($payload[$field])) {
                $errors[] = "Field '{$field}' wajib diisi untuk aksi ini.";
            }
        }

        return $errors;
    }

    // =========================================================================
    // Execute transition (atomic)
    // =========================================================================

    /**
     * Execute a workflow transition atomically.
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws ConflictHttpException When the ticket stage has changed since the request was formed.
     * @throws HttpException When permission or validation fails.
     */
    public function executeTransition(
        Ticket $ticket,
        User $actor,
        string $actionKey,
        array $payload = [],
        ?string $expectedCurrentStage = null
    ): Ticket {
        return DB::transaction(function () use ($ticket, $actor, $actionKey, $payload, $expectedCurrentStage): Ticket {
            // Lock ticket row
            $ticket = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);

            // Ensure ticket is dynamic
            $this->assertDynamicTicket($ticket);

            // Concurrency guard
            if ($expectedCurrentStage !== null && $ticket->current_workflow_stage !== $expectedCurrentStage) {
                throw new ConflictHttpException(
                    'Status tiket telah berubah. Muat ulang halaman dan coba lagi.'
                );
            }

            $snapshot = $ticket->workflow_snapshot;
            $stageKey = $ticket->current_workflow_stage;

            // Find the transition in snapshot
            $transitions = $this->snapshotBuilder->getTransitionsFromSnapshot($snapshot, $stageKey);
            $transition = collect($transitions)->firstWhere('action_key', $actionKey);

            if (! $transition) {
                throw new HttpException(422, "Aksi '{$actionKey}' tidak tersedia pada stage '{$stageKey}'.");
            }

            // Authorization
            if (! $this->actorCanExecuteTransition($transition, $ticket, $actor)) {
                throw new HttpException(403, 'Anda tidak memiliki izin untuk menjalankan aksi ini.');
            }

            // Payload validation
            $errors = $this->validateTransitionPayload($ticket, $actionKey, $payload);
            if ($actionKey === 'approve' && $actor->hasRole(['supervisor_it', 'supervisor']) && $ticket->hasActivePrimaryPic($actor) && trim((string) ($payload['notes'] ?? '')) === '') {
                $errors[] = 'Catatan wajib diisi untuk self-approval.';
            }
            if (! empty($errors)) {
                throw new HttpException(422, implode(', ', $errors));
            }

            $oldStage = $stageKey;
            $newStage = $transition['to_stage_key'];

            // Execute domain-specific side effects
            $this->runDomainAction($ticket, $actor, $actionKey, $oldStage, $newStage, $payload);

            // Handle on_hold: save previous stage
            if ($actionKey === 'put_on_hold') {
                $ticket->workflow_previous_stage = $oldStage;
            }

            // Handle resume from on_hold: restore previous stage
            if ($actionKey === 'resume' && $oldStage === 'on_hold') {
                $previousStage = $ticket->workflow_previous_stage;
                if ($previousStage) {
                    $newStage = $previousStage;
                    $ticket->workflow_previous_stage = null;
                }
            }

            // Update stage and legacy status
            $ticket->current_workflow_stage = $newStage;
            $ticket->workflow_stage_entered_at = now();

            $legacyStatus = $this->mapStageToLegacyStatus($newStage);
            if ($legacyStatus) {
                $ticket->status = $legacyStatus;
            }

            $ticket->save();

            // Record status history
            $roleKey = $actor->role?->key ?? 'unknown';
            $ticket->histories()->create([
                'from_status' => $oldStage,
                'to_status' => $newStage,
                'action' => $actionKey,
                'actor_id' => $actor->id,
                'actor_role' => $roleKey,
                'metadata' => $this->buildAuditMetadata($ticket, $actor, $actionKey, $payload),
            ]);

            Log::info('workflow.transition', [
                'ticket_id' => $ticket->id,
                'action' => $actionKey,
                'from_stage' => $oldStage,
                'to_stage' => $newStage,
                'actor_id' => $actor->id,
                'actor_role' => $roleKey,
            ]);

            $ticketId = $ticket->id;
            $notificationRules = $transition['notifications'] ?? [];
            $notificationContext = [
                'action_key' => $actionKey,
                'transition_name' => $transition['name'],
                'from_stage' => $oldStage,
                'to_stage' => $newStage,
                'actor_id' => $actor->id,
                'transition_metadata' => $transition['metadata'] ?? [],
            ];

            DB::afterCommit(function () use ($ticketId, $notificationRules, $notificationContext): void {
                try {
                    $committedTicket = Ticket::query()->find($ticketId);
                    if (! $committedTicket) {
                        Log::warning('workflow.transition.notification_ticket_missing', ['ticket_id' => $ticketId]);

                        return;
                    }

                    $this->dispatchTransitionNotifications($committedTicket, $notificationRules, $notificationContext);
                } catch (\Throwable $exception) {
                    Log::error('workflow.transition.notification_failed', [
                        'ticket_id' => $ticketId,
                        'action' => $notificationContext['action_key'],
                        'exception' => $exception->getMessage(),
                    ]);
                }
            });

            return $ticket->fresh();
        });
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    /**
     * Dispatch only the fixed, database-backed recipient rules stored in the ticket snapshot.
     *
     * @param  array<int, mixed>  $rules
     * @param  array<string, mixed>  $context
     */
    private function dispatchTransitionNotifications(Ticket $ticket, array $rules, array $context): void
    {
        $recipients = collect();

        foreach ($rules as $rule) {
            if (! is_array($rule)) {
                Log::warning('workflow.transition.notification_rule_skipped', [
                    'ticket_id' => $ticket->id,
                    'reason' => 'invalid_rule',
                ]);

                continue;
            }

            if (($rule['channel'] ?? 'database') !== 'database') {
                Log::warning('workflow.transition.notification_rule_skipped', [
                    'ticket_id' => $ticket->id,
                    'recipient_type' => $rule['recipient_type'] ?? null,
                    'reason' => 'unsupported_channel',
                ]);

                continue;
            }

            $recipientType = $rule['recipient_type'] ?? null;
            $resolved = match ($recipientType) {
                'requester' => User::query()->whereKey($ticket->requester_id)->where('is_active', true)->get(),
                'primary_pic' => $this->assignedRecipients($ticket, 'primary'),
                'secondary_pics' => $this->assignedRecipients($ticket, 'secondary'),
                'supervisor_it' => $this->roleRecipients('supervisor_it'),
                'specific_role' => $this->specificRoleRecipients($ticket, $rule, $context),
                default => null,
            };

            if ($resolved === null) {
                Log::warning('workflow.transition.notification_rule_skipped', [
                    'ticket_id' => $ticket->id,
                    'recipient_type' => $recipientType,
                    'reason' => 'unsupported_recipient_type',
                ]);

                continue;
            }

            $recipients = $recipients->merge($resolved);
        }

        $recipients = $recipients
            ->reject(fn (User $user): bool => $this->isForbiddenNotificationRole($user->role?->key))
            ->unique('id');

        foreach ($recipients as $recipient) {
            try {
                $recipient->notify(new TicketAlertNotification([
                    'type' => 'workflow_transition',
                    'severity' => 'info',
                    'title' => $context['transition_name'],
                    'message' => "Ticket {$ticket->ticket_number} transitioned from {$context['from_stage']} to {$context['to_stage']}.",
                    'ticket_id' => $ticket->id,
                    'ticket_number' => $ticket->ticket_number,
                    'action_url' => "/tickets/{$ticket->id}",
                    'actor_id' => $context['actor_id'],
                    'metadata' => [
                        'action_key' => $context['action_key'],
                        'from_stage' => $context['from_stage'],
                        'to_stage' => $context['to_stage'],
                    ],
                ]));
            } catch (\Throwable $exception) {
                Log::error('workflow.transition.notification_failed', [
                    'ticket_id' => $ticket->id,
                    'action' => $context['action_key'],
                    'recipient_id' => $recipient->id,
                    'exception' => $exception->getMessage(),
                ]);
            }
        }
    }

    private function assignedRecipients(Ticket $ticket, string $assignmentType): Collection
    {
        return User::query()
            ->where('is_active', true)
            ->whereHas('ticketAssignments', fn ($query) => $query
                ->where('ticket_id', $ticket->id)
                ->where('assignment_type', $assignmentType)
                ->where('is_current', true))
            ->get();
    }

    private function roleRecipients(string $roleKey): Collection
    {
        return User::query()
            ->where('is_active', true)
            ->whereHas('role', fn ($query) => $query->where('key', $roleKey))
            ->get();
    }

    /**
     * specific_role is intentionally inert unless a scalar role key is embedded in safe snapshot config.
     *
     * @param  array<string, mixed>  $rule
     * @param  array<string, mixed>  $context
     */
    private function specificRoleRecipients(Ticket $ticket, array $rule, array $context): Collection
    {
        $roleKey = data_get($rule, 'metadata.role_key')
            ?? data_get($rule, 'config.role_key')
            ?? data_get($context, 'transition_metadata.notification.role_key')
            ?? data_get($context, 'transition_metadata.notification_config.role_key');

        if (! is_string($roleKey)
            || preg_match('/^[a-z][a-z0-9_]{0,49}$/', $roleKey) !== 1
            || $this->isForbiddenNotificationRole($roleKey)) {
            Log::warning('workflow.transition.notification_rule_skipped', [
                'ticket_id' => $ticket->id,
                'recipient_type' => 'specific_role',
                'reason' => 'missing_or_unsafe_role_key',
            ]);

            return collect();
        }

        return $this->roleRecipients($roleKey);
    }

    private function isForbiddenNotificationRole(?string $roleKey): bool
    {
        return is_string($roleKey) && preg_match('/^(qa|uat|manager)(_|$)/', $roleKey) === 1;
    }

    private function assertDynamicTicket(Ticket $ticket): void
    {
        if ($ticket->workflow_mode !== 'dynamic') {
            throw new HttpException(422, 'Tiket ini bukan tiket dynamic workflow.');
        }
    }

    /**
     * Check if actor is permitted by snapshot transition permissions.
     */
    private function actorCanExecuteTransition(array $transition, Ticket $ticket, User $actor): bool
    {
        $permissions = $transition['permissions'] ?? [];
        if (empty($permissions)) {
            return false;
        }

        $actorRole = $actor->role?->key;

        foreach ($permissions as $perm) {
            $roleKey = $perm['role_key'] ?? null;

            // Role-based check
            if ($roleKey && $actorRole === $roleKey) {
                // Additional assignment checks
                if (! $this->checkAssignmentRule($transition['action_key'], $ticket, $actor, $roleKey)) {
                    continue;
                }

                return true;
            }

            // Permission-code check
            if (isset($perm['permission_code']) && $actor->hasPermission($perm['permission_code'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Additional actor-specific business rules beyond role matching.
     */
    private function checkAssignmentRule(string $actionKey, Ticket $ticket, User $actor, string $roleKey): bool
    {
        if ($roleKey === 'requester') {
            return $ticket->requester_id === $actor->id;
        }

        if (in_array($roleKey, ['pic_it_support', 'pic_it_develop', 'pic'], true)) {
            $hasActiveAssignment = $ticket->assignments()
                ->where('assigned_to', $actor->id)
                ->where('is_current', true)
                ->exists();

            if (! $hasActiveAssignment) {
                return false;
            }
        }

        // Actions that require primary PIC assignment
        $primaryPicActions = [
            'start_work',
            'submit_for_approval',
        ];

        if (in_array($actionKey, $primaryPicActions, true)) {
            // Supervisor acting as primary PIC is OK
            if ($roleKey === 'supervisor_it') {
                return $this->isSupervisorActingAsPrimaryPic($ticket, $actor);
            }
            // PIC must be primary
            if (in_array($roleKey, ['pic_it_support', 'pic_it_develop', 'pic'], true)) {
                return $this->isPrimaryPic($ticket, $actor);
            }
        }

        return true;
    }

    private function isPrimaryPic(Ticket $ticket, User $actor): bool
    {
        return $ticket->assignments()
            ->where('assigned_to', $actor->id)
            ->where('assignment_type', 'primary')
            ->where('is_current', true)
            ->exists();
    }

    private function isSupervisorActingAsPrimaryPic(Ticket $ticket, User $actor): bool
    {
        return $ticket->assignments()
            ->where('assigned_to', $actor->id)
            ->where('is_current', true)
            ->exists();
    }

    /**
     * Map a dynamic stage_key to a legacy TicketStatus enum value.
     * This keeps the legacy `status` column in sync for backward-compatible queries.
     */
    private function mapStageToLegacyStatus(string $stageKey): ?TicketStatus
    {
        return match ($stageKey) {
            'submitted' => TicketStatus::Submitted,
            'under_analysis' => TicketStatus::UnderAnalysis,
            'assigned' => TicketStatus::Assigned,
            'in_progress' => TicketStatus::InProgress,
            'pending_approval' => TicketStatus::PendingApproval,
            'done' => TicketStatus::Done,
            'need_info' => TicketStatus::NeedInfo,
            'waiting_external' => TicketStatus::WaitingExternal,
            'need_revision' => TicketStatus::NeedRevision,
            'on_hold' => TicketStatus::OnHold,
            'rejected' => TicketStatus::Rejected,
            'cancelled' => TicketStatus::Cancelled,
            'reopened' => TicketStatus::Reopened,
            default => null,
        };
    }

    /**
     * Domain-specific side effects for each action.
     * These run inside the transaction before the stage update.
     *
     * @param  array<string, mixed>  $payload
     */
    private function runDomainAction(
        Ticket $ticket,
        User $actor,
        string $actionKey,
        string $oldStage,
        string $newStage,
        array $payload
    ): void {
        match ($actionKey) {
            'start_work' => $this->handleStartWork($ticket, $actor),
            'submit_for_approval' => $this->handleSubmitForApproval($ticket, $actor, $payload),
            'approve' => $this->handleApprove($ticket, $actor, $payload),
            'reject' => $this->handleReject($ticket, $actor, $payload),
            'cancel' => $this->handleCancel($ticket, $actor, $payload),
            'reopen' => $this->handleReopen($ticket, $actor),
            default => null,
        };
    }

    private function handleStartWork(Ticket $ticket, User $actor): void
    {
        // Record work start time if not already set
        if (! $ticket->development_started_at) {
            $ticket->development_started_at = now();
        }
    }

    private function handleSubmitForApproval(Ticket $ticket, User $actor, array $payload): void
    {
        if (! $ticket->approval_requested_at) {
            $ticket->approval_requested_at = now();
        }
    }

    private function handleApprove(Ticket $ticket, User $actor, array $payload): void
    {
        $ticket->approval_completed_at = now();
        $ticket->closed_at = now();
        $ticket->closed_by = $actor->id;
    }

    private function handleReject(Ticket $ticket, User $actor, array $payload): void
    {
        $ticket->rejected_at = now();
    }

    private function handleCancel(Ticket $ticket, User $actor, array $payload): void
    {
        $ticket->closed_at = now();
        $ticket->closed_by = $actor->id;
    }

    private function handleReopen(Ticket $ticket, User $actor): void
    {
        // Clear close timestamps for reopened tickets
        $ticket->closed_at = null;
        $ticket->closed_by = null;
    }

    /** @param array<string, mixed> $payload */
    private function buildAuditMetadata(Ticket $ticket, User $actor, string $actionKey, array $payload): array
    {
        $safe = [];
        $allowedPayloadKeys = ['notes', 'reason', 'result_summary', 'external_party', 'external_ref'];
        foreach ($allowedPayloadKeys as $key) {
            if (isset($payload[$key])) {
                $safe[$key] = is_string($payload[$key]) ? mb_substr($payload[$key], 0, 500) : $payload[$key];
            }
        }

        $metadata = [
            'action_key' => $actionKey,
            'actor_id' => $actor->id,
            'actor_role' => $actor->role?->key,
            'payload' => $safe,
            'executed_at' => now()->toISOString(),
        ];

        if ($actionKey === 'approve' && $actor->hasRole(['supervisor_it', 'supervisor'])) {
            $metadata['self_approval'] = $ticket->hasActivePrimaryPic($actor);
        }

        return $metadata;
    }

    /** @return list<string> */
    private function getRequiredFieldsForTransition(array $transition): array
    {
        // submit_for_approval requires result_summary
        if ($transition['action_key'] === 'submit_for_approval') {
            return ['result_summary'];
        }

        return [];
    }
}
