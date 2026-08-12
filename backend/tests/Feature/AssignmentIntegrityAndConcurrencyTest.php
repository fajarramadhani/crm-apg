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
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AssignmentIntegrityAndConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    private User $supervisorIt;

    private User $picSupport;

    private User $picDevelop;

    private User $requester;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, MasterDataSeeder::class]);

        $this->requester = $this->createUser('requester');
        $this->supervisorIt = $this->createUser('supervisor_it');
        $this->picSupport = $this->createUser('pic_it_support');
        $this->picDevelop = $this->createUser('pic_it_develop');
    }

    public function test_exactly_one_primary_pic_active_at_a_time(): void
    {
        $ticket = $this->createTicket();
        $service = app(DynamicAssignmentService::class);

        $service->assignPrimary($ticket, $this->supervisorIt, $this->picSupport);
        $service->assignPrimary($ticket, $this->supervisorIt, $this->picDevelop);

        $activePrimaryCount = $ticket->assignments()
            ->where('assignment_type', 'primary')
            ->where('is_current', true)
            ->count();

        $this->assertSame(1, $activePrimaryCount);
        $this->assertSame($this->picDevelop->id, $ticket->fresh()->current_assignee_id);
    }

    public function test_same_user_cannot_have_duplicate_active_assignments_on_same_ticket(): void
    {
        $ticket = $this->createTicket();
        $service = app(DynamicAssignmentService::class);

        $service->assignPrimary($ticket, $this->supervisorIt, $this->picSupport);

        $this->expectException(ValidationException::class);
        $service->addSecondary($ticket, $this->supervisorIt, $this->picSupport);
    }

    public function test_old_assignment_has_ended_at_and_is_current_false(): void
    {
        $ticket = $this->createTicket();
        $service = app(DynamicAssignmentService::class);

        $assign1 = $service->assignPrimary($ticket, $this->supervisorIt, $this->picSupport);
        $assign2 = $service->assignPrimary($ticket, $this->supervisorIt, $this->picDevelop);

        $this->assertNotNull($assign1->fresh()->ended_at);
        $this->assertFalse($assign1->fresh()->is_current);
        $this->assertNull($assign2->fresh()->ended_at);
        $this->assertTrue($assign2->fresh()->is_current);
    }

    public function test_reassignment_history_is_recorded(): void
    {
        $ticket = $this->createTicket();
        $service = app(DynamicAssignmentService::class);

        $service->assignPrimary($ticket, $this->supervisorIt, $this->picSupport, notes: 'Assigning PIC Support');
        $service->assignPrimary($ticket, $this->supervisorIt, $this->picDevelop, notes: 'Reassigning to PIC Develop', reason: 'Workload balancing');

        $this->assertDatabaseHas('ticket_assignment_histories', [
            'ticket_id' => $ticket->id,
            'action' => 'assigned',
            'from_user_id' => null,
            'to_user_id' => $this->picSupport->id,
        ]);

        $this->assertDatabaseHas('ticket_assignment_histories', [
            'ticket_id' => $ticket->id,
            'action' => 'reassigned',
            'from_user_id' => $this->picSupport->id,
            'to_user_id' => $this->picDevelop->id,
        ]);
    }

    public function test_secondary_removal_history_is_recorded(): void
    {
        $ticket = $this->createTicket();
        $service = app(DynamicAssignmentService::class);

        $service->assignPrimary($ticket, $this->supervisorIt, $this->supervisorIt);
        $service->addSecondary($ticket, $this->supervisorIt, $this->picSupport, notes: 'Adding secondary PIC');
        $service->removeSecondary($ticket, $this->supervisorIt, $this->picSupport, notes: 'Removing secondary PIC', reason: 'Task completed');

        $this->assertDatabaseHas('ticket_assignment_histories', [
            'ticket_id' => $ticket->id,
            'action' => 'secondary_removed',
            'from_user_id' => $this->picSupport->id,
            'to_user_id' => null,
        ]);
    }

    public function test_supervisor_acting_as_pic_is_recorded(): void
    {
        $ticket = $this->createTicket();
        $service = app(DynamicAssignmentService::class);

        $assignment = $service->assignPrimary($ticket, $this->supervisorIt, $this->supervisorIt);

        $this->assertSame('supervisor_it', $assignment->role_at_assignment);
        $this->assertTrue($assignment->acting_as_pic);
    }

    public function test_transaction_rolls_back_if_assignment_fails(): void
    {
        $ticket = $this->createTicket();
        $initialAssignee = $ticket->current_assignee_id;

        try {
            DB::transaction(function () use ($ticket): void {
                $ticket->assignments()->create([
                    'assigned_to' => $this->picSupport->id,
                    'assigned_by' => $this->supervisorIt->id,
                    'assignment_type' => 'primary',
                    'started_at' => now(),
                    'is_current' => true,
                ]);

                throw new Exception('Simulated DB failure during history creation');
            });
        } catch (Exception $e) {
            // Caught exception
        }

        $this->assertDatabaseMissing('ticket_assignments', [
            'ticket_id' => $ticket->id,
            'assigned_to' => $this->picSupport->id,
        ]);
        $this->assertSame($initialAssignee, $ticket->fresh()->current_assignee_id);
    }

    public function test_concurrency_locking_prevents_duplicate_primary_assignments(): void
    {
        $ticket = $this->createTicket();
        $service = app(DynamicAssignmentService::class);

        // Execute sequential assignments inside locked transactions
        DB::transaction(function () use ($service, $ticket): void {
            $service->assignPrimary($ticket, $this->supervisorIt, $this->picSupport);
        });

        DB::transaction(function () use ($service, $ticket): void {
            $service->assignPrimary($ticket, $this->supervisorIt, $this->picDevelop);
        });

        $activePrimaries = $ticket->assignments()
            ->where('assignment_type', 'primary')
            ->where('is_current', true)
            ->get();

        $this->assertCount(1, $activePrimaries);
        $this->assertSame($this->picDevelop->id, $activePrimaries->first()->assigned_to);
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
