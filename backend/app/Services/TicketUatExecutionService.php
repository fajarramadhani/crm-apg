<?php

namespace App\Services;

use App\Enums\TicketStatus;
use App\Exceptions\InvalidTicketTransition;
use App\Models\Ticket;
use App\Models\TicketUatResult;
use App\Models\TicketUatRun;
use App\Models\TicketUatScenario;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final class TicketUatExecutionService
{
    public function __construct(private TicketTransitionService $transitions) {}

    public function start(Ticket $ticket, User $actor): Ticket
    {
        return DB::transaction(function () use ($ticket, $actor): Ticket {
            $locked = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);
            $this->assertOwner($locked, $actor);

            if ($locked->status === TicketStatus::UatAssignment) {
                return $this->transitions->startUat($locked, $actor);
            } elseif ($locked->status === TicketStatus::UatRetest) {
                return $this->transitions->startUatRetest($locked, $actor);
            } else {
                throw new InvalidTicketTransition($locked->status->value, 'Cannot start UAT from current status.');
            }
        });
    }

    public function startRun(Ticket $ticket, User $actor, array $data): TicketUatRun
    {
        return DB::transaction(function () use ($ticket, $actor, $data): TicketUatRun {
            $locked = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);
            $this->assertOwner($locked, $actor);

            if ($locked->status !== TicketStatus::UatInProgress) {
                throw new InvalidTicketTransition($locked->status->value, 'UAT run can only be started when status is UAT in progress.');
            }

            if ($locked->uatRuns()->where('status', 'in_progress')->exists()) {
                throw new InvalidTicketTransition($locked->status->value, 'A UAT run is already in progress.');
            }

            if (! $locked->uatScenarios()->where('is_active', true)->exists()) {
                throw new InvalidTicketTransition($locked->status->value, 'At least one active scenario is required.');
            }

            $runNumber = ($locked->uatRuns()->max('run_number') ?? 0) + 1;

            $run = $locked->uatRuns()->create([
                'requester_id' => $actor->id,
                'cycle_number' => $locked->uat_cycle_number,
                'run_number' => $runNumber,
                'environment' => $data['environment'],
                'build_reference' => $data['build_reference'] ?? null,
                'started_at' => now(),
                'status' => 'in_progress',
                'summary' => $data['summary'] ?? null,
            ]);

            $locked->histories()->create([
                'from_status' => $locked->status->value,
                'to_status' => $locked->status->value,
                'action' => 'uat_run_started',
                'actor_id' => $actor->id,
                'actor_role' => 'requester',
                'metadata' => ['run_number' => $runNumber, 'cycle_number' => $locked->uat_cycle_number],
            ]);

            return $run->fresh('results');
        });
    }

    public function recordResult(Ticket $ticket, TicketUatRun $run, User $actor, array $data): TicketUatResult
    {
        return DB::transaction(function () use ($ticket, $run, $actor, $data): TicketUatResult {
            $locked = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);
            $this->assertOwner($locked, $actor);

            $activeRun = TicketUatRun::query()->lockForUpdate()->findOrFail($run->id);
            if ($activeRun->ticket_id !== $locked->id) {
                abort(404, 'Run does not belong to this ticket.');
            }
            if ($activeRun->requester_id !== $actor->id) {
                throw new AuthorizationException('Only the requester who started the UAT run can record results.');
            }

            if ($locked->status !== TicketStatus::UatInProgress || $activeRun->status !== 'in_progress') {
                throw new InvalidTicketTransition($locked->status->value, 'The UAT run is no longer active.');
            }

            $scenario = TicketUatScenario::findOrFail($data['uat_scenario_id']);
            if ($scenario->ticket_id !== $locked->id || ! $scenario->is_active) {
                throw new InvalidTicketTransition($locked->status->value, 'Scenario is invalid or inactive.');
            }

            if ($activeRun->results()->where('uat_scenario_id', $scenario->id)->exists()) {
                throw new InvalidTicketTransition($locked->status->value, 'This scenario result has already been recorded.');
            }

            if (in_array($data['status'], ['rejected', 'blocked'], true) && blank($data['actual_result'] ?? null) && blank($data['notes'] ?? null)) {
                throw new InvalidTicketTransition($locked->status->value, 'Rejected or blocked results require notes or actual result.');
            }

            $result = $activeRun->results()->create([
                'uat_scenario_id' => $scenario->id,
                'executed_by' => $actor->id,
                'status' => $data['status'],
                'actual_result' => $data['actual_result'] ?? null,
                'notes' => $data['notes'] ?? null,
                'executed_at' => now(),
            ]);

            $locked->histories()->create([
                'from_status' => $locked->status->value,
                'to_status' => $locked->status->value,
                'action' => 'uat_result_recorded',
                'actor_id' => $actor->id,
                'actor_role' => 'requester',
                'metadata' => [
                    'run_number' => $activeRun->run_number,
                    'scenario_number' => $scenario->scenario_number,
                    'status' => $result->status,
                ],
            ]);

            return $result;
        });
    }

    public function complete(Ticket $ticket, TicketUatRun $run, User $actor, ?string $summary): TicketUatRun
    {
        return DB::transaction(function () use ($ticket, $run, $actor, $summary): TicketUatRun {
            $locked = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);
            $this->assertOwner($locked, $actor);

            $activeRun = TicketUatRun::query()->lockForUpdate()->findOrFail($run->id);
            if ($activeRun->ticket_id !== $locked->id) {
                abort(404, 'Run does not belong to this ticket.');
            }
            if ($activeRun->requester_id !== $actor->id) {
                throw new AuthorizationException('Only the requester who started the UAT run can complete it.');
            }

            if ($locked->status !== TicketStatus::UatInProgress || $activeRun->status !== 'in_progress') {
                throw new InvalidTicketTransition($locked->status->value, 'The UAT run has already been completed.');
            }

            $activeScenarioIds = $locked->uatScenarios()->where('is_active', true)->pluck('id');
            $recordedResults = $activeRun->results()->whereIn('uat_scenario_id', $activeScenarioIds)->get();

            if ($recordedResults->count() !== $activeScenarioIds->count() || $recordedResults->contains('status', 'not_run')) {
                throw new InvalidTicketTransition($locked->status->value, 'Every active scenario must have a completed result.');
            }

            // Verify rejected/blocked UAT findings constraints:
            foreach ($recordedResults as $result) {
                if ($result->status === 'rejected') {
                    $hasFinding = $locked->uatFindings()
                        ->where('uat_run_id', $activeRun->id)
                        ->where('uat_scenario_id', $result->uat_scenario_id)
                        ->exists();
                    if (! $hasFinding) {
                        throw new InvalidTicketTransition($locked->status->value, 'A finding must be reported for rejected scenario '.$result->uatScenario->scenario_number);
                    }
                } elseif ($result->status === 'blocked') {
                    $hasFinding = $locked->uatFindings()
                        ->where('uat_run_id', $activeRun->id)
                        ->where('uat_scenario_id', $result->uat_scenario_id)
                        ->exists();
                    if (! $hasFinding && blank($result->notes)) {
                        throw new InvalidTicketTransition($locked->status->value, 'Blocked scenario '.$result->uatScenario->scenario_number.' requires a finding or validation notes.');
                    }
                }
            }

            $rejectedCount = $recordedResults->whereIn('status', ['rejected', 'blocked'])->count();
            $acceptedCount = $recordedResults->where('status', 'accepted')->count();
            $outcome = $rejectedCount > 0 ? 'rejected' : 'accepted';

            $activeRun->update([
                'status' => $outcome,
                'completed_at' => now(),
                'summary' => $summary,
            ]);

            if ($outcome === 'rejected') {
                $this->transitions->recordUatFailure($locked, $actor, $summary, [
                    'run_number' => $activeRun->run_number,
                    'cycle_number' => $activeRun->cycle_number,
                    'rejected_scenarios' => $rejectedCount,
                ], function (Ticket $t): void {
                    $t->progress_percentage = min(90, $t->progress_percentage);
                    $t->latest_progress_at = now();
                });
            } else {
                $this->transitions->passUat($locked, $actor, $summary, [
                    'run_number' => $activeRun->run_number,
                    'cycle_number' => $activeRun->cycle_number,
                ]);
            }

            return $activeRun->fresh('results');
        });
    }

    public function assertOwner(Ticket $ticket, User $actor): void
    {
        if ($ticket->uat_assignee_id !== $actor->id) {
            throw new AuthorizationException('You are not the assigned UAT tester for this ticket.');
        }
    }
}
