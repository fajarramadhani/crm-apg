<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $this->seed(\Database\Seeders\MasterDataSeeder::class);
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
}
