<?php

namespace App\Console\Commands;

use App\Models\Application;
use App\Models\Branch;
use App\Models\Division;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\TicketAssignment;
use App\Models\TicketCategory;
use App\Models\TicketPriority;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Services\WorkflowSnapshotBuilder;
use Database\Seeders\DefaultWorkflowSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class SeedStage9BrowserFixturesCommand extends Command
{
    public const CONFIRMATION = 'SEED-STAGE9-BROWSER-FIXTURES';

    protected $signature = 'stage9:seed-browser-fixtures
        {--confirm= : Must equal SEED-STAGE9-BROWSER-FIXTURES}
        {--json : Print only the fixture manifest as JSON}';

    protected $description = 'Seed isolated Stage 9 browser fixtures in a disposable non-production MySQL database';

    public function handle(WorkflowSnapshotBuilder $snapshots): int
    {
        $database = (string) config('database.connections.'.config('database.default').'.database');
        $refusal = self::safetyRefusal(
            app()->environment(),
            (string) config('database.default'),
            $database,
            (string) $this->option('confirm'),
        );

        if ($refusal !== null) {
            $this->error($refusal);

            return self::FAILURE;
        }

        $password = (string) getenv('STAGE9_BROWSER_PASSWORD');
        if (strlen($password) < 16) {
            $this->error('STAGE9_BROWSER_PASSWORD must contain at least 16 characters. The credential is never printed.');

            return self::INVALID;
        }

        $manifest = DB::transaction(function () use ($password, $snapshots): array {
            app(RoleSeeder::class)->run();
            app(DefaultWorkflowSeeder::class)->run();

            $data = $this->seedMasterData();
            $users = $this->seedUsers($password, $data['division'], $data['branch']);
            $workflow = WorkflowDefinition::query()->where('code', 'crm_default')->where('version', 1)->firstOrFail();
            WorkflowDefinition::query()->whereKeyNot($workflow->id)->update(['is_active' => false]);
            $workflow->update([
                'is_active' => true,
                'config_status' => 'active',
                'published_at' => now(),
            ]);

            $tickets = $this->seedTickets($users, $data, $workflow, $snapshots->build($workflow));

            return [
                'fixture' => 'stage9-browser',
                'database' => (string) config('database.connections.'.config('database.default').'.database'),
                'frontend_paths' => [
                    'requester_list' => '/user/tickets',
                    'requester_create' => '/user/create-ticket',
                    'requester_detail' => '/user/tickets/'.$tickets['requester']->id,
                    'supervisor_dashboard' => '/supervisor-it/dashboard',
                    'supervisor_list' => '/supervisor-it/tickets',
                    'supervisor_self_approval' => '/supervisor-it/tickets/'.$tickets['self_approval']->id,
                    'pic_dashboard' => '/pic/dashboard',
                    'pic_list' => '/pic/tickets',
                    'pic_detail' => '/pic/tickets/'.$tickets['pic']->id,
                    'legacy_detail' => '/supervisor-it/tickets/'.$tickets['legacy']->id,
                    'workflow_list' => '/admin/workflows',
                    'workflow_detail' => '/admin/workflows/'.$workflow->id,
                    'workflow_editor' => '/admin/workflows/'.$workflow->id.'/edit',
                ],
                'users' => collect($users)->map(fn (User $user): array => [
                    'id' => $user->id,
                    'email' => $user->email,
                    'role' => $user->role->key,
                ])->all(),
                'tickets' => collect($tickets)->map(fn (Ticket $ticket): array => [
                    'id' => $ticket->id,
                    'number' => $ticket->ticket_number,
                    'status' => $ticket->status->value,
                    'workflow_mode' => $ticket->workflow_mode,
                ])->all(),
            ];
        });

        $json = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        if ($this->option('json')) {
            $this->line($json);
        } else {
            $this->info('Stage 9 browser fixtures are ready. No credential was printed.');
            $this->line($json);
        }

        return self::SUCCESS;
    }

    public static function safetyRefusal(string $environment, string $connection, string $database, string $confirmation): ?string
    {
        if (! in_array($environment, ['local', 'testing', 'staging'], true)) {
            return 'Stage 9 browser fixtures are disabled outside local, testing, and staging.';
        }
        if ($connection !== 'mysql') {
            return 'Stage 9 browser fixtures require the mysql connection.';
        }
        if (! preg_match('/(?:browser|stage9|e2e|disposable|test)/i', $database)) {
            return 'The MySQL database name must explicitly identify a disposable browser/test database.';
        }
        if ($confirmation !== self::CONFIRMATION) {
            return 'Refusing without --confirm='.self::CONFIRMATION.'.';
        }

        return null;
    }

    /** @return array<string, array<string, string>> */
    public static function fixtureUsers(): array
    {
        return [
            'requester' => ['name' => 'Stage 9 Requester', 'email' => 'requester@stage9.invalid', 'role' => 'requester'],
            'supervisor' => ['name' => 'Stage 9 Supervisor IT', 'email' => 'supervisor-it@stage9.invalid', 'role' => 'supervisor_it'],
            'support' => ['name' => 'Stage 9 Support PIC', 'email' => 'support-pic@stage9.invalid', 'role' => 'pic_it_support'],
            'develop' => ['name' => 'Stage 9 Develop PIC', 'email' => 'develop-pic@stage9.invalid', 'role' => 'pic_it_develop'],
            'superadmin' => ['name' => 'Stage 9 Super Admin', 'email' => 'admin@stage9.invalid', 'role' => 'superadmin'],
            'legacy_it_lead' => ['name' => 'Stage 9 Legacy IT Lead', 'email' => 'legacy-it-lead@stage9.invalid', 'role' => 'it_lead'],
            'legacy_pic' => ['name' => 'Stage 9 Legacy PIC', 'email' => 'legacy-pic@stage9.invalid', 'role' => 'pic'],
            'legacy_qa' => ['name' => 'Stage 9 Legacy QA', 'email' => 'legacy-qa@stage9.invalid', 'role' => 'qa'],
        ];
    }

    /** @return array{division: Division, branch: Branch, application: Application, category: TicketCategory, priority: TicketPriority} */
    private function seedMasterData(): array
    {
        $division = Division::query()->updateOrCreate(['code' => 'STAGE9_IT'], ['name' => 'Stage 9 Isolated IT', 'description' => 'Disposable browser validation fixture', 'is_active' => true]);
        $branch = Branch::query()->updateOrCreate(['code' => 'STAGE9'], ['name' => 'Stage 9 Isolated Branch', 'city' => 'Test City', 'address' => 'Disposable browser validation fixture', 'is_active' => true]);
        $application = Application::query()->updateOrCreate(['code' => 'STAGE9_APP'], ['name' => 'Stage 9 Fixture Application', 'description' => 'Disposable browser validation fixture', 'owner_division_id' => $division->id, 'is_active' => true]);
        $category = TicketCategory::query()->updateOrCreate(['code' => 'STAGE9_INCIDENT'], ['name' => 'Stage 9 Browser Incident', 'type' => 'incident', 'description' => 'Disposable browser validation fixture', 'is_active' => true]);
        $priority = TicketPriority::query()->updateOrCreate(['key' => 'stage9_medium'], ['name' => 'Stage 9 Medium', 'level' => 90, 'description' => 'Disposable browser validation fixture', 'is_active' => true]);

        return compact('division', 'branch', 'application', 'category', 'priority');
    }

    /** @return array<string, User> */
    private function seedUsers(string $password, Division $division, Branch $branch): array
    {
        $users = [];
        foreach (self::fixtureUsers() as $key => $fixture) {
            $role = Role::query()->where('key', $fixture['role'])->where('is_active', true)->firstOrFail();
            $users[$key] = User::query()->updateOrCreate(
                ['email' => $fixture['email']],
                ['name' => $fixture['name'], 'role_id' => $role->id, 'division_id' => $division->id, 'branch_id' => $branch->id, 'password' => Hash::make($password), 'is_active' => true],
            )->load('role');
        }

        return $users;
    }

    /** @return array<string, Ticket> */
    private function seedTickets(array $users, array $data, WorkflowDefinition $workflow, array $snapshot): array
    {
        $base = [
            'requester_id' => $users['requester']->id,
            'division_id' => $data['division']->id,
            'current_division_id' => $data['division']->id,
            'branch_id' => $data['branch']->id,
            'application_id' => $data['application']->id,
            'ticket_category_id' => $data['category']->id,
            'requested_priority_id' => $data['priority']->id,
            'final_priority_id' => $data['priority']->id,
            'description' => 'Synthetic Stage 9 browser validation data. No production person, recipient, or customer data.',
            'affected_url' => 'https://stage9.invalid/example',
            'submitted_at' => now()->subHour(),
        ];
        $dynamic = [
            'workflow_id' => $workflow->id,
            'workflow_version' => $workflow->version,
            'workflow_snapshot' => $snapshot,
            'workflow_mode' => 'dynamic',
            'workflow_stage_entered_at' => now()->subMinutes(30),
        ];

        $requester = Ticket::query()->updateOrCreate(['ticket_number' => 'STAGE9-DYNAMIC-001'], $base + $dynamic + ['title' => 'Stage 9 requester dynamic ticket', 'status' => 'submitted', 'current_workflow_stage' => 'submitted']);
        $pic = Ticket::query()->updateOrCreate(['ticket_number' => 'STAGE9-DYNAMIC-002'], $base + $dynamic + ['title' => 'Stage 9 PIC work ticket', 'status' => 'in_progress', 'current_workflow_stage' => 'in_progress', 'current_assignee_id' => $users['support']->id, 'assigned_by' => $users['supervisor']->id, 'assigned_at' => now()->subMinutes(45), 'progress_percentage' => 60]);
        $selfApproval = Ticket::query()->updateOrCreate(['ticket_number' => 'STAGE9-DYNAMIC-003'], $base + $dynamic + ['title' => 'Stage 9 supervisor self approval ticket', 'status' => 'pending_approval', 'current_workflow_stage' => 'pending_approval', 'current_assignee_id' => $users['supervisor']->id, 'assigned_by' => $users['supervisor']->id, 'assigned_at' => now()->subHour(), 'approval_requested_at' => now()->subMinutes(15), 'progress_percentage' => 100]);
        $legacy = Ticket::query()->updateOrCreate(['ticket_number' => 'STAGE9-LEGACY-001'], $base + ['title' => 'Stage 9 representative legacy ticket', 'status' => 'triage', 'workflow_mode' => 'legacy', 'workflow_id' => null, 'workflow_version' => null, 'workflow_snapshot' => null, 'current_workflow_stage' => null, 'current_assignee_id' => $users['legacy_it_lead']->id, 'assigned_by' => $users['legacy_it_lead']->id, 'triage_started_at' => now()->subMinutes(20)]);

        foreach ([[$pic, $users['support'], $users['supervisor'], 'pic_it_support'], [$selfApproval, $users['supervisor'], $users['supervisor'], 'supervisor_it'], [$legacy, $users['legacy_pic'], $users['legacy_it_lead'], 'pic']] as [$ticket, $assignee, $assigner, $role]) {
            TicketAssignment::query()->updateOrCreate(
                ['ticket_id' => $ticket->id, 'assigned_to' => $assignee->id, 'assignment_type' => 'primary', 'is_current' => true],
                ['assigned_by' => $assigner->id, 'role_at_assignment' => $role, 'acting_as_pic' => true, 'started_at' => now()->subHour(), 'notes' => 'Stage 9 isolated browser fixture', 'assignment_reason' => 'Browser validation'],
            );
        }

        return ['requester' => $requester, 'pic' => $pic, 'self_approval' => $selfApproval, 'legacy' => $legacy];
    }
}
