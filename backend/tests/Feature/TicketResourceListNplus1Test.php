<?php

namespace Tests\Feature;

use App\Models\Division;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TicketResourceListNplus1Test extends TestCase
{
    use RefreshDatabase;

    private User $supervisor;

    private User $pic;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, MasterDataSeeder::class]);

        $division = Division::where('code', 'IT')->firstOrFail();

        $this->supervisor = User::factory()->create([
            'role_id' => Role::where('key', 'supervisor_it')->firstOrFail()->id,
            'division_id' => $division->id,
            'is_active' => true,
        ]);

        $this->pic = User::factory()->create([
            'role_id' => Role::where('key', 'pic_it_support')->firstOrFail()->id,
            'division_id' => $division->id,
            'is_active' => true,
        ]);
    }

    public function test_supervisor_it_ticket_list_does_not_issue_n_plus_one_queries_per_ticket(): void
    {
        // Create 10 tickets
        for ($i = 0; $i < 10; $i++) {
            Ticket::factory()->create([
                'current_division_id' => $this->supervisor->division_id,
                'status' => 'in_progress',
                'current_assignee_id' => $this->pic->id,
            ]);
        }

        // Measure query count for 5 tickets vs 10 tickets
        DB::enableQueryLog();
        $this->actingAs($this->supervisor)
            ->getJson('/api/v1/supervisor-it/tickets?per_page=5')
            ->assertOk();
        $queriesFor5 = count(DB::getQueryLog());
        DB::disableQueryLog();

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($this->supervisor)
            ->getJson('/api/v1/supervisor-it/tickets?per_page=10')
            ->assertOk();
        $queriesFor10 = count(DB::getQueryLog());
        DB::disableQueryLog();

        // The query count should NOT scale linearly with ticket count.
        // A difference of ±1 is acceptable for pagination metadata overhead,
        // but a per-ticket N+1 would produce ~5x more queries for 10 tickets.
        $this->assertLessThan(
            $queriesFor5 + 3,
            $queriesFor10 + 3,
            "Supervisor IT ticket list query count should not scale with ticket count (5 tickets = {$queriesFor5} queries, 10 tickets = {$queriesFor10} queries)."
        );
    }

    public function test_pic_ticket_list_does_not_issue_n_plus_one_queries_per_ticket(): void
    {
        // Create 10 tickets assigned to PIC
        for ($i = 0; $i < 10; $i++) {
            $ticket = Ticket::factory()->create([
                'status' => 'in_progress',
                'current_assignee_id' => $this->pic->id,
            ]);
            $ticket->assignments()->create([
                'assigned_to' => $this->pic->id,
                'assigned_by' => $this->supervisor->id,
                'assignment_type' => 'primary',
                'is_current' => true,
                'started_at' => now(),
                'assigned_at' => now(),
            ]);
        }

        // Measure query count for 5 tickets vs 10 tickets
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($this->pic)
            ->getJson('/api/v1/pic/tickets?per_page=5')
            ->assertOk();
        $queriesFor5 = count(DB::getQueryLog());
        DB::disableQueryLog();

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($this->pic)
            ->getJson('/api/v1/pic/tickets?per_page=10')
            ->assertOk();
        $queriesFor10 = count(DB::getQueryLog());
        DB::disableQueryLog();

        // The query count should NOT scale linearly with ticket count.
        // A difference of ±1 is acceptable for pagination metadata overhead,
        // but a per-ticket N+1 would produce ~5x more queries for 10 tickets.
        $this->assertLessThanOrEqual(
            $queriesFor5 + 1,
            $queriesFor10,
            "PIC ticket list query count should not scale with ticket count (5 tickets = {$queriesFor5} queries, 10 tickets = {$queriesFor10} queries)."
        );
    }
}
