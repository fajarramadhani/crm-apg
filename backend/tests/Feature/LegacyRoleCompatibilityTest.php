<?php

namespace Tests\Feature;

use App\Enums\TicketStatus;
use App\Models\Application;
use App\Models\Branch;
use App\Models\Division;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketPriority;
use App\Models\User;
use App\Services\DynamicAssignmentService;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegacyRoleCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    private User $legacyItLead;

    private User $legacyPic;

    private User $legacyQa;

    private User $legacySupervisor;

    private User $supervisorIt;

    private User $picSupport;

    private User $requester;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, MasterDataSeeder::class]);

        $this->requester = $this->createUser('requester');
        $this->legacyItLead = $this->createUser('it_lead');
        $this->legacyPic = $this->createUser('pic');
        $this->legacyQa = $this->createUser('qa');
        $this->legacySupervisor = $this->createUser('supervisor');
        $this->supervisorIt = $this->createUser('supervisor_it');
        $this->picSupport = $this->createUser('pic_it_support');
    }

    public function test_legacy_it_lead_can_still_access_legacy_it_lead_routes(): void
    {
        $ticket = $this->createTicket(['status' => TicketStatus::Triage]);

        $res = $this->actingAs($this->legacyItLead)
            ->getJson('/api/v1/it-lead/triage-queue');

        $res->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_legacy_pic_can_still_open_legacy_assigned_tickets(): void
    {
        $ticket = $this->createTicket([
            'status' => TicketStatus::Assigned,
            'current_assignee_id' => $this->legacyPic->id,
        ]);
        $ticket->assignments()->create([
            'assigned_to' => $this->legacyPic->id,
            'assigned_by' => $this->legacyItLead->id,
            'assignment_type' => 'primary',
            'started_at' => now(),
            'is_current' => true,
        ]);

        $res = $this->actingAs($this->legacyPic)
            ->getJson("/api/v1/pic/tickets/{$ticket->id}");

        $res->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_legacy_qa_route_remains_functional(): void
    {
        $res = $this->actingAs($this->legacyQa)
            ->getJson('/api/v1/qa/assignments');

        $res->assertOk();
    }

    public function test_ticket_status_is_not_modified_by_new_assignment_engine(): void
    {
        $ticket = $this->createTicket(['status' => TicketStatus::PendingValidation]);
        $initialStatus = $ticket->status;

        $service = app(DynamicAssignmentService::class);
        $service->assignPrimary($ticket, $this->supervisorIt, $this->picSupport);

        $this->assertSame($initialStatus, $ticket->fresh()->status);
    }

    private function createTicket(array $overrides = []): Ticket
    {
        $division = Division::query()->first();
        $branch = Branch::query()->first();
        $app = Application::query()->first();
        $category = TicketCategory::query()->first();
        $priority = TicketPriority::query()->first();

        return Ticket::query()->create(array_merge([
            'ticket_number' => 'TIC-TEST-'.rand(100000, 999999),
            'requester_id' => $this->requester->id,
            'division_id' => $division->id,
            'current_division_id' => $division->id,
            'branch_id' => $branch->id,
            'application_id' => $app->id,
            'ticket_category_id' => $category->id,
            'requested_priority_id' => $priority->id,
            'title' => 'Test Ticket Title',
            'description' => 'Test Ticket Description details',
            'status' => TicketStatus::PendingValidation,
        ], $overrides));
    }

    private function createUser(string $roleKey): User
    {
        $role = Role::query()->where('key', $roleKey)->firstOrFail();

        return User::factory()->create([
            'role_id' => $role->id,
            'division_id' => Division::query()->first()?->id,
            'branch_id' => Branch::query()->first()?->id,
            'is_active' => true,
        ]);
    }
}
