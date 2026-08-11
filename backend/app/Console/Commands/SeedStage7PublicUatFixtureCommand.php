<?php

namespace App\Console\Commands;

use App\Enums\TicketStatus;
use App\Models\Application;
use App\Models\Branch;
use App\Models\Division;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketPriority;
use App\Services\PublicTicketTrackingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class SeedStage7PublicUatFixtureCommand extends Command
{
    public const CONFIRMATION = 'SEED-STAGE7-PUBLIC-UAT';

    protected $signature = 'stage7:seed-public-uat
        {--confirm= : Must equal SEED-STAGE7-PUBLIC-UAT}
        {--json : Print only the fixture manifest as JSON}';

    protected $description = 'Seed one synthetic public UAT fixture in a disposable local or test database';

    public function handle(PublicTicketTrackingService $tracking): int
    {
        $database = (string) config('database.connections.'.config('database.default').'.database');
        $refusal = self::safetyRefusal(app()->environment(), $database, (string) $this->option('confirm'));
        if ($refusal !== null) {
            $this->error($refusal);

            return self::FAILURE;
        }

        $manifest = DB::transaction(function () use ($tracking): array {
            $division = Division::query()->updateOrCreate(['code' => 'STAGE7_IT'], ['name' => 'Stage 7 Isolated IT', 'is_active' => true]);
            $branch = Branch::query()->updateOrCreate(['code' => 'STAGE7'], ['name' => 'Stage 7 Isolated Branch', 'is_active' => true]);
            $application = Application::query()->updateOrCreate(['code' => 'STAGE7_APP'], ['name' => 'Stage 7 Fixture Application', 'owner_division_id' => $division->id, 'is_active' => true]);
            $category = TicketCategory::query()->updateOrCreate(['code' => 'STAGE7_UAT'], ['name' => 'Stage 7 Public UAT', 'type' => 'incident', 'is_active' => true]);
            $priority = TicketPriority::query()->updateOrCreate(['key' => 'stage7_medium'], ['name' => 'Stage 7 Medium', 'level' => 70, 'is_active' => true]);
            $ticket = Ticket::query()->updateOrCreate(['ticket_number' => 'STAGE7-PUBLIC-UAT-001'], [
                'requester_id' => null,
                'requester_name' => 'Stage 7 Public Requester',
                'requester_email' => 'requester@stage7.invalid',
                'submission_source' => 'public_form',
                'division_id' => $division->id,
                'current_division_id' => $division->id,
                'branch_id' => $branch->id,
                'application_id' => $application->id,
                'ticket_category_id' => $category->id,
                'requested_priority_id' => $priority->id,
                'final_priority_id' => $priority->id,
                'title' => 'Synthetic Stage 7 public UAT fixture',
                'description' => 'Synthetic data only. Verify OTP, private evidence upload, idempotency, and terminal state.',
                'status' => TicketStatus::ReadyForUat,
                'submitted_at' => now(),
            ]);
            $record = $ticket->publicTrackingTokens()->whereNull('revoked_at')->latest('generation')->first();
            $receipt = $record ? $tracking->receipt($record) : $tracking->create($ticket);

            return [
                'fixture' => 'stage7-public-uat',
                'ticket_id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'requester_email' => $ticket->requester_email,
                'tracking_url' => $receipt['tracking_url'],
                'expected_action' => 'uat',
            ];
        });

        $json = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        if (! $this->option('json')) {
            $this->info('Stage 7 public UAT fixture is ready. The synthetic tracking URL follows.');
        }
        $this->line($json);

        return self::SUCCESS;
    }

    public static function safetyRefusal(string $environment, string $database, string $confirmation): ?string
    {
        if (! in_array($environment, ['local', 'testing'], true)) {
            return 'Stage 7 fixtures are disabled outside local and testing.';
        }
        if ($database !== ':memory:' && $database !== 'crm_local' && ! preg_match('/(?:stage7|fixture|e2e|disposable|test)/i', $database)) {
            return 'The database name must explicitly identify a disposable fixture/test database.';
        }
        if ($confirmation !== self::CONFIRMATION) {
            return 'Refusing without --confirm='.self::CONFIRMATION.'.';
        }

        return null;
    }
}
