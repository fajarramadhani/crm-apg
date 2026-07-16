<?php

namespace App\Services;

use App\Enums\TicketStatus;
use App\Events\TicketInternalTestingFailed;
use App\Events\TicketInternalTestingStarted;
use App\Events\TicketReadyForQa;
use App\Exceptions\InvalidTicketTransition;
use App\Models\Ticket;
use App\Models\TicketInternalTestCase;
use App\Models\TicketInternalTestResult;
use App\Models\TicketInternalTestRun;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class TicketInternalTestingService
{
    public function __construct(private TicketTransitionService $transitions, private TicketDevelopmentService $development) {}

    public function createCase(Ticket $ticket, User $actor, array $data): TicketInternalTestCase
    {
        return DB::transaction(function () use ($ticket, $actor, $data) {
            $locked = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);
            $this->assertCaseMutable($locked, $actor);
            $case = $locked->internalTestCases()->create([...$data, 'created_by' => $actor->id, 'is_active' => true]);
            $this->history($locked, $actor, 'internal_test_case_created', ['case_number' => $case->case_number]);

            return $case;
        });
    }

    public function updateCase(Ticket $ticket, TicketInternalTestCase $case, User $actor, array $data): TicketInternalTestCase
    {
        return DB::transaction(function () use ($ticket, $case, $actor, $data) {
            $locked = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);
            $this->assertCaseMutable($locked, $actor);
            $item = TicketInternalTestCase::query()->lockForUpdate()->findOrFail($case->id);
            if ($item->ticket_id !== $locked->id) {
                abort(404);
            }if ($item->results()->exists()) {
                throw new InvalidTicketTransition($locked->status->value, 'A used test case cannot be edited.');
            }$item->update($data);

            return $item->fresh();
        });
    }

    public function deactivateCase(Ticket $ticket, TicketInternalTestCase $case, User $actor): TicketInternalTestCase
    {
        return DB::transaction(function () use ($ticket, $case, $actor) {
            $locked = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);
            $this->assertCaseMutable($locked, $actor);
            $item = TicketInternalTestCase::query()->lockForUpdate()->findOrFail($case->id);
            if ($item->ticket_id !== $locked->id) {
                abort(404);
            }if ($item->results()->exists()) {
                throw new InvalidTicketTransition($locked->status->value, 'A used test case can only remain as historical evidence.');
            }$item->update(['is_active' => false]);

            return $item;
        });
    }

    public function startRun(Ticket $ticket, User $actor, array $data): TicketInternalTestRun
    {
        $run = DB::transaction(function () use ($ticket, $actor, $data) {
            $locked = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);
            $this->development->assertOwner($locked, $actor);
            if ($locked->status !== TicketStatus::DevelopmentInProgress || $locked->progress_percentage !== 100) {
                throw new InvalidTicketTransition($locked->status->value, 'Development progress must be 100 before internal testing.');
            }if (! $locked->internalTestCases()->where('is_active', true)->exists()) {
                throw new InvalidTicketTransition($locked->status->value, 'At least one active test case is required.');
            }if ($locked->internalTestRuns()->where('status', 'in_progress')->exists()) {
                throw new InvalidTicketTransition($locked->status->value, 'An internal test run is already active.');
            }$run = $locked->internalTestRuns()->create([...$data, 'executed_by' => $actor->id, 'run_number' => ($locked->internalTestRuns()->max('run_number') ?? 0) + 1, 'started_at' => now(), 'status' => 'in_progress']);
            $this->transitions->phaseTransition($locked, $actor, TicketStatus::DevelopmentInProgress, TicketStatus::InternalTesting, 'internal_testing_started', metadata: ['test_run_number' => $run->run_number, 'build_reference' => $run->build_reference], mutate: fn (Ticket $t) => $t->internal_testing_started_at = now());

            return $run->fresh('results');
        });
        TicketInternalTestingStarted::dispatch($ticket->fresh(), $actor);

        return $run;
    }

    public function recordResult(Ticket $ticket, TicketInternalTestRun $run, User $actor, array $data): TicketInternalTestResult
    {
        return DB::transaction(function () use ($ticket, $run, $actor, $data) {
            $locked = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);
            $this->development->assertOwner($locked, $actor);
            $active = TicketInternalTestRun::query()->lockForUpdate()->findOrFail($run->id);
            if ($active->ticket_id !== $locked->id) {
                abort(404);
            }if ($locked->status !== TicketStatus::InternalTesting || $active->status !== 'in_progress') {
                throw new InvalidTicketTransition($locked->status->value, 'The test run is no longer active.');
            }$case = TicketInternalTestCase::findOrFail($data['test_case_id']);
            if ($case->ticket_id !== $locked->id || ! $case->is_active) {
                throw new InvalidTicketTransition($locked->status->value, 'Test case does not belong to this active ticket.');
            }if ($active->results()->where('test_case_id', $case->id)->exists()) {
                throw new InvalidTicketTransition($locked->status->value, 'This test result has already been recorded.');
            }if (in_array($data['status'], ['failed', 'blocked'], true) && blank($data['actual_result'] ?? null) && blank($data['notes'] ?? null)) {
                throw new InvalidTicketTransition($locked->status->value, 'Failed or blocked results require notes or an actual result.');
            }$result = $active->results()->create([...$data, 'executed_by' => $actor->id, 'executed_at' => now()]);
            $this->history($locked, $actor, 'internal_test_result_recorded', ['test_run_number' => $active->run_number, 'status' => $result->status]);

            return $result;
        });
    }

    public function complete(Ticket $ticket, TicketInternalTestRun $run, User $actor, ?string $summary): TicketInternalTestRun
    {
        $outcome = null;
        $completed = DB::transaction(function () use ($ticket, $run, $actor, $summary, &$outcome) {
            $locked = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);
            $this->development->assertOwner($locked, $actor);
            $active = TicketInternalTestRun::query()->lockForUpdate()->findOrFail($run->id);
            if ($active->ticket_id !== $locked->id) {
                abort(404);
            }if ($locked->status !== TicketStatus::InternalTesting || $active->status !== 'in_progress') {
                throw new InvalidTicketTransition($locked->status->value, 'The test run has already been completed.');
            }$caseIds = $locked->internalTestCases()->where('is_active', true)->pluck('id');
            $results = $active->results()->whereIn('test_case_id', $caseIds)->get();
            if ($results->count() !== $caseIds->count() || $results->contains('status', 'not_run')) {
                throw new InvalidTicketTransition($locked->status->value, 'Every active test case must have a completed result.');
            }$failed = $results->whereIn('status', ['failed', 'blocked'])->count();
            $passed = $results->where('status', 'passed')->count();
            $outcome = $failed > 0 ? 'failed' : 'passed';
            $active->update(['status' => $outcome, 'completed_at' => now(), 'summary' => $summary]);
            if ($outcome === 'failed') {
                $this->transitions->phaseTransition($locked, $actor, TicketStatus::InternalTesting, TicketStatus::DevelopmentInProgress, 'internal_testing_failed', metadata: ['test_run_number' => $active->run_number, 'passed' => $passed, 'failed' => $failed], mutate: function (Ticket $t) {
                    $t->progress_percentage = min(90, $t->progress_percentage);
                    $t->latest_progress_at = now();
                });
                $this->history($locked, $actor, 'development_rework_started', ['progress' => $locked->progress_percentage, 'reason' => 'internal_test_failed']);
            } else {
                $this->transitions->phaseTransition($locked, $actor, TicketStatus::InternalTesting, TicketStatus::ReadyForQa, 'internal_testing_passed', metadata: ['test_run_number' => $active->run_number, 'passed' => $passed, 'failed' => 0], mutate: function (Ticket $t) {
                    $t->development_completed_at = now();
                    $t->internal_testing_completed_at = now();
                    $t->ready_for_qa_at = now();
                });
                $this->history($locked, $actor, 'ready_for_qa', ['test_run_number' => $active->run_number]);
            }

return $active->fresh('results');
        });
        if ($outcome === 'failed') {
            TicketInternalTestingFailed::dispatch($ticket->fresh(), $actor);
        } else {
            TicketReadyForQa::dispatch($ticket->fresh(), $actor);
        }

return $completed;
    }

    private function assertCaseMutable(Ticket $ticket, User $actor): void
    {
        $this->development->assertOwner($ticket, $actor);
        if (! $ticket->development_started_at || ! in_array($ticket->status, [TicketStatus::DevelopmentInProgress, TicketStatus::InternalTesting], true)) {
            throw new InvalidTicketTransition($ticket->status->value);
        }if ($ticket->internalTestRuns()->where('status', 'in_progress')->exists()) {
            throw new InvalidTicketTransition($ticket->status->value,'Test cases cannot change during an active run.');
        }
    }

    private function history(Ticket $ticket,User $actor,string $action,array $metadata): void
    {
        $ticket->histories()->create(['from_status' => $ticket->status->value, 'to_status' => $ticket->status->value, 'action' => $action, 'actor_id' => $actor->id, 'actor_role' => 'pic', 'metadata' => $metadata]);
    }
}
