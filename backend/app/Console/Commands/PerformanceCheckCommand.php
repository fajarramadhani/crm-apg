<?php

namespace App\Console\Commands;

use App\Enums\TicketStatus;
use App\Http\Controllers\Api\V1\AdminWorkflowController;
use App\Http\Controllers\Api\V1\PicTicketController;
use App\Http\Controllers\Api\V1\SupervisorItTicketController;
use App\Http\Requests\Api\V1\TicketListRequest;
use App\Models\Branch;
use App\Models\Division;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketPriority;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Services\TicketNotificationRecipientResolver;
use App\Services\TicketNotificationService;
use App\Support\PerformanceCheckSafety;
use Illuminate\Console\Command;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class PerformanceCheckCommand extends Command
{
    protected $signature = 'crm:performance-check {--confirm-disposable : Confirm the empty database is disposable}';

    protected $description = 'Generate disposable Stage 9 scale fixtures and benchmark CRM read paths';

    private const USER_COUNT = 120;

    private const TICKET_COUNT = 1000;

    /** @var list<QueryExecuted> */
    private array $capturedQueries = [];

    /** @var list<array<string, mixed>> */
    private array $results = [];

    /** @var array<string, mixed> */
    private array $fixtures = [];

    private bool $capturing = false;

    private string $token = '';

    public function handle(
        SupervisorItTicketController $supervisor,
        PicTicketController $pic,
        AdminWorkflowController $workflows,
        TicketNotificationRecipientResolver $resolver,
        TicketNotificationService $notifications,
    ): int {
        if ($reason = PerformanceCheckSafety::refusalReason((bool) $this->option('confirm-disposable'))) {
            return $this->refuse($reason);
        }

        try {
            $this->assertEmptyDisposableDatabase();
        } catch (Throwable $exception) {
            return $this->refuse($exception->getMessage());
        }

        $this->token = 'pb'.strtolower(Str::random(10));
        DB::listen(function (QueryExecuted $query): void {
            if ($this->capturing) {
                $this->capturedQueries[] = $query;
            }
        });

        $infrastructureFailures = [];

        try {
            $this->createFixtures();
            $this->runBenchmarks($supervisor, $pic, $workflows, $resolver, $notifications);
        } catch (Throwable $exception) {
            $infrastructureFailures[] = [
                'phase' => 'harness',
                'exception' => $exception::class,
                'error' => $exception->getMessage(),
            ];
        } finally {
            $this->capturing = false;
            try {
                $this->cleanup();
            } catch (Throwable $exception) {
                $infrastructureFailures[] = [
                    'phase' => 'cleanup',
                    'exception' => $exception::class,
                    'error' => $exception->getMessage(),
                ];
            }
        }

        foreach ($this->results as $result) {
            $this->jsonLine($result);
        }
        foreach ($infrastructureFailures as $failure) {
            $this->jsonLine(['type' => 'infrastructure_failure', ...$failure]);
        }

        $this->jsonLine([
            'type' => 'summary',
            'command' => 'php artisan crm:performance-check --confirm-disposable',
            'run' => $this->token,
            'ready' => $infrastructureFailures === [] && count($this->results) === 11,
            'infrastructure_failures' => count($infrastructureFailures),
            'benchmarks' => count($this->results),
            'fixtures' => [
                'users' => self::USER_COUNT,
                'tickets' => self::TICKET_COUNT,
                'assignments_per_ticket' => '1-3',
                'status_histories_per_ticket' => '5-20',
                'attachment_metadata_per_ticket' => '0-10',
                'workflow_versions' => 5,
                'physical_attachment_files' => 0,
            ],
            'findings' => [
                'benchmarks_with_repeated_queries' => collect($this->results)->where('n_plus_one.detected', true)->count(),
            ],
            'cleanup_attempted' => true,
        ]);

        return $infrastructureFailures === [] ? self::SUCCESS : self::FAILURE;
    }

    private function assertEmptyDisposableDatabase(): void
    {
        $nonEmpty = collect([
            'users',
            'roles',
            'tickets',
            'workflow_definitions',
            'divisions',
            'branches',
            'ticket_categories',
            'ticket_priorities',
        ])
            ->filter(fn (string $table): bool => DB::table($table)->exists())
            ->values();

        if ($reason = PerformanceCheckSafety::disposableBoundaryReason($nonEmpty->all())) {
            throw new RuntimeException($reason);
        }
    }

    private function createFixtures(): void
    {
        $now = now();
        $division = Division::query()->create(['code' => strtoupper($this->token), 'name' => "Performance {$this->token}"]);
        $branch = Branch::query()->create(['code' => strtoupper($this->token), 'name' => "Performance {$this->token}"]);
        $category = TicketCategory::query()->create(['code' => strtoupper($this->token), 'name' => "Performance {$this->token}", 'type' => 'request']);
        $priority = TicketPriority::query()->create(['key' => $this->token, 'name' => "Performance {$this->token}", 'level' => 1]);

        $roles = [];
        foreach (['requester', 'supervisor_it', 'pic_it_support', 'admin'] as $key) {
            $roles[$key] = Role::query()->create(['key' => $key, 'name' => Str::headline($key)]);
        }

        $users = [];
        for ($index = 0; $index < self::USER_COUNT; $index++) {
            $role = match (true) {
                $index === 0 => 'supervisor_it',
                $index === 1 => 'admin',
                $index < 32 => 'requester',
                default => 'pic_it_support',
            };
            $users[] = [
                'role_id' => $roles[$role]->id,
                'division_id' => $division->id,
                'branch_id' => $branch->id,
                'name' => "Performance {$role} {$index}",
                'email' => "{$this->token}.{$index}@example.invalid",
                'password' => '$2y$04$abcdefghijklmnopqrstuuJqQJ2P7jZzM6hGJ6GzJ7YxR4hQ5jM2',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        DB::table('users')->insert($users);
        $createdUsers = User::query()->where('email', 'like', $this->token.'.%@example.invalid')->orderBy('id')->get();
        $supervisor = $createdUsers[0];
        $admin = $createdUsers[1];
        $requesters = $createdUsers->slice(2, 30)->values();
        $pics = $createdUsers->slice(32)->values();

        $workflowModels = [];
        for ($version = 1; $version <= 5; $version++) {
            $workflow = WorkflowDefinition::query()->create([
                'code' => $this->token,
                'name' => "Performance workflow v{$version}",
                'description' => "Token-owned benchmark workflow {$this->token}",
                'version' => $version,
                'config_status' => $version === 5 ? 'active' : 'inactive',
                'is_active' => $version === 5,
                'published_at' => $now,
                'created_by' => $admin->id,
                'metadata' => ['performance_run' => $this->token],
            ]);
            $start = $workflow->stages()->create(['stage_key' => 'assigned', 'name' => 'Assigned', 'order' => 1, 'is_initial' => true]);
            $progress = $workflow->stages()->create(['stage_key' => 'in_progress', 'name' => 'In progress', 'order' => 2]);
            $done = $workflow->stages()->create(['stage_key' => 'done', 'name' => 'Done', 'order' => 3, 'is_terminal' => true]);
            $start->fields()->create(['field_name' => 'description', 'is_required' => true]);
            foreach ([[$start, $progress, 'start'], [$progress, $done, 'complete']] as [$from, $to, $action]) {
                $transition = $workflow->transitions()->create(['from_stage_id' => $from->id, 'to_stage_id' => $to->id, 'action_key' => $action, 'name' => Str::headline($action)]);
                $transition->permissions()->create(['role_key' => 'pic_it_support']);
                $transition->notifications()->create(['recipient_type' => 'requester', 'channel' => 'database', 'template_code' => "performance.{$action}"]);
            }
            $workflowModels[] = $workflow;
        }

        $tickets = [];
        $statuses = [TicketStatus::Assigned->value, TicketStatus::InProgress->value, TicketStatus::NeedInfo->value, TicketStatus::PendingApproval->value];
        for ($index = 0; $index < self::TICKET_COUNT; $index++) {
            $picUser = $pics[$index % $pics->count()];
            $tickets[] = [
                'ticket_number' => strtoupper('P'.$this->token.str_pad((string) $index, 6, '0', STR_PAD_LEFT)),
                'requester_id' => $requesters[$index % $requesters->count()]->id,
                'division_id' => $division->id,
                'current_division_id' => $division->id,
                'branch_id' => $branch->id,
                'ticket_category_id' => $category->id,
                'requested_priority_id' => $priority->id,
                'final_priority_id' => $priority->id,
                'current_assignee_id' => $picUser->id,
                'assigned_by' => $supervisor->id,
                'title' => "Performance ticket {$index}",
                'description' => "Token-owned benchmark fixture {$this->token}",
                'status' => $statuses[$index % count($statuses)],
                'assigned_at' => $now,
                'submitted_at' => $now,
                'resolution_due_at' => $index % 3 === 0 ? $now->copy()->subHour() : $now->copy()->addHours(12),
                'progress_percentage' => $index % 101,
                'workflow_id' => $workflowModels[$index % 5]->id,
                'workflow_version' => ($index % 5) + 1,
                'workflow_snapshot' => json_encode(['performance_run' => $this->token], JSON_THROW_ON_ERROR),
                'workflow_mode' => 'dynamic',
                'current_workflow_stage' => 'in_progress',
                'workflow_stage_entered_at' => $now,
                'created_at' => $now->copy()->subMinutes($index),
                'updated_at' => $now,
            ];
        }
        foreach (array_chunk($tickets, 500) as $chunk) {
            DB::table('tickets')->insert($chunk);
        }

        $ticketRows = DB::table('tickets')->where('description', "Token-owned benchmark fixture {$this->token}")->orderBy('id')->get(['id', 'current_assignee_id', 'requester_id']);
        $assignments = [];
        $histories = [];
        $attachments = [];
        foreach ($ticketRows as $index => $ticket) {
            $assignmentCount = 1 + ($index % 3);
            for ($offset = 0; $offset < $assignmentCount; $offset++) {
                $assignments[] = [
                    'ticket_id' => $ticket->id,
                    'assigned_to' => $pics[($index + $offset) % $pics->count()]->id,
                    'assigned_by' => $supervisor->id,
                    'assignment_type' => $offset === 0 ? 'primary' : 'secondary',
                    'role_at_assignment' => 'pic_it_support',
                    'started_at' => $now,
                    'is_current' => true,
                    'notes' => "Performance {$this->token}",
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            $historyCount = 5 + ($index % 16);
            for ($offset = 0; $offset < $historyCount; $offset++) {
                $histories[] = [
                    'ticket_id' => $ticket->id,
                    'from_status' => $offset === 0 ? null : TicketStatus::Assigned->value,
                    'to_status' => TicketStatus::Assigned->value,
                    'action' => 'performance_step',
                    'actor_id' => $offset % 2 === 0 ? $supervisor->id : $ticket->current_assignee_id,
                    'actor_role' => $offset % 2 === 0 ? 'supervisor_it' : 'pic_it_support',
                    'notes' => "Performance {$this->token}",
                    'metadata' => json_encode(['performance_run' => $this->token], JSON_THROW_ON_ERROR),
                    'created_at' => $now->copy()->subSeconds($offset),
                ];
            }

            $attachmentCount = $index % 11;
            for ($offset = 0; $offset < $attachmentCount; $offset++) {
                $attachments[] = [
                    'ticket_id' => $ticket->id,
                    'uploaded_by' => $ticket->requester_id,
                    'original_name' => "benchmark-{$offset}.txt",
                    'stored_name' => "{$this->token}-{$ticket->id}-{$offset}.txt",
                    'disk' => 'local',
                    'path' => "performance-metadata-only/{$this->token}/{$ticket->id}/{$offset}",
                    'mime_type' => 'text/plain',
                    'size' => 128 + $offset,
                    'category' => 'other',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }
        foreach ([['ticket_assignments', $assignments], ['ticket_status_histories', $histories], ['ticket_attachments', $attachments]] as [$table, $rows]) {
            foreach (array_chunk($rows, 1000) as $chunk) {
                DB::table($table)->insert($chunk);
            }
        }

        $this->fixtures = [
            'supervisor' => $supervisor,
            'admin' => $admin,
            'pic' => $pics->first(),
            'ticket' => Ticket::query()->where('current_assignee_id', $pics->first()->id)->firstOrFail(),
            'workflow' => $workflowModels[4],
        ];
    }

    private function runBenchmarks(
        SupervisorItTicketController $supervisor,
        PicTicketController $pic,
        AdminWorkflowController $workflows,
        TicketNotificationRecipientResolver $resolver,
        TicketNotificationService $notifications,
    ): void {
        $supervisorRequest = fn (string $uri, array $query = []) => $this->request(TicketListRequest::class, $uri, $this->fixtures['supervisor'], $query);
        $picRequest = fn (string $uri, array $query = []) => $this->request(TicketListRequest::class, $uri, $this->fixtures['pic'], $query);
        $adminRequest = fn (string $uri, array $query = []) => $this->request(Request::class, $uri, $this->fixtures['admin'], $query);
        $ticket = $this->fixtures['ticket'];
        $workflow = $this->fixtures['workflow'];

        $this->benchmark('supervisor.dashboard', fn () => $supervisor->dashboard($supervisorRequest('/api/v1/supervisor-it/dashboard')));
        $this->benchmark('supervisor.ticket_list', fn () => $supervisor->index($supervisorRequest('/api/v1/supervisor-it/tickets', ['per_page' => 50])));
        $this->benchmark('supervisor.ticket_detail', fn () => $supervisor->show($supervisorRequest("/api/v1/supervisor-it/tickets/{$ticket->id}"), $ticket->fresh()));
        $this->benchmark('pic.dashboard', fn () => $pic->dashboard($picRequest('/api/v1/pic/dashboard')));
        $this->benchmark('pic.ticket_list', fn () => $pic->tickets($picRequest('/api/v1/pic/tickets', ['per_page' => 50])));
        $this->benchmark('pic.ticket_detail', fn () => $pic->show($picRequest("/api/v1/pic/tickets/{$ticket->id}"), $ticket->fresh()));
        $this->benchmark('assignment.candidates', fn () => $supervisor->assignees($supervisorRequest('/api/v1/supervisor-it/assignees')));
        $this->benchmark('admin.workflow_list', fn () => $workflows->index($adminRequest('/api/v1/admin/workflows', ['per_page' => 20])));
        $this->benchmark('admin.workflow_detail', fn () => $workflows->show($adminRequest("/api/v1/admin/workflows/{$workflow->id}"), $workflow->fresh()));
        $this->benchmark('audit.timeline', fn () => $supervisor->show($supervisorRequest("/api/v1/supervisor-it/tickets/{$ticket->id}"), $ticket->fresh()));
        $this->benchmark('notification.resolver_and_service', function () use ($resolver, $notifications, $ticket): array {
            $resolved = $resolver->resolve($ticket->fresh(), 'ticket_submitted');
            $sent = $notifications->dispatch($ticket->fresh(), 'ticket_submitted', 'info', 'Performance benchmark', 'Metadata-only benchmark notification', actorId: $this->fixtures['supervisor']->id, metadata: ['performance_run' => $this->token]);

            return ['resolved_recipient_ids' => $resolved->pluck('id')->all(), 'sent' => $sent];
        });
    }

    private function request(string $class, string $uri, User $user, array $query = []): Request
    {
        /** @var Request $request */
        $request = $class::create($uri, 'GET', $query);
        if ($request instanceof FormRequest) {
            $request->setContainer(app());
        }
        $request->setUserResolver(fn (): User => $user);
        Auth::setUser($user);

        return $request;
    }

    private function benchmark(string $name, callable $operation): void
    {
        gc_collect_cycles();
        $this->capturedQueries = [];
        $memoryBefore = memory_get_usage(true);
        $peakBefore = memory_get_peak_usage(true);
        $started = hrtime(true);
        $this->capturing = true;

        try {
            $value = $operation();
        } finally {
            $this->capturing = false;
        }

        $elapsedMs = round((hrtime(true) - $started) / 1_000_000, 3);
        $payload = $value instanceof JsonResponse ? (string) $value->getContent() : json_encode($value, JSON_THROW_ON_ERROR);
        $signatures = [];
        foreach ($this->capturedQueries as $query) {
            $signature = $this->querySignature($query->sql);
            $signatures[$signature] = ($signatures[$signature] ?? 0) + 1;
        }
        arsort($signatures);
        $repeated = collect($signatures)
            ->filter(fn (int $count): bool => $count > 1)
            ->take(10)
            ->map(fn (int $count, string $signature): array => ['count' => $count, 'signature' => $signature])
            ->values()
            ->all();

        $this->results[] = [
            'type' => 'benchmark',
            'name' => $name,
            'elapsed_ms' => $elapsedMs,
            'query_count' => count($this->capturedQueries),
            'memory_delta_bytes' => memory_get_usage(true) - $memoryBefore,
            'peak_memory_delta_bytes' => max(0, memory_get_peak_usage(true) - $peakBefore),
            'payload_bytes' => strlen($payload),
            'n_plus_one' => [
                'detected' => $repeated !== [],
                'repeated_signature_count' => count($repeated),
                'repeated_signatures' => $repeated,
            ],
        ];
    }

    private function querySignature(string $sql): string
    {
        $normalized = preg_replace(["/'(?:''|[^'])*'/", '/\b\d+(?:\.\d+)?\b/', '/\s+/'], ['?', '?', ' '], strtolower($sql));

        return trim((string) $normalized);
    }

    private function cleanup(): void
    {
        if ($this->token === '') {
            return;
        }

        $userIds = DB::table('users')->where('email', 'like', $this->token.'.%@example.invalid')->pluck('id');
        DB::table('notification_delivery_logs')->whereIn('user_id', $userIds)->delete();
        DB::table('notifications')->whereIn('notifiable_id', $userIds)->delete();
        DB::table('tickets')->where('description', "Token-owned benchmark fixture {$this->token}")->delete();
        DB::table('workflow_definitions')->where('code', $this->token)->delete();
        DB::table('users')->whereIn('id', $userIds)->delete();
        DB::table('roles')->whereIn('key', ['requester', 'supervisor_it', 'pic_it_support', 'admin'])->delete();
        DB::table('ticket_categories')->where('code', strtoupper($this->token))->delete();
        DB::table('ticket_priorities')->where('key', $this->token)->delete();
        DB::table('branches')->where('code', strtoupper($this->token))->delete();
        DB::table('divisions')->where('code', strtoupper($this->token))->delete();
    }

    private function refuse(string $reason): int
    {
        $this->jsonLine([
            'type' => 'refusal',
            'command' => 'crm:performance-check',
            'ready' => false,
            'error' => $reason,
        ]);

        return self::FAILURE;
    }

    /** @param array<string, mixed> $value */
    private function jsonLine(array $value): void
    {
        $this->line(json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }
}
