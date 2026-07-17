<?php

namespace App\Services;

use App\Enums\TicketStatus;
use App\Exceptions\InvalidTicketTransition;
use App\Models\Ticket;
use App\Models\TicketUatFinding;
use App\Models\TicketUatRun;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final class TicketUatFindingService
{
    public function __construct(private TicketTransitionService $transitions) {}

    public function createFinding(Ticket $ticket, User $actor, array $data): TicketUatFinding
    {
        return DB::transaction(function () use ($ticket, $actor, $data): TicketUatFinding {
            $locked = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);

            if ($locked->uat_assignee_id !== $actor->id) {
                throw new AuthorizationException('Only the assigned UAT tester can report findings.');
            }

            if ($locked->status !== TicketStatus::UatInProgress) {
                throw new InvalidTicketTransition($locked->status->value, 'Findings can only be reported while UAT testing is in progress.');
            }

            $run = TicketUatRun::lockForUpdate()->findOrFail($data['uat_run_id']);
            if ($run->ticket_id !== $locked->id || $run->status !== 'in_progress' || $run->requester_id !== $actor->id) {
                throw new InvalidTicketTransition($locked->status->value, 'The associated UAT run is not in progress.');
            }

            if (isset($data['uat_scenario_id'])) {
                $scenario = $locked->uatScenarios()->where('is_active', true)->whereKey($data['uat_scenario_id'])->first();
                if (! $scenario) {
                    throw new InvalidTicketTransition($locked->status->value, 'The scenario is invalid or inactive.');
                }
                $result = $run->results()->where('uat_scenario_id', $scenario->id)->first();
                if (! $result || ! in_array($result->status, ['rejected', 'blocked'], true)) {
                    throw new InvalidTicketTransition($locked->status->value, 'A finding must be linked to a rejected or blocked result.');
                }
            }

            $picAssignment = $locked->assignments()
                ->where('assigned_to', $locked->current_assignee_id)
                ->where('is_current', true)
                ->exists();
            if (! $picAssignment) {
                throw new InvalidTicketTransition($locked->status->value, 'The ticket must have an active PIC assignment for UAT findings.');
            }

            // Generate unique finding number
            DB::table('ticket_uat_finding_sequences')->insertOrIgnore([
                'ticket_id' => $locked->id,
                'last_number' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $seq = DB::table('ticket_uat_finding_sequences')->where('ticket_id', $locked->id)->lockForUpdate()->first();
            $next = ((int) $seq->last_number) + 1;
            DB::table('ticket_uat_finding_sequences')->where('ticket_id', $locked->id)->update([
                'last_number' => $next,
                'updated_at' => now(),
            ]);

            $findingNumber = sprintf('FND-%s-%03d', $locked->ticket_number, $next);

            $finding = $locked->uatFindings()->create([
                'uat_run_id' => $run->id,
                'uat_scenario_id' => $data['uat_scenario_id'],
                'reported_by' => $actor->id,
                'assigned_to' => $locked->assignments()->where('assigned_to', $locked->current_assignee_id)->where('is_current', true)->exists()
                    ? $locked->current_assignee_id
                    : null,
                'finding_number' => $findingNumber,
                'title' => $data['title'],
                'description' => $data['description'],
                'business_impact' => $data['business_impact'] ?? '',
                'severity' => $data['severity'],
                'status' => 'open',
            ]);

            $this->findingHistory($finding, null, 'open', 'created', $actor);

            $locked->histories()->create([
                'from_status' => $locked->status->value,
                'to_status' => $locked->status->value,
                'action' => 'uat_finding_created',
                'actor_id' => $actor->id,
                'actor_role' => 'requester',
                'metadata' => ['finding_number' => $findingNumber, 'severity' => $finding->severity],
            ]);

            return $finding;
        });
    }

    public function startFinding(Ticket $ticket, TicketUatFinding $finding, User $actor): TicketUatFinding
    {
        return DB::transaction(function () use ($ticket, $finding, $actor): TicketUatFinding {
            $locked = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);
            if ($locked->status !== TicketStatus::DevelopmentInProgress) {
                throw new InvalidTicketTransition($locked->status->value, 'UAT findings can only be fixed during development rework.');
            }
            if ($locked->current_assignee_id !== $actor->id || ! $locked->assignments()->where('assigned_to', $actor->id)->where('is_current', true)->exists()) {
                throw new AuthorizationException('Only the assigned PIC can start findings.');
            }

            $lockedFinding = TicketUatFinding::query()->lockForUpdate()->findOrFail($finding->id);
            if ($lockedFinding->ticket_id !== $locked->id) {
                abort(404, 'Finding does not belong to this ticket.');
            }
            if ($lockedFinding->assigned_to !== $actor->id) {
                throw new AuthorizationException('This finding is assigned to another PIC.');
            }

            if ($lockedFinding->status !== 'open' && $lockedFinding->status !== 'reopened') {
                throw new InvalidTicketTransition($locked->status->value, 'Finding must be open or reopened to start.');
            }

            $from = $lockedFinding->status;
            $lockedFinding->status = 'in_progress';
            $lockedFinding->save();

            $this->findingHistory($lockedFinding, $from, 'in_progress', 'started', $actor);

            $locked->histories()->create([
                'from_status' => $locked->status->value,
                'to_status' => $locked->status->value,
                'action' => 'uat_finding_started',
                'actor_id' => $actor->id,
                'actor_role' => 'pic',
                'metadata' => ['finding_number' => $lockedFinding->finding_number],
            ]);

            return $lockedFinding->fresh();
        });
    }

    public function resolveFinding(Ticket $ticket, TicketUatFinding $finding, User $actor, string $notes): TicketUatFinding
    {
        return DB::transaction(function () use ($ticket, $finding, $actor, $notes): TicketUatFinding {
            $locked = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);
            if ($locked->status !== TicketStatus::DevelopmentInProgress) {
                throw new InvalidTicketTransition($locked->status->value, 'UAT findings can only be fixed during development rework.');
            }
            if ($locked->current_assignee_id !== $actor->id || ! $locked->assignments()->where('assigned_to', $actor->id)->where('is_current', true)->exists()) {
                throw new AuthorizationException('Only the assigned PIC can resolve findings.');
            }

            $lockedFinding = TicketUatFinding::query()->lockForUpdate()->findOrFail($finding->id);
            if ($lockedFinding->ticket_id !== $locked->id) {
                abort(404, 'Finding does not belong to this ticket.');
            }
            if ($lockedFinding->assigned_to !== $actor->id) {
                throw new AuthorizationException('This finding is assigned to another PIC.');
            }

            if ($lockedFinding->status !== 'in_progress') {
                throw new InvalidTicketTransition($locked->status->value, 'Finding must be in progress to be resolved.');
            }

            $from = $lockedFinding->status;
            $lockedFinding->status = 'resolved';
            $lockedFinding->resolved_by = $actor->id;
            $lockedFinding->resolved_at = now();
            $lockedFinding->resolution_notes = $notes;
            $lockedFinding->save();

            $this->findingHistory($lockedFinding, $from, 'resolved', 'resolved', $actor, $notes);

            $locked->histories()->create([
                'from_status' => $locked->status->value,
                'to_status' => $locked->status->value,
                'action' => 'uat_finding_resolved',
                'actor_id' => $actor->id,
                'actor_role' => 'pic',
                'metadata' => ['finding_number' => $lockedFinding->finding_number],
            ]);

            return $lockedFinding->fresh();
        });
    }

    public function submitRetest(Ticket $ticket, User $actor, bool $requiresQaRetest): Ticket
    {
        return DB::transaction(function () use ($ticket, $actor, $requiresQaRetest): Ticket {
            $locked = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);

            if ($locked->current_assignee_id !== $actor->id) {
                throw new AuthorizationException('Only the assigned PIC can submit UAT retests.');
            }
            if (! $locked->assignments()->where('assigned_to', $actor->id)->where('is_current', true)->exists()) {
                throw new AuthorizationException('PIC assignment is no longer active.');
            }
            if ($locked->status !== TicketStatus::DevelopmentInProgress) {
                throw new InvalidTicketTransition($locked->status->value, 'UAT retest can only be submitted during development rework.');
            }

            if ($locked->progress_percentage !== 100) {
                throw new InvalidTicketTransition($locked->status->value, 'Progress must be 100% to submit a UAT retest.');
            }

            if ($locked->internalTestRuns()->where('status', 'in_progress')->exists()) {
                throw new InvalidTicketTransition($locked->status->value, 'An active internal test run is currently running.');
            }

            $unresolvedFindings = $locked->uatFindings()->whereIn('status', ['open', 'in_progress', 'reopened'])->exists();
            if ($unresolvedFindings) {
                throw new InvalidTicketTransition($locked->status->value, 'All open, in-progress, or reopened UAT findings must be resolved.');
            }

            $lastUatFailure = $locked->histories()->where('to_status', 'uat_failed')->latest()->first();
            if (! $lastUatFailure) {
                throw new InvalidTicketTransition($locked->status->value, 'No UAT failure has occurred.');
            }

            $hasReworkWorklog = $locked->worklogs()
                ->where('created_at', '>=', $lastUatFailure->created_at)
                ->where('activity_type', 'rework')
                ->exists();
            if (! $hasReworkWorklog) {
                throw new InvalidTicketTransition($locked->status->value, 'At least one rework worklog is required since the last UAT failure.');
            }

            $hasPassedInternalRun = $locked->internalTestRuns()
                ->where('status', 'passed')
                ->where('completed_at', '>=', $lastUatFailure->created_at)
                ->exists();
            if (! $hasPassedInternalRun) {
                throw new InvalidTicketTransition($locked->status->value, 'A passed internal test run must be executed after the last UAT failure.');
            }

            if ($requiresQaRetest) {
                $lastQaRetestPassed = $locked->qaTestRuns()
                    ->where('status', 'passed')
                    ->where('completed_at', '>=', $lastUatFailure->created_at)
                    ->exists();
                if (! $lastQaRetestPassed) {
                    throw new InvalidTicketTransition($locked->status->value, 'QA retest is required and must have passed before UAT retest.');
                }
            }

            // Advance all resolved findings to retest status
            $resolvedFindings = $locked->uatFindings()->where('status', 'resolved')->get();
            foreach ($resolvedFindings as $finding) {
                $finding->status = 'retest';
                $finding->save();
                $this->findingHistory($finding, 'resolved', 'retest', 'submitted_for_retest', $actor);
            }

            return $this->transitions->submitUatRetest($locked, $actor);
        });
    }

    public function verifyFinding(Ticket $ticket, TicketUatFinding $finding, User $actor, ?string $notes): TicketUatFinding
    {
        return DB::transaction(function () use ($ticket, $finding, $actor, $notes): TicketUatFinding {
            $locked = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);
            if ($locked->uat_assignee_id !== $actor->id) {
                throw new AuthorizationException('Only the assigned UAT tester can verify findings.');
            }
            if (! in_array($locked->status, [TicketStatus::UatRetest, TicketStatus::UatInProgress], true)) {
                throw new InvalidTicketTransition($locked->status->value, 'Findings can only be verified during UAT retest.');
            }

            $lockedFinding = TicketUatFinding::query()->lockForUpdate()->findOrFail($finding->id);
            if ($lockedFinding->ticket_id !== $locked->id) {
                abort(404, 'Finding does not belong to this ticket.');
            }

            if ($lockedFinding->status !== 'retest') {
                throw new InvalidTicketTransition($locked->status->value, 'Finding must be submitted for UAT retest before verification.');
            }

            $from = $lockedFinding->status;
            $lockedFinding->status = 'verified';
            $lockedFinding->verified_by = $actor->id;
            $lockedFinding->verified_at = now();
            $lockedFinding->save();

            $this->findingHistory($lockedFinding, $from, 'verified', 'verified', $actor, $notes);

            $locked->histories()->create([
                'from_status' => $locked->status->value,
                'to_status' => $locked->status->value,
                'action' => 'uat_finding_verified',
                'actor_id' => $actor->id,
                'actor_role' => 'requester',
                'metadata' => ['finding_number' => $lockedFinding->finding_number],
            ]);

            return $lockedFinding->fresh();
        });
    }

    public function reopenFinding(Ticket $ticket, TicketUatFinding $finding, User $actor, ?string $notes): TicketUatFinding
    {
        return DB::transaction(function () use ($ticket, $finding, $actor, $notes): TicketUatFinding {
            $locked = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);
            if ($locked->uat_assignee_id !== $actor->id) {
                throw new AuthorizationException('Only the assigned UAT tester can reopen findings.');
            }
            if (! in_array($locked->status, [TicketStatus::UatRetest, TicketStatus::UatInProgress], true)) {
                throw new InvalidTicketTransition($locked->status->value, 'Findings can only be reopened during UAT retest.');
            }

            $lockedFinding = TicketUatFinding::query()->lockForUpdate()->findOrFail($finding->id);
            if ($lockedFinding->ticket_id !== $locked->id) {
                abort(404, 'Finding does not belong to this ticket.');
            }

            if ($lockedFinding->status !== 'retest') {
                throw new InvalidTicketTransition($locked->status->value, 'Finding must be submitted for UAT retest before reopening.');
            }

            $from = $lockedFinding->status;
            $lockedFinding->status = 'reopened';
            $lockedFinding->save();

            $this->findingHistory($lockedFinding, $from, 'reopened', 'reopened', $actor, $notes);

            $locked->histories()->create([
                'from_status' => $locked->status->value,
                'to_status' => $locked->status->value,
                'action' => 'uat_finding_reopened',
                'actor_id' => $actor->id,
                'actor_role' => 'requester',
                'metadata' => ['finding_number' => $lockedFinding->finding_number],
            ]);

            return $lockedFinding->fresh();
        });
    }

    public function rejectFinding(Ticket $ticket, TicketUatFinding $finding, User $actor, string $notes): TicketUatFinding
    {
        return DB::transaction(function () use ($ticket, $finding, $actor, $notes): TicketUatFinding {
            $locked = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);
            if (! $actor->hasRole('it_lead')) {
                throw new AuthorizationException('Only the IT Lead can reject UAT findings.');
            }

            $lockedFinding = TicketUatFinding::query()->lockForUpdate()->findOrFail($finding->id);
            if ($lockedFinding->ticket_id !== $locked->id) {
                abort(404, 'Finding does not belong to this ticket.');
            }

            if ($lockedFinding->status !== 'open') {
                throw new InvalidTicketTransition($locked->status->value, 'Only open UAT findings can be rejected.');
            }

            $from = $lockedFinding->status;
            $lockedFinding->status = 'rejected';
            $lockedFinding->save();

            $this->findingHistory($lockedFinding, $from, 'rejected', 'rejected', $actor, $notes);

            return $lockedFinding->fresh();
        });
    }

    private function findingHistory(TicketUatFinding $finding, ?string $from, string $to, string $action, User $actor, ?string $notes = null): void
    {
        $finding->histories()->create([
            'from_status' => $from,
            'to_status' => $to,
            'action' => $action,
            'actor_id' => $actor->id,
            'notes' => $notes,
            'created_at' => now(),
        ]);
    }
}
