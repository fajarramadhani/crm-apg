<?php

namespace App\Console\Commands;

use App\Models\Ticket;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Services\DynamicAssignmentService;
use App\Services\WorkflowActivationService;
use App\Services\WorkflowEngineService;
use App\Support\ConcurrencyCheckSafety;
use Illuminate\Console\Command;
use Throwable;

final class ConcurrencyCheckWorkerCommand extends Command
{
    protected $signature = 'crm:concurrency-worker {scenario} {barrier} {payload}';

    protected $description = 'Internal worker for crm:concurrency-check';

    protected $hidden = true;

    public function handle(
        DynamicAssignmentService $assignments,
        WorkflowEngineService $workflows,
        WorkflowActivationService $activations,
    ): int {
        if ($reason = ConcurrencyCheckSafety::refusalReason()) {
            $this->line(json_encode(['ok' => false, 'error' => $reason], JSON_UNESCAPED_SLASHES));

            return self::FAILURE;
        }

        $payload = json_decode(base64_decode((string) $this->argument('payload'), true) ?: '', true);
        $barrier = (string) $this->argument('barrier');
        if (! is_array($payload) || ! is_dir($barrier)) {
            $this->line(json_encode(['ok' => false, 'error' => 'Invalid worker payload or barrier.']));

            return self::FAILURE;
        }

        if (! $this->ownsPayloadRecords((string) $this->argument('scenario'), $payload)) {
            $this->line(json_encode(['ok' => false, 'error' => 'Worker payload does not reference concurrency-check fixtures.']));

            return self::FAILURE;
        }

        touch($barrier.DIRECTORY_SEPARATOR.'ready-'.$payload['worker']);
        $deadline = microtime(true) + 20;
        while (! is_file($barrier.DIRECTORY_SEPARATOR.'release')) {
            if (microtime(true) >= $deadline) {
                $this->line(json_encode(['ok' => false, 'error' => 'Barrier timeout.']));

                return self::FAILURE;
            }
            usleep(10_000);
        }

        try {
            $scenario = (string) $this->argument('scenario');
            $result = match ($scenario) {
                'primary' => $assignments->assignPrimary(
                    Ticket::query()->findOrFail($payload['ticket']),
                    User::query()->findOrFail($payload['actor']),
                    User::query()->findOrFail($payload['target']),
                    notes: 'concurrency-check primary'
                )->id,
                'reassign' => $assignments->reassign(
                    Ticket::query()->findOrFail($payload['ticket']),
                    User::query()->findOrFail($payload['actor']),
                    User::query()->findOrFail($payload['target']),
                    notes: 'concurrency-check reassign',
                    reason: 'parallel harness'
                )->id,
                'submit', 'approve' => $workflows->executeTransition(
                    Ticket::query()->findOrFail($payload['ticket']),
                    User::query()->findOrFail($payload['actor']),
                    $scenario === 'submit' ? 'submit_for_approval' : 'approve',
                    $scenario === 'submit' ? ['result_summary' => 'parallel result'] : [],
                    $scenario === 'submit' ? 'in_progress' : 'pending_approval',
                )->current_workflow_stage,
                'activate' => $this->activate($activations, (int) $payload['workflow']),
                default => throw new \RuntimeException("Unknown scenario: {$scenario}"),
            };

            $this->line(json_encode(['ok' => true, 'result' => $result], JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->line(json_encode([
                'ok' => false,
                'exception' => $exception::class,
                'error' => $exception->getMessage(),
            ], JSON_UNESCAPED_SLASHES));

            return self::FAILURE;
        }
    }

    private function activate(WorkflowActivationService $service, int $workflowId): string
    {
        $error = $service->activate(WorkflowDefinition::query()->findOrFail($workflowId));
        if ($error !== null) {
            throw new \RuntimeException($error[1].': '.$error[0]);
        }

        return 'active';
    }

    /** @param array<string, mixed> $payload */
    private function ownsPayloadRecords(string $scenario, array $payload): bool
    {
        $run = $payload['run'] ?? null;
        if (! is_string($run) || preg_match('/^cc[a-zA-Z0-9]{10}$/', $run) !== 1) {
            return false;
        }

        if ($scenario === 'activate') {
            return isset($payload['workflow'])
                && WorkflowDefinition::query()
                    ->whereKey($payload['workflow'])
                    ->where('code', 'like', $run.'\_%')
                    ->where('metadata->concurrency_run', $run)
                    ->exists();
        }

        if (! isset($payload['ticket'], $payload['actor'])) {
            return false;
        }

        $userIds = [$payload['actor']];
        if (isset($payload['target'])) {
            $userIds[] = $payload['target'];
        }

        return Ticket::query()
            ->whereKey($payload['ticket'])
            ->where('description', "Isolated {$run} fixture")
            ->exists()
            && User::query()
                ->whereIn('id', $userIds)
                ->where('email', 'like', $run.'.%@example.invalid')
                ->count() === count(array_unique($userIds));
    }
}
