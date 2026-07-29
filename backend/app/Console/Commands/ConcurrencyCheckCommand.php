<?php

namespace App\Console\Commands;

use App\Enums\TicketStatus;
use App\Models\Branch;
use App\Models\Division;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketPriority;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Services\DynamicAssignmentService;
use App\Services\WorkflowSnapshotBuilder;
use App\Support\ConcurrencyCheckSafety;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class ConcurrencyCheckCommand extends Command
{
    protected $signature = 'crm:concurrency-check';

    protected $description = 'Run destructive true-concurrency checks against an isolated disposable MySQL database';

    /** @var array<string, mixed> */
    private array $fixtures = [];

    /** @var list<array<string, mixed>> */
    private array $results = [];

    private string $token;

    public function handle(DynamicAssignmentService $assignments, WorkflowSnapshotBuilder $snapshots): int
    {
        if ($reason = ConcurrencyCheckSafety::refusalReason()) {
            return $this->emitFailure('safety', $reason);
        }

        $this->token = 'cc'.strtolower(Str::random(10));

        try {
            $this->assertDisposableBoundary();
            $this->createFixtures($assignments, $snapshots);
            $this->runChecks();
        } catch (Throwable $exception) {
            $this->results[] = [
                'scenario' => 'harness',
                'passed' => false,
                'error' => $exception->getMessage(),
                'exception' => $exception::class,
            ];
        } finally {
            try {
                $this->cleanup();
            } catch (Throwable $exception) {
                $this->results[] = [
                    'scenario' => 'cleanup',
                    'passed' => false,
                    'error' => $exception->getMessage(),
                ];
            }
        }

        foreach ($this->results as $result) {
            $this->line(json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        }

        $passed = collect($this->results)->every(fn (array $result): bool => $result['passed'] === true);
        $this->line(json_encode([
            'summary' => true,
            'run' => $this->token,
            'passed' => $passed,
            'scenarios' => count($this->results),
        ], JSON_UNESCAPED_SLASHES));

        return $passed ? self::SUCCESS : self::FAILURE;
    }

    private function assertDisposableBoundary(): void
    {
        $roleKeys = ['requester', 'supervisor_it', 'pic_it_support', 'pic_it_develop'];
        if (Role::query()->whereIn('key', $roleKeys)->exists()) {
            throw new RuntimeException('Disposable database required: concurrency role keys already exist. No data was changed.');
        }

        if (WorkflowDefinition::query()->where('config_status', 'active')->exists()) {
            throw new RuntimeException('Disposable database required: an active workflow already exists. No data was changed.');
        }
    }

    private function createFixtures(DynamicAssignmentService $assignments, WorkflowSnapshotBuilder $snapshots): void
    {
        $division = Division::query()->create(['code' => strtoupper($this->token), 'name' => "Concurrency {$this->token}"]);
        $branch = Branch::query()->create(['code' => strtoupper($this->token), 'name' => "Concurrency {$this->token}"]);
        $category = TicketCategory::query()->create(['code' => strtoupper($this->token), 'name' => "Concurrency {$this->token}", 'type' => 'request']);
        $priority = TicketPriority::query()->create([
            'key' => $this->token,
            'name' => "Concurrency {$this->token}",
            'level' => ((int) TicketPriority::query()->max('level')) + 1,
        ]);

        $roles = [];
        foreach (['requester', 'supervisor_it', 'pic_it_support', 'pic_it_develop'] as $key) {
            $roles[$key] = Role::query()->create(['key' => $key, 'name' => "Concurrency {$key}"]);
        }

        $users = [];
        foreach (['requester', 'supervisor_it', 'pic_it_support', 'pic_it_develop'] as $key) {
            $users[$key] = User::query()->create([
                'role_id' => $roles[$key]->id,
                'division_id' => $division->id,
                'branch_id' => $branch->id,
                'name' => "Concurrency {$key}",
                'email' => "{$this->token}.{$key}@example.invalid",
                'password' => Str::random(40),
                'is_active' => true,
            ]);
        }

        $workflows = [
            $this->createWorkflow('a', $users['supervisor_it']),
            $this->createWorkflow('b', $users['supervisor_it']),
        ];
        $snapshot = $snapshots->build($workflows[0]);

        $tickets = [];
        foreach (['primary', 'submit', 'approve', 'reassign'] as $scenario) {
            $stage = match ($scenario) {
                'submit' => 'in_progress',
                'approve' => 'pending_approval',
                default => 'in_progress',
            };
            $tickets[$scenario] = Ticket::query()->create([
                'ticket_number' => strtoupper(substr($this->token, 0, 14).'-'.substr($scenario, 0, 6)),
                'requester_id' => $users['requester']->id,
                'division_id' => $division->id,
                'current_division_id' => $division->id,
                'branch_id' => $branch->id,
                'ticket_category_id' => $category->id,
                'requested_priority_id' => $priority->id,
                'title' => "Concurrency {$scenario}",
                'description' => "Isolated {$this->token} fixture",
                'status' => match ($stage) {
                    'pending_approval' => TicketStatus::PendingApproval,
                    default => TicketStatus::InProgress,
                },
                'workflow_id' => $workflows[0]->id,
                'workflow_version' => 1,
                'workflow_snapshot' => $snapshot,
                'workflow_mode' => 'dynamic',
                'current_workflow_stage' => $stage,
                'workflow_stage_entered_at' => now(),
            ]);
        }

        foreach (['submit', 'approve', 'reassign'] as $scenario) {
            $assignments->assignPrimary($tickets[$scenario], $users['supervisor_it'], $users['pic_it_support']);
        }

        $this->fixtures = compact('division', 'branch', 'category', 'priority', 'roles', 'users', 'workflows', 'tickets');
    }

    private function createWorkflow(string $suffix, User $creator): WorkflowDefinition
    {
        $workflow = WorkflowDefinition::query()->create([
            'code' => "{$this->token}_{$suffix}",
            'name' => "Concurrency {$suffix}",
            'version' => 1,
            'config_status' => 'published',
            'published_at' => now(),
            'created_by' => $creator->id,
            'metadata' => ['concurrency_run' => $this->token],
        ]);
        $progress = $workflow->stages()->create(['stage_key' => 'in_progress', 'name' => 'In progress', 'order' => 1, 'is_initial' => true]);
        $approval = $workflow->stages()->create(['stage_key' => 'pending_approval', 'name' => 'Pending approval', 'order' => 2, 'stage_type' => 'approval']);
        $done = $workflow->stages()->create(['stage_key' => 'done', 'name' => 'Done', 'order' => 3, 'is_terminal' => true]);

        $submit = $workflow->transitions()->create([
            'from_stage_id' => $progress->id,
            'to_stage_id' => $approval->id,
            'action_key' => 'submit_for_approval',
            'name' => 'Submit for approval',
        ]);
        $submit->permissions()->create(['role_key' => 'pic_it_support']);
        $submit->notifications()->create(['recipient_type' => 'requester', 'channel' => 'database', 'template_code' => 'concurrency.submit']);

        $approve = $workflow->transitions()->create([
            'from_stage_id' => $approval->id,
            'to_stage_id' => $done->id,
            'action_key' => 'approve',
            'name' => 'Approve',
        ]);
        $approve->permissions()->create(['role_key' => 'supervisor_it']);
        $approve->notifications()->create(['recipient_type' => 'requester', 'channel' => 'database', 'template_code' => 'concurrency.approve']);

        $config = $workflow->approvalConfigs()->create([
            'stage_id' => $approval->id,
            'approval_type' => 'single',
            'label' => 'Concurrency approval',
            'notes_required' => false,
            'is_active' => true,
        ]);
        $config->steps()->create(['step_order' => 1, 'approver_role_key' => 'supervisor_it']);

        return $workflow;
    }

    private function runChecks(): void
    {
        $users = $this->fixtures['users'];
        $tickets = $this->fixtures['tickets'];
        $workflows = $this->fixtures['workflows'];

        $workers = $this->runWorkers('primary', [
            ['ticket' => $tickets['primary']->id, 'actor' => $users['supervisor_it']->id, 'target' => $users['pic_it_support']->id],
            ['ticket' => $tickets['primary']->id, 'actor' => $users['supervisor_it']->id, 'target' => $users['pic_it_develop']->id],
        ]);
        $this->record('competing_primary_assignment', $workers,
            $this->assignmentInvariant($tickets['primary'], 2));

        foreach (['submit', 'approve'] as $scenario) {
            $actor = $scenario === 'submit' ? $users['pic_it_support'] : $users['supervisor_it'];
            $workers = $this->runWorkers($scenario, array_fill(0, 2, ['ticket' => $tickets[$scenario]->id, 'actor' => $actor->id]));
            $history = $tickets[$scenario]->histories()->where('action', $scenario === 'submit' ? 'submit_for_approval' : 'approve')->count();
            $notifications = DB::table('notifications')
                ->where('notifiable_id', $users['requester']->id)
                ->where('data', 'like', '%"ticket_id":'.$tickets[$scenario]->id.'%')
                ->count();
            $this->record("duplicate_{$scenario}", $workers, [
                'worker_successes' => collect($workers)->where('ok', true)->count() === 1,
                'history_count' => $history === 1,
                'notification_count' => $notifications === 1,
                'final_stage' => $tickets[$scenario]->fresh()->current_workflow_stage === ($scenario === 'submit' ? 'pending_approval' : 'done'),
            ]);
        }

        $workers = $this->runWorkers('activate', [
            ['workflow' => $workflows[0]->id],
            ['workflow' => $workflows[1]->id],
        ]);
        $this->record('competing_workflow_activation', $workers, [
            'worker_successes' => collect($workers)->where('ok', true)->count() === 2,
            'one_active_workflow' => WorkflowDefinition::query()->whereIn('id', collect($workflows)->pluck('id'))->where('config_status', 'active')->count() === 1,
            'active_flags_match' => WorkflowDefinition::query()->whereIn('id', collect($workflows)->pluck('id'))->where('is_active', true)->count() === 1,
        ]);

        $workers = $this->runWorkers('reassign', [
            ['ticket' => $tickets['reassign']->id, 'actor' => $users['supervisor_it']->id, 'target' => $users['pic_it_develop']->id],
            ['ticket' => $tickets['reassign']->id, 'actor' => $users['supervisor_it']->id, 'target' => $users['supervisor_it']->id],
        ]);
        $this->record('competing_reassignment', $workers, $this->assignmentInvariant($tickets['reassign'], 3));
    }

    /** @param list<array<string, int>> $payloads
     * @return list<array<string, mixed>>
     */
    private function runWorkers(string $scenario, array $payloads): array
    {
        $barrier = storage_path('framework/cache/concurrency-'.$this->token.'-'.$scenario);
        if (! mkdir($barrier, 0700, true) && ! is_dir($barrier)) {
            throw new RuntimeException("Cannot create barrier {$barrier}");
        }

        $processes = [];
        foreach ($payloads as $index => $payload) {
            $payload['worker'] = $index + 1;
            $payload['run'] = $this->token;
            $command = [PHP_BINARY, base_path('artisan'), 'crm:concurrency-worker', $scenario, $barrier, base64_encode(json_encode($payload, JSON_THROW_ON_ERROR))];
            $pipes = [];
            $process = proc_open($command, [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes, base_path());
            if (! is_resource($process)) {
                throw new RuntimeException('Unable to start worker process.');
            }
            fclose($pipes[0]);
            $processes[] = compact('process', 'pipes');
        }

        $deadline = microtime(true) + 20;
        while (count(glob($barrier.DIRECTORY_SEPARATOR.'ready-*') ?: []) < count($payloads)) {
            if (microtime(true) >= $deadline) {
                throw new RuntimeException("Workers did not reach the {$scenario} barrier.");
            }
            usleep(10_000);
        }
        touch($barrier.DIRECTORY_SEPARATOR.'release');

        $results = [];
        foreach ($processes as $worker) {
            $stdout = trim(stream_get_contents($worker['pipes'][1]));
            $stderr = trim(stream_get_contents($worker['pipes'][2]));
            fclose($worker['pipes'][1]);
            fclose($worker['pipes'][2]);
            $exit = proc_close($worker['process']);
            $decoded = json_decode(collect(preg_split('/\R/', $stdout))->last() ?: '', true);
            $results[] = is_array($decoded)
                ? $decoded + ['exit_code' => $exit]
                : ['ok' => false, 'exit_code' => $exit, 'error' => $stderr !== '' ? $stderr : $stdout];
        }

        foreach (glob($barrier.DIRECTORY_SEPARATOR.'*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($barrier);

        return $results;
    }

    /** @return array<string, bool> */
    private function assignmentInvariant(Ticket $ticket, int $expectedHistories): array
    {
        $active = $ticket->assignments()->where('assignment_type', 'primary')->where('is_current', true)->get();

        return [
            'worker_successes' => true,
            'one_active_primary' => $active->count() === 1,
            'history_count' => $ticket->assignmentHistories()->whereIn('action', ['assigned', 'reassigned'])->count() === $expectedHistories,
            'ticket_matches_assignment' => $ticket->fresh()->current_assignee_id === $active->first()?->assigned_to,
        ];
    }

    /** @param list<array<string, mixed>> $workers
     * @param  array<string, bool>  $invariants
     */
    private function record(string $scenario, array $workers, array $invariants): void
    {
        if (isset($invariants['worker_successes']) && $invariants['worker_successes'] === true && ! str_starts_with($scenario, 'duplicate_')) {
            $invariants['worker_successes'] = collect($workers)->where('ok', true)->count() === count($workers);
        }

        $this->results[] = [
            'scenario' => $scenario,
            'passed' => ! in_array(false, $invariants, true),
            'invariants' => $invariants,
            'workers' => $workers,
        ];
    }

    private function cleanup(): void
    {
        $userIds = User::query()->where('email', 'like', $this->token.'.%@example.invalid')->pluck('id');
        DB::table('notifications')->where('notifiable_type', User::class)
            ->whereIn('notifiable_id', $userIds)->delete();
        Ticket::withTrashed()->where('description', "Isolated {$this->token} fixture")->get()->each->forceDelete();
        WorkflowDefinition::withTrashed()->where('code', 'like', $this->token.'\_%')->get()->each->forceDelete();
        User::query()->whereIn('id', $userIds)->delete();
        Role::query()->whereIn('key', ['requester', 'supervisor_it', 'pic_it_support', 'pic_it_develop'])->delete();
        TicketCategory::query()->where('code', strtoupper($this->token))->delete();
        TicketPriority::query()->where('key', $this->token)->delete();
        Branch::query()->where('code', strtoupper($this->token))->delete();
        Division::query()->where('code', strtoupper($this->token))->delete();
    }

    private function emitFailure(string $scenario, string $error): int
    {
        $this->line(json_encode(['scenario' => $scenario, 'passed' => false, 'error' => $error], JSON_UNESCAPED_SLASHES));

        return self::FAILURE;
    }
}
