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
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupervisorItAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private User $supervisorIt;

    private User $picSupport;

    private User $picDevelop;

    private User $requester;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, MasterDataSeeder::class]);

        $divisions = Division::all();
        $branches = Branch::all();

        $div1 = $divisions->first();
        $div2 = $divisions->last();
        $branch1 = $branches->first();
        $branch2 = $branches->last();

        $this->supervisorIt = $this->createUser('supervisor_it', divisionId: $div1->id, branchId: $branch1->id);
        $this->picSupport = $this->createUser('pic_it_support', divisionId: $div1->id, branchId: $branch1->id);
        $this->picDevelop = $this->createUser('pic_it_develop', divisionId: $div2->id, branchId: $branch2->id);
        $this->requester = $this->createUser('requester', divisionId: $div1->id, branchId: $branch1->id);
        $this->admin = $this->createUser('admin', divisionId: $div1->id, branchId: $branch1->id);
    }

    public function test_supervisor_it_can_view_all_tickets_across_divisions_and_branches(): void
    {
        $ticket1 = $this->createTicket(['division_id' => $this->supervisorIt->division_id, 'branch_id' => $this->supervisorIt->branch_id]);
        $ticket2 = $this->createTicket(['division_id' => $this->picDevelop->division_id, 'branch_id' => $this->picDevelop->branch_id]);

        $res = $this->actingAs($this->supervisorIt)
            ->getJson('/api/v1/supervisor-it/tickets');

        $res->assertOk()
            ->assertJsonPath('success', true);

        $ids = collect($res->json('data'))->pluck('id')->all();
        $this->assertContains($ticket1->id, $ids);
        $this->assertContains($ticket2->id, $ids);
    }

    public function test_supervisor_it_can_assign_self_as_primary_pic(): void
    {
        $ticket = $this->createTicket();

        $res = $this->actingAs($this->supervisorIt)
            ->postJson("/api/v1/supervisor-it/tickets/{$ticket->id}/assign-primary", [
                'user_id' => $this->supervisorIt->id,
                'notes' => 'Assigning self as primary PIC',
                'reason' => 'Initial assignment',
            ]);

        $res->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('ticket_assignments', [
            'ticket_id' => $ticket->id,
            'assigned_to' => $this->supervisorIt->id,
            'assignment_type' => 'primary',
            'role_at_assignment' => 'supervisor_it',
            'acting_as_pic' => true,
            'is_current' => true,
        ]);

        $this->assertSame($this->supervisorIt->id, $ticket->fresh()->current_assignee_id);
    }

    public function test_supervisor_it_can_assign_self_as_secondary_pic(): void
    {
        $ticket = $this->createTicket();

        // Assign picSupport as primary first
        $this->actingAs($this->supervisorIt)
            ->postJson("/api/v1/supervisor-it/tickets/{$ticket->id}/assign-primary", [
                'user_id' => $this->picSupport->id,
            ])->assertOk();

        // Supervisor IT assigns self as secondary
        $res = $this->actingAs($this->supervisorIt)
            ->postJson("/api/v1/supervisor-it/tickets/{$ticket->id}/secondary-assignees", [
                'user_id' => $this->supervisorIt->id,
                'notes' => 'Adding self as secondary',
            ]);

        $res->assertOk();

        $this->assertDatabaseHas('ticket_assignments', [
            'ticket_id' => $ticket->id,
            'assigned_to' => $this->supervisorIt->id,
            'assignment_type' => 'secondary',
            'role_at_assignment' => 'supervisor_it',
            'acting_as_pic' => true,
            'is_current' => true,
        ]);
    }

    public function test_supervisor_it_can_assign_pic_it_support_and_pic_it_develop(): void
    {
        $ticket = $this->createTicket();

        $this->actingAs($this->supervisorIt)
            ->postJson("/api/v1/supervisor-it/tickets/{$ticket->id}/assign-primary", [
                'user_id' => $this->picSupport->id,
            ])->assertOk();

        $this->assertDatabaseHas('ticket_assignments', [
            'ticket_id' => $ticket->id,
            'assigned_to' => $this->picSupport->id,
            'role_at_assignment' => 'pic_it_support',
            'acting_as_pic' => false,
            'is_current' => true,
        ]);

        $this->actingAs($this->supervisorIt)
            ->postJson("/api/v1/supervisor-it/tickets/{$ticket->id}/reassign", [
                'user_id' => $this->picDevelop->id,
            ])->assertOk();

        $this->assertDatabaseHas('ticket_assignments', [
            'ticket_id' => $ticket->id,
            'assigned_to' => $this->picDevelop->id,
            'role_at_assignment' => 'pic_it_develop',
            'acting_as_pic' => false,
            'is_current' => true,
        ]);
    }

    public function test_supervisor_it_can_add_two_secondary_pics(): void
    {
        $ticket = $this->createTicket();

        $this->actingAs($this->supervisorIt)
            ->postJson("/api/v1/supervisor-it/tickets/{$ticket->id}/assign-primary", [
                'user_id' => $this->supervisorIt->id,
            ])->assertOk();

        $this->actingAs($this->supervisorIt)
            ->postJson("/api/v1/supervisor-it/tickets/{$ticket->id}/secondary-assignees", [
                'user_id' => $this->picSupport->id,
            ])->assertOk();

        $this->actingAs($this->supervisorIt)
            ->postJson("/api/v1/supervisor-it/tickets/{$ticket->id}/secondary-assignees", [
                'user_id' => $this->picDevelop->id,
            ])->assertOk();

        $this->assertEquals(2, $ticket->assignments()->where('assignment_type', 'secondary')->where('is_current', true)->count());
    }

    public function test_supervisor_it_can_replace_primary_pic(): void
    {
        $ticket = $this->createTicket();

        $this->actingAs($this->supervisorIt)
            ->postJson("/api/v1/supervisor-it/tickets/{$ticket->id}/assign-primary", [
                'user_id' => $this->picSupport->id,
            ])->assertOk();

        $this->actingAs($this->supervisorIt)
            ->postJson("/api/v1/supervisor-it/tickets/{$ticket->id}/reassign", [
                'user_id' => $this->picDevelop->id,
                'reason' => 'Replacing primary PIC',
            ])->assertOk();

        $this->assertDatabaseHas('ticket_assignments', [
            'ticket_id' => $ticket->id,
            'assigned_to' => $this->picSupport->id,
            'is_current' => false,
        ]);

        $this->assertDatabaseHas('ticket_assignments', [
            'ticket_id' => $ticket->id,
            'assigned_to' => $this->picDevelop->id,
            'is_current' => true,
        ]);

        $this->assertSame($this->picDevelop->id, $ticket->fresh()->current_assignee_id);
    }

    public function test_supervisor_it_can_takeover_ticket(): void
    {
        $ticket = $this->createTicket();

        $this->actingAs($this->supervisorIt)
            ->postJson("/api/v1/supervisor-it/tickets/{$ticket->id}/assign-primary", [
                'user_id' => $this->picSupport->id,
            ])->assertOk();

        $this->actingAs($this->supervisorIt)
            ->postJson("/api/v1/supervisor-it/tickets/{$ticket->id}/takeover", [
                'reason' => 'Taking over due to escalation',
            ])->assertOk();

        $this->assertDatabaseHas('ticket_assignments', [
            'ticket_id' => $ticket->id,
            'assigned_to' => $this->supervisorIt->id,
            'assignment_type' => 'primary',
            'role_at_assignment' => 'supervisor_it',
            'acting_as_pic' => true,
            'is_current' => true,
        ]);

        $this->assertDatabaseHas('ticket_assignment_histories', [
            'ticket_id' => $ticket->id,
            'action' => 'taken_over',
            'from_user_id' => $this->picSupport->id,
            'to_user_id' => $this->supervisorIt->id,
        ]);
    }

    public function test_rejects_requester_as_pic_candidate(): void
    {
        $ticket = $this->createTicket();

        $res = $this->actingAs($this->supervisorIt)
            ->postJson("/api/v1/supervisor-it/tickets/{$ticket->id}/assign-primary", [
                'user_id' => $this->requester->id,
            ]);

        $res->assertStatus(422)
            ->assertJsonValidationErrors(['user_id']);
    }

    public function test_rejects_admin_as_pic_candidate(): void
    {
        $ticket = $this->createTicket();

        $res = $this->actingAs($this->supervisorIt)
            ->postJson("/api/v1/supervisor-it/tickets/{$ticket->id}/assign-primary", [
                'user_id' => $this->admin->id,
            ]);

        $res->assertStatus(422)
            ->assertJsonValidationErrors(['user_id']);
    }

    public function test_rejects_inactive_user_as_pic_candidate(): void
    {
        $ticket = $this->createTicket();
        $inactivePic = $this->createUser('pic_it_support', isActive: false);

        $res = $this->actingAs($this->supervisorIt)
            ->postJson("/api/v1/supervisor-it/tickets/{$ticket->id}/assign-primary", [
                'user_id' => $inactivePic->id,
            ]);

        $res->assertStatus(422)
            ->assertJsonValidationErrors(['user_id']);
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

    private function createUser(string $roleKey, ?int $divisionId = null, ?int $branchId = null, bool $isActive = true): User
    {
        $role = Role::query()->where('key', $roleKey)->firstOrFail();

        return User::factory()->create([
            'role_id' => $role->id,
            'division_id' => $divisionId ?? Division::query()->first()?->id,
            'branch_id' => $branchId ?? Branch::query()->first()?->id,
            'is_active' => $isActive,
        ]);
    }
}
