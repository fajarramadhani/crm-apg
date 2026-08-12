<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Branch;
use App\Models\Division;
use App\Models\Office;
use App\Models\Role;
use App\Models\TicketCategory;
use App\Models\TicketPriority;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkingCalendar;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DefaultWorkflowSeeder;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_seeder_creates_canonical_roles_offices_and_requester_systems(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(11, Role::query()->count());
        $this->assertSame(0, User::query()->count());
        $this->assertSame(4, Office::query()->count());
        $this->assertSame(0, Division::query()->count());
        $this->assertSame(0, Branch::query()->count());
        $this->assertSame(7, Application::query()->count());
        $this->assertSame(0, TicketCategory::query()->count());
        $this->assertSame(0, TicketPriority::query()->count());
        $this->assertSame(0, WorkingCalendar::query()->count());
    }

    public function test_master_data_seeder_is_idempotent_and_preserves_user_roles_and_workflow_state(): void
    {
        $this->seed([RoleSeeder::class, MasterDataSeeder::class, DefaultWorkflowSeeder::class]);

        $role = Role::query()->where('key', 'requester')->firstOrFail();
        $user = User::factory()->create(['role_id' => $role->id]);
        $workflow = WorkflowDefinition::query()->where('code', 'crm_default')->where('version', 1)->firstOrFail();
        $initialCounts = [
            'divisions' => Division::query()->count(),
            'branches' => Branch::query()->count(),
            'applications' => Application::query()->count(),
            'ticket_categories' => TicketCategory::query()->count(),
            'ticket_priorities' => TicketPriority::query()->count(),
            'working_calendars' => WorkingCalendar::query()->count(),
        ];

        $this->seed(MasterDataSeeder::class);

        $this->assertSame($initialCounts['divisions'], Division::query()->count());
        $this->assertSame($initialCounts['branches'], Branch::query()->count());
        $this->assertSame($initialCounts['applications'], Application::query()->count());
        $this->assertSame($initialCounts['ticket_categories'], TicketCategory::query()->count());
        $this->assertSame($initialCounts['ticket_priorities'], TicketPriority::query()->count());
        $this->assertSame($initialCounts['working_calendars'], WorkingCalendar::query()->count());
        $this->assertSame($role->id, $user->fresh()->role_id);
        $this->assertFalse($workflow->fresh()->is_active);
        $this->assertSame(0, WorkflowDefinition::query()->where('is_active', true)->count());
        $this->assertSame(TicketPriority::query()->count(), TicketPriority::query()->distinct('key')->count('key'));
        $this->assertSame(TicketPriority::query()->count(), TicketPriority::query()->distinct('level')->count('level'));
    }
}
