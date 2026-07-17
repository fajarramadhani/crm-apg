<?php

namespace App\Services;

use App\Enums\TicketStatus;
use App\Events\TicketQaDefectCreated;
use App\Exceptions\InvalidTicketTransition;
use App\Models\Ticket;
use App\Models\TicketQaDefect;
use App\Models\TicketQaTestRun;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final class TicketQaDefectService
{
    public function __construct(private TicketTransitionService $transitions) {}

    public function createDefect(Ticket $ticket, User $actor, array $data): TicketQaDefect
    {
        return DB::transaction(function () use ($ticket, $actor, $data): TicketQaDefect {
            $locked = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);

            if ($locked->qa_assignee_id !== $actor->id) {
                throw new AuthorizationException('Only the assigned QA can create defects.');
            }

            if ($locked->status !== TicketStatus::QaInProgress) {
                throw new InvalidTicketTransition($locked->status->value, 'Defects can only be reported while QA testing is in progress.');
            }

            $run = TicketQaTestRun::lockForUpdate()->findOrFail($data['qa_test_run_id']);
            if ($run->ticket_id !== $locked->id || $run->status !== 'in_progress') {
                throw new InvalidTicketTransition($locked->status->value, 'The associated test run is not in progress or belongs to another ticket.');
            }

            if (isset($data['qa_test_case_id'])) {
                $case = $locked->qaTestCases()->where('is_active', true)->whereKey($data['qa_test_case_id'])->first();
                if (! $case) {
                    throw new InvalidTicketTransition($locked->status->value, 'The test case is invalid or inactive.');
                }
            }

            // Generate unique defect number via row lock on defect sequences
            DB::table('ticket_qa_defect_sequences')->insertOrIgnore([
                'ticket_id' => $locked->id,
                'last_number' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $seq = DB::table('ticket_qa_defect_sequences')->where('ticket_id', $locked->id)->lockForUpdate()->first();
            $next = ((int) $seq->last_number) + 1;
            DB::table('ticket_qa_defect_sequences')->where('ticket_id', $locked->id)->update([
                'last_number' => $next,
                'updated_at' => now(),
            ]);

            $defectNumber = sprintf('DEF-%s-%03d', $locked->ticket_number, $next);

            $defect = $locked->qaDefects()->create([
                'qa_test_run_id' => $run->id,
                'qa_test_case_id' => $data['qa_test_case_id'] ?? null,
                'reported_by' => $actor->id,
                'assigned_to' => $locked->current_assignee_id, // Assigned to the active PIC
                'defect_number' => $defectNumber,
                'title' => $data['title'],
                'description' => $data['description'],
                'severity' => $data['severity'],
                'priority' => $data['priority'],
                'steps_to_reproduce' => $data['steps_to_reproduce'],
                'expected_result' => $data['expected_result'],
                'actual_result' => $data['actual_result'],
                'environment' => $data['environment'] ?? null,
                'status' => 'open',
            ]);

            $this->defectHistory($defect, null, 'open', 'created', $actor);

            $locked->histories()->create([
                'from_status' => $locked->status->value,
                'to_status' => $locked->status->value,
                'action' => 'qa_defect_created',
                'actor_id' => $actor->id,
                'actor_role' => 'qa',
                'metadata' => ['defect_number' => $defectNumber, 'severity' => $defect->severity],
            ]);

            TicketQaDefectCreated::dispatch($defect, $actor);

            return $defect;
        });
    }

    public function updateDefect(Ticket $ticket, TicketQaDefect $defect, User $actor, array $data): TicketQaDefect
    {
        return DB::transaction(function () use ($ticket, $defect, $actor, $data): TicketQaDefect {
            $locked = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);
            if ($locked->qa_assignee_id !== $actor->id) {
                throw new AuthorizationException('Only the assigned QA can edit defects.');
            }

            if ($locked->status !== TicketStatus::QaInProgress) {
                throw new InvalidTicketTransition($locked->status->value, 'Defects can only be edited while QA testing is in progress.');
            }

            $lockedDefect = TicketQaDefect::query()->lockForUpdate()->findOrFail($defect->id);
            if ($lockedDefect->ticket_id !== $locked->id) {
                abort(404, 'Defect does not belong to this ticket.');
            }

            if (! in_array($lockedDefect->status, ['open', 'reopened'], true)) {
                throw new InvalidTicketTransition($locked->status->value, 'Defect is no longer editable.');
            }

            $lockedDefect->update(collect($data)->only([
                'title', 'description', 'severity', 'priority', 'steps_to_reproduce',
                'expected_result', 'actual_result', 'environment',
            ])->all());

            return $lockedDefect->fresh();
        });
    }

    public function startDefect(Ticket $ticket, TicketQaDefect $defect, User $actor): TicketQaDefect
    {
        return DB::transaction(function () use ($ticket, $defect, $actor): TicketQaDefect {
            $locked = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);
            if ($locked->current_assignee_id !== $actor->id) {
                throw new AuthorizationException('Only the assigned PIC can start fixing defects.');
            }

            $lockedDefect = TicketQaDefect::query()->lockForUpdate()->findOrFail($defect->id);
            if ($lockedDefect->ticket_id !== $locked->id) {
                abort(404, 'Defect does not belong to this ticket.');
            }

            if (! in_array($lockedDefect->status, ['open', 'reopened'], true)) {
                throw new InvalidTicketTransition($locked->status->value, 'Defect cannot be started from its current status.');
            }

            $from = $lockedDefect->status;
            $lockedDefect->status = 'in_progress';
            $lockedDefect->save();

            $this->defectHistory($lockedDefect, $from, 'in_progress', 'defect_started', $actor);

            $locked->histories()->create([
                'from_status' => $locked->status->value,
                'to_status' => $locked->status->value,
                'action' => 'qa_defect_started',
                'actor_id' => $actor->id,
                'actor_role' => 'pic',
                'metadata' => ['defect_number' => $lockedDefect->defect_number],
            ]);

            return $lockedDefect->fresh();
        });
    }

    public function resolveDefect(Ticket $ticket, TicketQaDefect $defect, User $actor, string $notes): TicketQaDefect
    {
        return DB::transaction(function () use ($ticket, $defect, $actor, $notes): TicketQaDefect {
            $locked = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);
            if ($locked->current_assignee_id !== $actor->id) {
                throw new AuthorizationException('Only the assigned PIC can resolve defects.');
            }

            $lockedDefect = TicketQaDefect::query()->lockForUpdate()->findOrFail($defect->id);
            if ($lockedDefect->ticket_id !== $locked->id) {
                abort(404, 'Defect does not belong to this ticket.');
            }

            if ($lockedDefect->status !== 'in_progress' && $lockedDefect->status !== 'open') {
                throw new InvalidTicketTransition($locked->status->value, 'Defect must be in progress or open to be resolved.');
            }

            $from = $lockedDefect->status;
            $lockedDefect->status = 'resolved';
            $lockedDefect->resolved_by = $actor->id;
            $lockedDefect->resolved_at = now();
            $lockedDefect->resolution_notes = $notes;
            $lockedDefect->save();

            $this->defectHistory($lockedDefect, $from, 'resolved', 'defect_resolved', $actor, $notes);

            $locked->histories()->create([
                'from_status' => $locked->status->value,
                'to_status' => $locked->status->value,
                'action' => 'qa_defect_resolved',
                'actor_id' => $actor->id,
                'actor_role' => 'pic',
                'metadata' => ['defect_number' => $lockedDefect->defect_number],
            ]);

            return $lockedDefect->fresh();
        });
    }

    public function verifyDefect(Ticket $ticket, TicketQaDefect $defect, User $actor, ?string $notes): TicketQaDefect
    {
        return DB::transaction(function () use ($ticket, $defect, $actor, $notes): TicketQaDefect {
            $locked = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);
            if ($locked->qa_assignee_id !== $actor->id) {
                throw new AuthorizationException('Only the assigned QA can verify defects.');
            }

            $lockedDefect = TicketQaDefect::query()->lockForUpdate()->findOrFail($defect->id);
            if ($lockedDefect->ticket_id !== $locked->id) {
                abort(404, 'Defect does not belong to this ticket.');
            }

            if ($lockedDefect->status !== 'retest') {
                throw new InvalidTicketTransition($locked->status->value, 'Defect must be submitted for retest before verification.');
            }

            $from = $lockedDefect->status;
            $lockedDefect->status = 'verified';
            $lockedDefect->verified_by = $actor->id;
            $lockedDefect->verified_at = now();
            $lockedDefect->save();

            $this->defectHistory($lockedDefect, $from, 'verified', 'defect_verified', $actor, $notes);

            $locked->histories()->create([
                'from_status' => $locked->status->value,
                'to_status' => $locked->status->value,
                'action' => 'qa_defect_verified',
                'actor_id' => $actor->id,
                'actor_role' => 'qa',
                'metadata' => ['defect_number' => $lockedDefect->defect_number],
            ]);

            return $lockedDefect->fresh();
        });
    }

    public function reopenDefect(Ticket $ticket, TicketQaDefect $defect, User $actor, ?string $notes): TicketQaDefect
    {
        return DB::transaction(function () use ($ticket, $defect, $actor, $notes): TicketQaDefect {
            $locked = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);
            if ($locked->qa_assignee_id !== $actor->id) {
                throw new AuthorizationException('Only the assigned QA can reopen defects.');
            }

            $lockedDefect = TicketQaDefect::query()->lockForUpdate()->findOrFail($defect->id);
            if ($lockedDefect->ticket_id !== $locked->id) {
                abort(404, 'Defect does not belong to this ticket.');
            }

            if ($lockedDefect->status !== 'retest') {
                throw new InvalidTicketTransition($locked->status->value, 'Defect must be submitted for retest before reopening.');
            }

            $from = $lockedDefect->status;
            $lockedDefect->status = 'reopened';
            $lockedDefect->save();

            $this->defectHistory($lockedDefect, $from, 'reopened', 'defect_reopened', $actor, $notes);

            $locked->histories()->create([
                'from_status' => $locked->status->value,
                'to_status' => $locked->status->value,
                'action' => 'qa_defect_reopened',
                'actor_id' => $actor->id,
                'actor_role' => 'qa',
                'metadata' => ['defect_number' => $lockedDefect->defect_number],
            ]);

            return $lockedDefect->fresh();
        });
    }

    public function rejectDefect(Ticket $ticket, TicketQaDefect $defect, User $actor, string $notes): TicketQaDefect
    {
        return DB::transaction(function () use ($ticket, $defect, $actor, $notes): TicketQaDefect {
            $locked = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);
            if ($locked->qa_assignee_id !== $actor->id && ! $actor->hasRole('it_lead')) {
                throw new AuthorizationException('Only the assigned QA or IT Lead can reject defects.');
            }

            $lockedDefect = TicketQaDefect::query()->lockForUpdate()->findOrFail($defect->id);
            if ($lockedDefect->ticket_id !== $locked->id) {
                abort(404, 'Defect does not belong to this ticket.');
            }

            if ($lockedDefect->status !== 'open') {
                throw new InvalidTicketTransition($locked->status->value, 'Only open defects can be rejected.');
            }

            $from = $lockedDefect->status;
            $lockedDefect->status = 'rejected';
            $lockedDefect->save();

            $this->defectHistory($lockedDefect, $from, 'rejected', 'defect_rejected', $actor, $notes);

            return $lockedDefect->fresh();
        });
    }

    public function submitRetest(Ticket $ticket, User $actor): Ticket
    {
        return DB::transaction(function () use ($ticket, $actor): Ticket {
            $locked = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);

            // Preconditions check:
            // 1. Current PIC assignee is active.
            if ($locked->current_assignee_id !== $actor->id || ! $locked->assignments()->where('assigned_to', $actor->id)->where('is_current', true)->exists()) {
                throw new AuthorizationException('Only the assigned PIC can submit QA retests.');
            }

            // 2. Progress must be 100%.
            if ($locked->progress_percentage !== 100) {
                throw new InvalidTicketTransition($locked->status->value, 'Progress must be 100% to submit a QA retest.');
            }

            // 3. No active internal test run.
            if ($locked->internalTestRuns()->where('status', 'in_progress')->exists()) {
                throw new InvalidTicketTransition($locked->status->value, 'An active internal test run is currently running.');
            }

            // 4. All defects must be resolved.
            $unresolvedDefects = $locked->qaDefects()->whereIn('status', ['open', 'in_progress', 'reopened'])->exists();
            if ($unresolvedDefects) {
                throw new InvalidTicketTransition($locked->status->value, 'All open, in-progress, or reopened defects must be resolved.');
            }

            // 5. Must have at least one rework worklog since the last QA failure.
            $lastQaFailure = $locked->histories()->where('to_status', 'qa_failed')->latest()->first();
            if (! $lastQaFailure) {
                throw new InvalidTicketTransition($locked->status->value, 'No QA failure has occurred.');
            }

            $hasReworkWorklog = $locked->worklogs()
                ->where('created_at', '>=', $lastQaFailure->created_at)
                ->where('activity_type', 'rework')
                ->exists();
            if (! $hasReworkWorklog) {
                throw new InvalidTicketTransition($locked->status->value, 'At least one rework worklog is required since the last QA failure.');
            }

            // 6. Must have a passed internal test run completed since the last QA failure.
            $hasPassedInternalRun = $locked->internalTestRuns()
                ->where('status', 'passed')
                ->where('completed_at', '>=', $lastQaFailure->created_at)
                ->exists();
            if (! $hasPassedInternalRun) {
                throw new InvalidTicketTransition($locked->status->value, 'A passed internal test run must be executed after the last QA failure.');
            }

            // Advance all resolved defects to retest status
            $resolvedDefects = $locked->qaDefects()->where('status', 'resolved')->get();
            foreach ($resolvedDefects as $defect) {
                $defect->status = 'retest';
                $defect->save();
                $this->defectHistory($defect, 'resolved', 'retest', 'defect_retest_requested', $actor);
            }

            // Transition ticket to qa_retest
            return $this->transitions->submitQaRetest($locked, $actor);
        });
    }

    private function defectHistory(TicketQaDefect $defect, ?string $from, string $to, string $action, User $actor, ?string $notes = null): void
    {
        $defect->histories()->create([
            'from_status' => $from,
            'to_status' => $to,
            'action' => $action,
            'actor_id' => $actor->id,
            'actor_role' => $actor->role?->key ?? 'unknown',
            'notes' => $notes,
        ]);
    }
}
