<?php

namespace Tests\Feature;

use App\Enums\TicketStatus;
use App\Models\Division;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\TicketQaDefect;
use App\Models\TicketQaTestRun;
use App\Models\User;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TicketResourceSerializationNplus1Test extends TestCase
{
    use RefreshDatabase;

    private User $pic;

    private Division $division;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(MasterDataSeeder::class);

        $this->division = Division::where('code', 'IT')->firstOrFail();
        $this->pic = User::factory()->create([
            'role_id' => Role::where('key', 'pic_it_support')->firstOrFail()->id,
            'division_id' => $this->division->id,
            'is_active' => true,
        ]);
    }

    public function test_pic_tickets_endpoint_serialization_does_not_issue_per_ticket_defect_or_history_queries(): void
    {
        // Create 4 tickets in DevelopmentInProgress assigned to our PIC.
        // Two tickets have open QA defects, which triggers the deeper
        // allowedActions() path that previously issued per-ticket
        // histories()/worklogs()/internalTestRuns() queries.
        $tickets = Ticket::factory()->count(4)->create([
            'status' => TicketStatus::DevelopmentInProgress,
            'current_assignee_id' => $this->pic->id,
            'division_id' => $this->division->id,
            'progress_percentage' => 100,
        ]);

        foreach ($tickets as $index => $ticket) {
            $ticket->assignments()->create([
                'assigned_to' => $this->pic->id,
                'assigned_by' => $this->pic->id,
                'assignment_type' => 'primary',
                'is_current' => true,
                'started_at' => now(),
            ]);

            // Odd-index tickets get an open QA defect.
            if ($index % 2 === 1) {
                $qaTestRun = TicketQaTestRun::create([
                    'ticket_id' => $ticket->id,
                    'qa_user_id' => $this->pic->id,
                    'cycle_number' => 1,
                    'run_number' => 1,
                    'environment' => 'staging',
                    'started_at' => now(),
                    'completed_at' => now(),
                    'status' => 'failed',
                ]);

                TicketQaDefect::create([
                    'ticket_id' => $ticket->id,
                    'qa_test_run_id' => $qaTestRun->id,
                    'qa_test_case_id' => null,
                    'reported_by' => $this->pic->id,
                    'assigned_to' => $this->pic->id,
                    'defect_number' => 'DEF-'.$index,
                    'title' => 'Sample Defect '.$index,
                    'description' => 'Test defect description',
                    'severity' => 'major',
                    'priority' => 'medium',
                    'steps_to_reproduce' => 'Reproduce',
                    'expected_result' => 'Expected',
                    'actual_result' => 'Actual',
                    'status' => 'open',
                ]);
            }
        }

        DB::enableQueryLog();

        $response = $this->actingAs($this->pic)
            ->getJson('/api/v1/pic/tickets')
            ->assertOk();

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // Ensure we got 4 tickets back
        $this->assertCount(4, $response->json('data'));

        // Count per-table queries that would N+1.
        $defectQueries = collect($queries)->filter(fn ($q) => str_contains($q['query'], 'ticket_qa_defects'));
        $historyQueries = collect($queries)->filter(fn ($q) => str_contains($q['query'], 'ticket_status_histories') && str_contains(strtolower($q['query']), 'where'));
        $worklogQueries = collect($queries)->filter(fn ($q) => str_contains($q['query'], 'ticket_worklogs') && str_contains(strtolower($q['query']), 'where'));
        $internalTestQueries = collect($queries)->filter(fn ($q) => str_contains($q['query'], 'ticket_internal_test_runs'));

        // The N+1 guard: these queries must not scale with ticket count (4 tickets).
        // With forSummary() adding has_open_defects / has_open_uat_finding via withExists,
        // defect lookups should not execute per-ticket.
        $this->assertLessThan(
            2,
            $defectQueries->count(),
            'Defect queries during list serialization must not execute per-ticket. '.
            'Got '.$defectQueries->count().' queries.'
        );

        // Histories queries should NOT scale with ticket count.
        // With withExists for has_qa_failed_history / has_uat_failed_history, we get
        // exactly 2 aggregate EXISTS subqueries (fixed, not per-ticket).
        // If N+1 was happening, we'd see 4+ queries (one per ticket).
        $this->assertLessThan(
            3,
            $historyQueries->count(),
            'Status history queries during list serialization must not execute per-ticket. '.
            'Got '.$historyQueries->count().' queries (allowing 2 aggregate EXISTS queries).'
        );

        // Worklog and internal test queries should be minimal (aggregate only).
        // Similar to histories, we have fixed aggregate existence queries.
        $combinedNplus1 = $worklogQueries->count() + $internalTestQueries->count();
        $this->assertLessThan(
            5,
            $combinedNplus1,
            'Worklog/internal-test queries during list serialization must not execute per-ticket. '.
            'Got '.$combinedNplus1.' queries (allowing fixed aggregate queries).'
        );
    }
}
