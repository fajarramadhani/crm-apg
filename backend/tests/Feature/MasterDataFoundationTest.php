<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Division;
use App\Models\Role;
use App\Models\TicketCategory;
use App\Models\TicketPriority;
use App\Models\User;
use App\Models\WorkingCalendar;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterDataFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, MasterDataSeeder::class]);
    }

    public function test_all_master_endpoints_require_authentication_and_have_request_id(): void
    {
        foreach (['divisions', 'branches', 'applications', 'ticket-categories', 'ticket-priorities', 'sla-policies', 'working-calendars', 'holidays'] as $endpoint) {
            $this->getJson("/api/v1/master/{$endpoint}")->assertUnauthorized()->assertJsonStructure(['meta' => ['request_id']]);
        }
        $application = Application::firstOrFail();
        $this->getJson("/api/v1/master/applications/{$application->id}/modules")->assertUnauthorized();
    }

    public function test_each_authenticated_role_can_read_active_master_data_but_only_admin_can_manage_it(): void
    {
        foreach (array_keys(config('permissions.roles')) as $roleKey) {
            $user = $this->user($roleKey);
            $this->actingAs($user)->getJson('/api/v1/master/divisions')->assertOk()->assertJsonPath('success', true)->assertJsonStructure(['data' => [['id', 'code', 'name', 'is_active']], 'meta' => ['request_id']]);
            if ($roleKey !== 'superadmin') {
                $this->actingAs($user)->postJson('/api/v1/admin/divisions', ['code' => 'DENIED', 'name' => 'Denied'])->assertForbidden()->assertJsonPath('error.code', 'FORBIDDEN');
            }
        }
        $this->assertContains('master_data.view', $this->user('executive')->permissions());
        $this->assertNotContains('master_data.manage', $this->user('executive')->permissions());
    }

    public function test_admin_can_create_update_and_deactivate_division_with_standard_resources(): void
    {
        $admin = $this->user('superadmin');
        $created = $this->actingAs($admin)->postJson('/api/v1/admin/divisions', ['code' => 'tech', 'name' => 'Technology']);
        $created->assertCreated()->assertJsonPath('data.code', 'TECH')->assertJsonPath('data.name', 'Technology')->assertJsonStructure(['meta' => ['request_id']]);
        $id = $created->json('data.id');
        $this->actingAs($admin)->putJson("/api/v1/admin/divisions/{$id}", ['code' => 'TECH', 'name' => 'Technology Services'])->assertOk()->assertJsonPath('data.name', 'Technology Services');
        $this->actingAs($admin)->deleteJson("/api/v1/admin/divisions/{$id}")->assertOk()->assertJsonPath('data.is_active', false);
        $this->assertDatabaseHas('divisions', ['id' => $id, 'is_active' => false]);
    }

    public function test_division_code_is_unique_and_parent_cannot_reference_itself(): void
    {
        $admin = $this->user('superadmin');
        $division = Division::firstOrFail();
        $this->actingAs($admin)->postJson('/api/v1/admin/divisions', ['code' => $division->code, 'name' => 'Duplicate'])->assertUnprocessable()->assertJsonValidationErrors('code')->assertJsonStructure(['meta' => ['request_id']]);
        $this->actingAs($admin)->putJson("/api/v1/admin/divisions/{$division->id}", ['code' => $division->code, 'name' => $division->name, 'parent_id' => $division->id])->assertUnprocessable()->assertJsonValidationErrors('parent_id');
    }

    public function test_admin_can_create_application_and_module_for_valid_application(): void
    {
        $admin = $this->user('superadmin');
        $division = Division::active()->firstOrFail();
        $application = $this->actingAs($admin)->postJson('/api/v1/admin/applications', ['code' => 'HELPDESK', 'name' => 'Helpdesk', 'owner_division_id' => $division->id])->assertCreated();
        $applicationId = $application->json('data.id');
        $this->actingAs($admin)->postJson("/api/v1/admin/applications/{$applicationId}/modules", ['code' => 'TICKET', 'name' => 'Ticketing'])->assertCreated()->assertJsonPath('data.application_id', $applicationId);
        $this->actingAs($admin)->postJson('/api/v1/admin/applications/999999/modules', ['code' => 'INVALID', 'name' => 'Invalid'])->assertNotFound();
    }

    public function test_admin_can_create_category_and_priority_with_domain_validation(): void
    {
        $admin = $this->user('superadmin');
        $this->actingAs($admin)->postJson('/api/v1/admin/ticket-categories', ['code' => 'SECURITY', 'name' => 'Security Incident', 'type' => 'incident'])->assertCreated()->assertJsonPath('data.type', 'incident');
        $this->actingAs($admin)->postJson('/api/v1/admin/ticket-categories', ['code' => 'BAD', 'name' => 'Bad', 'type' => 'unknown'])->assertUnprocessable()->assertJsonValidationErrors('type');
        $this->actingAs($admin)->postJson('/api/v1/admin/ticket-priorities', ['key' => 'informational', 'name' => 'Informational', 'level' => 10])->assertCreated()->assertJsonPath('data.level', 10);
    }

    public function test_sla_policy_requires_positive_duration_and_rejects_active_duplicate(): void
    {
        $admin = $this->user('superadmin');
        $priority = TicketPriority::where('key', 'critical')->firstOrFail();
        $calendar = WorkingCalendar::firstOrFail();
        $base = ['priority_id' => $priority->id, 'working_calendar_id' => $calendar->id, 'is_active' => true];
        $this->actingAs($admin)->postJson('/api/v1/admin/sla-policies', [...$base, 'resolution_minutes' => 0])->assertUnprocessable()->assertJsonValidationErrors('resolution_minutes');
        $this->actingAs($admin)->postJson('/api/v1/admin/sla-policies', [...$base, 'resolution_minutes' => 240])->assertUnprocessable()->assertJsonValidationErrors('priority_id');
    }

    public function test_working_calendar_and_duplicate_holiday_are_validated(): void
    {
        $admin = $this->user('superadmin');
        $invalid = ['code' => 'INVALID_CAL', 'name' => 'Invalid', 'timezone' => 'Asia/Jakarta', 'workday_start' => '17:00', 'workday_end' => '08:00', 'working_days' => [1, 1, 8]];
        $this->actingAs($admin)->postJson('/api/v1/admin/working-calendars', $invalid)->assertUnprocessable()->assertJsonValidationErrors(['workday_end', 'working_days.1', 'working_days.2']);
        $calendar = WorkingCalendar::firstOrFail();
        $holiday = ['date' => '2027-01-01', 'name' => 'Development Test Holiday', 'is_recurring' => false];
        $this->actingAs($admin)->postJson("/api/v1/admin/working-calendars/{$calendar->id}/holidays", $holiday)->assertCreated()->assertJsonPath('data.date', '2027-01-01');
        $this->actingAs($admin)->postJson("/api/v1/admin/working-calendars/{$calendar->id}/holidays", $holiday)->assertUnprocessable()->assertJsonValidationErrors('date');
    }

    public function test_inactive_data_is_hidden_from_master_dropdown_but_visible_in_admin_list(): void
    {
        $category = TicketCategory::where('code', 'INCIDENT')->firstOrFail();
        $category->update(['is_active' => false]);
        $requester = $this->user('requester');
        $this->actingAs($requester)->getJson('/api/v1/master/ticket-categories')->assertOk()->assertJsonMissing(['code' => 'INCIDENT']);
        $admin = $this->user('superadmin');
        $this->actingAs($admin)->getJson('/api/v1/admin/ticket-categories?is_active=0')->assertOk()->assertJsonFragment(['code' => 'INCIDENT', 'is_active' => false]);
    }

    public function test_deactivation_preserves_active_relation_consistency(): void
    {
        $admin = $this->user('superadmin');
        $priority = TicketPriority::where('key', 'critical')->firstOrFail();
        $this->actingAs($admin)->deleteJson("/api/v1/admin/ticket-priorities/{$priority->id}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('priority');

        $application = Application::firstOrFail();
        $module = $application->modules()->create(['code' => 'DEACTIVATE_TEST', 'name' => 'Deactivate Test', 'is_active' => true]);
        $this->actingAs($admin)->deleteJson("/api/v1/admin/applications/{$application->id}")
            ->assertOk()
            ->assertJsonPath('data.is_active', false);
        $this->assertFalse($module->fresh()->is_active);
    }

    public function test_admin_list_supports_search_pagination_and_bounded_page_size(): void
    {
        $admin = $this->user('superadmin');
        $this->actingAs($admin)->getJson('/api/v1/admin/divisions?search=technology&is_active=1&per_page=2')->assertOk()->assertJsonPath('meta.pagination.per_page', 2)->assertJsonPath('data.0.code', 'IT');
        $this->actingAs($admin)->getJson('/api/v1/admin/divisions?per_page=101')->assertUnprocessable()->assertJsonValidationErrors('per_page');
    }

    public function test_master_data_seeder_is_idempotent_and_foreign_keys_are_present(): void
    {
        $this->seed(MasterDataSeeder::class);
        $this->assertDatabaseCount('ticket_priorities', 4);
        $this->assertDatabaseCount('sla_policies', 4);
        $this->assertDatabaseHas('sla_policies', ['resolution_minutes' => 2400]);
        $application = Application::whereNotNull('owner_division_id')->firstOrFail();
        $this->assertNotNull($application->ownerDivision);
    }

    private function user(string $roleKey): User
    {
        $role = Role::where('key', $roleKey)->firstOrFail();

        return User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
    }
}
