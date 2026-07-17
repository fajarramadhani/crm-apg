<?php

namespace App\Services;

use App\Enums\TicketStatus;
use App\Exceptions\InvalidTicketTransition;
use App\Models\Ticket;
use App\Models\TicketQaTestCase;
use App\Models\TicketQaTestResult;
use App\Models\TicketQaTestRun;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final class TicketQaExecutionService
{
    public function __construct(private TicketTransitionService $transitions) {}

    public function start(Ticket $ticket, User $actor): Ticket
    {
        return DB::transaction(function () use ($ticket, $actor): Ticket {
            $locked = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);
            $this->assertOwner($locked, $actor);

            if ($locked->status === TicketStatus::QaAssignment) {
                return $this->transitions->startQa($locked, $actor);
            } elseif ($locked->status === TicketStatus::QaRetest) {
                return $this->transitions->startQaRetest($locked, $actor);
            } else {
                throw new InvalidTicketTransition($locked->status->value, 'Cannot start QA testing from current status.');
            }
        });
    }

    public function startRun(Ticket $ticket, User $actor, array $data): TicketQaTestRun
    {
        return DB::transaction(function () use ($ticket, $actor, $data): TicketQaTestRun {
            $locked = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);
            $this->assertOwner($locked, $actor);

            if ($locked->status !== TicketStatus::QaInProgress) {
                throw new InvalidTicketTransition($locked->status->value, 'QA test run can only be started when status is QA in progress.');
            }

            if ($locked->qaTestRuns()->where('status', 'in_progress')->exists()) {
                throw new InvalidTicketTransition($locked->status->value, 'A QA test run is already in progress.');
            }

            if (! $locked->qaTestCases()->where('is_active', true)->exists()) {
                throw new InvalidTicketTransition($locked->status->value, 'At least one active test case is required.');
            }

            $runNumber = ($locked->qaTestRuns()->max('run_number') ?? 0) + 1;

            $run = $locked->qaTestRuns()->create([
                'qa_user_id' => $actor->id,
                'cycle_number' => $locked->qa_cycle_number,
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
                'action' => 'qa_test_run_started',
                'actor_id' => $actor->id,
                'actor_role' => 'qa',
                'metadata' => ['run_number' => $runNumber, 'cycle_number' => $locked->qa_cycle_number],
            ]);

            return $run->fresh('results');
        });
    }

    public function recordResult(Ticket $ticket, TicketQaTestRun $run, User $actor, array $data): TicketQaTestResult
    {
        return DB::transaction(function () use ($ticket, $run, $actor, $data): TicketQaTestResult {
            $locked = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);
            $this->assertOwner($locked, $actor);

            $activeRun = TicketQaTestRun::query()->lockForUpdate()->findOrFail($run->id);
            if ($activeRun->ticket_id !== $locked->id) {
                abort(404, 'Run does not belong to this ticket.');
            }

            if ($locked->status !== TicketStatus::QaInProgress || $activeRun->status !== 'in_progress') {
                throw new InvalidTicketTransition($locked->status->value, 'The test run is no longer active.');
            }

            $case = TicketQaTestCase::findOrFail($data['qa_test_case_id']);
            if ($case->ticket_id !== $locked->id || ! $case->is_active) {
                throw new InvalidTicketTransition($locked->status->value, 'Test case is invalid or inactive.');
            }

            if ($activeRun->results()->where('qa_test_case_id', $case->id)->exists()) {
                throw new InvalidTicketTransition($locked->status->value, 'This test result has already been recorded.');
            }

            if (in_array($data['status'], ['failed', 'blocked'], true) && blank($data['actual_result'] ?? null) && blank($data['notes'] ?? null)) {
                throw new InvalidTicketTransition($locked->status->value, 'Failed or blocked results require notes or actual result.');
            }

            $result = $activeRun->results()->create([
                'qa_test_case_id' => $case->id,
                'executed_by' => $actor->id,
                'status' => $data['status'],
                'actual_result' => $data['actual_result'] ?? null,
                'notes' => $data['notes'] ?? null,
                'executed_at' => now(),
            ]);

            $locked->histories()->create([
                'from_status' => $locked->status->value,
                'to_status' => $locked->status->value,
                'action' => 'qa_test_result_recorded',
                'actor_id' => $actor->id,
                'actor_role' => 'qa',
                'metadata' => [
                    'run_number' => $activeRun->run_number,
                    'case_number' => $case->case_number,
                    'status' => $result->status,
                ],
            ]);

            return $result;
        });
    }

    public function complete(Ticket $ticket, TicketQaTestRun $run, User $actor, ?string $summary): TicketQaTestRun
    {
        return DB::transaction(function () use ($ticket, $run, $actor, $summary): TicketQaTestRun {
            $locked = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);
            $this->assertOwner($locked, $actor);

            $activeRun = TicketQaTestRun::query()->lockForUpdate()->findOrFail($run->id);
            if ($activeRun->ticket_id !== $locked->id) {
                abort(404, 'Run does not belong to this ticket.');
            }

            if ($locked->status !== TicketStatus::QaInProgress || $activeRun->status !== 'in_progress') {
                throw new InvalidTicketTransition($locked->status->value, 'The test run has already been completed.');
            }

            $activeCaseIds = $locked->qaTestCases()->where('is_active', true)->pluck('id');
            $recordedResults = $activeRun->results()->whereIn('qa_test_case_id', $activeCaseIds)->get();

            if ($recordedResults->count() !== $activeCaseIds->count() || $recordedResults->contains('status', 'not_run')) {
                throw new InvalidTicketTransition($locked->status->value, 'Every active test case must have a completed result.');
            }

            // Verify failed/blocked result constraints:
            foreach ($recordedResults as $result) {
                if ($result->status === 'failed') {
                    // Check if defect is filed for this case & run
                    $hasDefect = $locked->qaDefects()
                        ->where('qa_test_run_id', $activeRun->id)
                        ->where('qa_test_case_id', $result->qa_test_case_id)
                        ->exists();
                    if (! $hasDefect) {
                        throw new InvalidTicketTransition($locked->status->value, 'A defect must be reported for failed test case '.$result->testCase->case_number);
                    }
                } elseif ($result->status === 'blocked') {
                    // Check if defect is filed or notes are present
                    $hasDefect = $locked->qaDefects()
                        ->where('qa_test_run_id', $activeRun->id)
                        ->where('qa_test_case_id', $result->qa_test_case_id)
                        ->exists();
                    if (! $hasDefect && blank($result->notes)) {
                        throw new InvalidTicketTransition($locked->status->value, 'Blocked test case '.$result->testCase->case_number.' requires a defect or validation notes.');
                    }
                }
            }

            $failedCount = $recordedResults->whereIn('status', ['failed', 'blocked'])->count();
            $passedCount = $recordedResults->where('status', 'passed')->count();
            $outcome = $failedCount > 0 ? 'failed' : 'passed';

            $activeRun->update([
                'status' => $outcome,
                'completed_at' => now(),
                'summary' => $summary,
            ]);

            if ($outcome === 'failed') {
                $this->transitions->recordQaFailure($locked, $actor, $summary, [
                    'run_number' => $activeRun->run_number,
                    'cycle_number' => $activeRun->cycle_number,
                    'failed_cases' => $failedCount,
                ], function (Ticket $t): void {
                    $t->progress_percentage = min(90, $t->progress_percentage);
                    $t->latest_progress_at = now();
                });
            } else {
                $this->transitions->passQa($locked, $actor, $summary, [
                    'run_number' => $activeRun->run_number,
                    'cycle_number' => $activeRun->cycle_number,
                ]);
            }

            return $activeRun->fresh('results');
        });
    }

    public function assertOwner(Ticket $ticket, User $actor): void
    {
        if ($ticket->qa_assignee_id !== $actor->id) {
            throw new AuthorizationException('You are not the assigned QA for this ticket.');
        }
    }
}
