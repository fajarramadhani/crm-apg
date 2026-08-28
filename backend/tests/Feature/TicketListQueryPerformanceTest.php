<?php

namespace Tests\Feature;

use App\Enums\TicketStatus;
use App\Models\Division;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TicketListQueryPerformanceTest extends TestCase
{
    use RefreshDatabase;

    private User $supervisorIt;

    private User $picSupport;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, MasterDataSeeder::class]);

        $supervisorRole = Role::where('key', 'supervisor_it')->firstOrFail();
        $picRole = Role::where('key', 'pic_it_support')->firstOrFail();
        $division = Division::where('code', 'IT')->firstOrFail();

        $this->supervisorIt = User::factory()->create([
            'role_id' => $supervisorRole->id,
            'division_id' => $division->id,
            'is_active' => true,
        ]);

        $this->picSupport = User::factory()->create([
            'role_id' => $picRole->id,
            'division_id' => $division->id,
            'is_active' => true,
        ]);
    }

    public function test_supervisor_it_list_serialization_does_not_issue_per_ticket_queries(): void
    {
        // Create 5 tickets with various statuses including development_in_progress
        for ($i = 0; $i < 5; $i++) {
            Ticket::factory()->create([
                'current_division_id' => $this->supervisorIt->division_id,
                'current_assignee_id' => $this->picSupport->id,
                'status' => $i % 2 === 0 ? TicketStatus::DevelopmentInProgress : TicketStatus::Assigned,
            ]);
        }

        DB::enableQueryLog();
        $response = $this->actingAs($this->supervisorIt)
            ->getJson('/api/v1/supervisor-it/tickets')
            ->assertOk();
        $queries = collect(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertCount(5, $response->json('data'));

        // Verify no query was issued to ticket_qa_defects or ticket_uat_findings per ticket
        $defectQueries = $queries->filter(fn ($q) => str_contains($q['query'], 'ticket_qa_defects'));
        $uatQueries = $queries->filter(fn ($q) => str_contains($q['query'], 'ticket_uat_findings'));

        // withExists in forSummary produces aggregate EXISTS subqueries (one per
        // has_* flag), not per-ticket N+1. For 5 DevelopmentInProgress tickets the
        // supervisor list must not issue row-level defect/uat lookups per ticket.
        $this->assertLessThan(2, $defectQueries->count(), 'List serialization should not query ticket_qa_defects per ticket.');
        $this->assertLessThan(2, $uatQueries->count(), 'List serialization should not query ticket_uat_findings per ticket.');
    }

    public function test_pic_tickets_list_serialization_does_not_issue_per_ticket_queries(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $ticket = Ticket::factory()->create([
                'current_division_id' => $this->picSupport->division_id,
                'current_assignee_id' => $this->picSupport->id,
                'status' => TicketStatus::DevelopmentInProgress,
            ]);
            $ticket->assignments()->create([
                'assigned_to' => $this->picSupport->id,
                'assigned_by' => $this->supervisorIt->id,
                'assignment_type' => 'primary',
                'is_current' => true,
                'started_at' => now(),
            ]);
        }

        DB::enableQueryLog();
        $response = $this->actingAs($this->picSupport)
            ->getJson('/api/v1/pic/tickets')
            ->assertOk();
        $queries = collect(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertCount(5, $response->json('data'));

        $defectQueries = $queries->filter(fn ($q) => str_contains($q['query'], 'ticket_qa_defects'));
        $uatQueries = $queries->filter(fn ($q) => str_contains($q['query'], 'ticket_uat_findings'));

        $this->assertLessThan(2, $defectQueries->count(), 'PIC list serialization should not query ticket_qa_defects per ticket.');
        $this->assertLessThan(2, $uatQueries->count(), 'PIC list serialization should not query ticket_uat_findings per ticket.');
    }
}
