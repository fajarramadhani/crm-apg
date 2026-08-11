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

class PicAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private User $supervisorIt;

    private User $picSupport;

    private User $picDevelop;

    private User $unassignedPic;

    private User $requester;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, MasterDataSeeder::class]);

        $this->requester = $this->createUser('requester');
        $this->supervisorIt = $this->createUser('supervisor_it');
        $this->picSupport = $this->createUser('pic_it_support');
        $this->picDevelop = $this->createUser('pic_it_develop');
        $this->unassignedPic = $this->createUser('pic_it_support');
    }

    public function test_primary_pic_can_view_ticket(): void
    {
        $ticket = $this->createTicket();

        $this->actingAs($this->supervisorIt)
            ->postJson("/api/v1/supervisor-it/tickets/{$ticket->id}/assign-primary", [
                'user_id' => $this->picSupport->id,
            ])->assertOk();

        $this->actingAs($this->picSupport)
            ->getJson("/api/v1/pic/tickets/{$ticket->id}")
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_secondary_pic_can_view_ticket(): void
    {
        $ticket = $this->createTicket();

        $this->actingAs($this->supervisorIt)
            ->postJson("/api/v1/supervisor-it/tickets/{$ticket->id}/assign-primary", [
                'user_id' => $this->picDevelop->id,
            ])->assertOk();

        $this->actingAs($this->supervisorIt)
            ->postJson("/api/v1/supervisor-it/tickets/{$ticket->id}/secondary-assignees", [
                'user_id' => $this->picSupport->id,
            ])->assertOk();

        $this->actingAs($this->picSupport)
            ->getJson("/api/v1/pic/tickets/{$ticket->id}")
            ->assertOk()
            ->assertJsonPath('success', true);

        // Also check index listing includes secondary assignment
        $indexRes = $this->actingAs($this->picSupport)
            ->getJson('/api/v1/pic/assignments');

        $indexRes->assertOk();
        $ids = collect($indexRes->json('data'))->pluck('id')->all();
        $this->assertContains($ticket->id, $ids);
    }

    public function test_pic_without_assignment_gets_403(): void
    {
        $ticket = $this->createTicket();

        $this->actingAs($this->supervisorIt)
            ->postJson("/api/v1/supervisor-it/tickets/{$ticket->id}/assign-primary", [
                'user_id' => $this->picSupport->id,
            ])->assertOk();

        $this->actingAs($this->unassignedPic)
            ->getJson("/api/v1/pic/tickets/{$ticket->id}")
            ->assertStatus(403);
    }

    public function test_pic_support_can_handle_internal_systems_if_assigned(): void
    {
        $internalApp = Application::query()->firstOrCreate(['code' => 'SYS_INTERNAL'], ['name' => 'Internal System', 'is_active' => true]);
        $ticket = $this->createTicket(['application_id' => $internalApp->id]);

        $this->actingAs($this->supervisorIt)
            ->postJson("/api/v1/supervisor-it/tickets/{$ticket->id}/assign-primary", [
                'user_id' => $this->picSupport->id,
            ])->assertOk();

        $this->actingAs($this->picSupport)
            ->getJson("/api/v1/pic/tickets/{$ticket->id}")
            ->assertOk();
    }

    public function test_pic_develop_can_handle_insurance_systems_if_assigned(): void
    {
        $insuranceApp = Application::query()->firstOrCreate(['code' => 'SYS_INSURANCE'], ['name' => 'Insurance Core System', 'is_active' => true]);
        $ticket = $this->createTicket(['application_id' => $insuranceApp->id]);

        $this->actingAs($this->supervisorIt)
            ->postJson("/api/v1/supervisor-it/tickets/{$ticket->id}/assign-primary", [
                'user_id' => $this->picDevelop->id,
            ])->assertOk();

        $this->actingAs($this->picDevelop)
            ->getJson("/api/v1/pic/tickets/{$ticket->id}")
            ->assertOk();
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
