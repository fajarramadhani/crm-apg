<?php

namespace Tests\Feature;

use App\Models\Division;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(MasterDataSeeder::class);
    }

    private function user(string $role): User
    {
        return User::factory()->create(['role_id' => Role::where('key', $role)->firstOrFail()->id, 'is_active' => true]);
    }

    public function test_manager_can_access_manager_reports()
    {
        $manager = $this->user('manager');
        $response = $this->actingAs($manager)->getJson('/api/v1/reports/manager/summary');
        $response->assertStatus(200);
    }

    public function test_requester_is_forbidden_from_reports()
    {
        $requester = $this->user('requester');

        $this->actingAs($requester)->getJson('/api/v1/reports/manager/summary')->assertStatus(403);
        $this->actingAs($requester)->getJson('/api/v1/reports/it-lead/summary')->assertStatus(403);
        $this->actingAs($requester)->getJson('/api/v1/reports/executive/summary')->assertStatus(403);
        $this->actingAs($requester)->getJson('/api/v1/reports/supervisor/summary')->assertStatus(403);
        $this->actingAs($requester)->getJson('/api/v1/reports/pic/performance')->assertStatus(403);
    }

    public function test_executive_can_only_access_executive_reports_and_not_technical_ones()
    {
        $executive = $this->user('executive');

        $this->actingAs($executive)->getJson('/api/v1/reports/executive/summary')->assertStatus(200);
        $this->actingAs($executive)->getJson('/api/v1/reports/manager/summary')->assertStatus(403);
    }

    public function test_pic_can_access_own_performance_and_not_others()
    {
        $pic = $this->user('pic');

        $this->actingAs($pic)->getJson('/api/v1/reports/pic/performance')->assertStatus(200);
        $this->actingAs($pic)->getJson('/api/v1/reports/manager/summary')->assertStatus(403);
    }

    public function test_supervisor_can_access_supervisor_reports()
    {
        $supervisor = $this->user('supervisor');

        $this->actingAs($supervisor)->getJson('/api/v1/reports/supervisor/summary')->assertStatus(200);
        $this->actingAs($supervisor)->getJson('/api/v1/reports/manager/summary')->assertStatus(403);
    }

    public function test_manager_report_scope_cannot_be_overridden_to_another_division(): void
    {
        $managerDivision = Division::firstOrFail();
        $otherDivision = Division::query()->whereKeyNot($managerDivision->id)->first()
            ?? Division::create(['code' => 'OTHER', 'name' => 'Other Division', 'is_active' => true]);
        $manager = $this->user('manager');
        $manager->update(['division_id' => $managerDivision->id]);

        Ticket::factory()->create(['division_id' => $managerDivision->id, 'current_division_id' => $managerDivision->id]);
        Ticket::factory()->create(['division_id' => $otherDivision->id, 'current_division_id' => $otherDivision->id]);

        $this->actingAs($manager)
            ->getJson("/api/v1/reports/manager/summary?division_id={$otherDivision->id}")
            ->assertOk()
            ->assertJsonPath('filters.division_id', $managerDivision->id);
    }
}
