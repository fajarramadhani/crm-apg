<?php

namespace Tests\Feature;

use App\Models\Office;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\OfficeSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(OfficeSeeder::class);
    }

    public function test_only_admin_can_manage_accounts(): void
    {
        $this->getJson('/api/v1/admin/users')->assertUnauthorized();
        $requester = $this->user('requester');
        $this->actingAs($requester)->getJson('/api/v1/admin/users')->assertForbidden();
        $this->actingAs($this->user('superadmin'))->getJson('/api/v1/admin/users')->assertOk();
    }

    public function test_admin_can_create_list_and_update_an_account(): void
    {
        $admin = $this->user('superadmin');
        $requesterRole = Role::query()->where('key', 'requester')->firstOrFail();
        $office = Office::query()->pusat()->firstOrFail();

        $created = $this->actingAs($admin)->postJson('/api/v1/admin/users/requester', [
            'office_mode' => 'pusat',
            'office_id' => $office->id,
            'name' => 'Default Requester',
            'email' => 'requester@apg.test',
            'phone' => '081234567890',
            'password' => 'password12345',
            'password_confirmation' => 'password12345',
        ])->assertCreated()->assertJsonPath('data.role.key', 'requester');

        $id = $created->json('data.id');
        $this->actingAs($admin)->getJson('/api/v1/admin/users?search=requester')
            ->assertOk()
            ->assertJsonPath('data.0.email', 'requester@apg.test')
            ->assertJsonPath('meta.pagination.total', 1);

        $this->actingAs($admin)->putJson("/api/v1/admin/users/{$id}", [
            'name' => 'Requester Updated',
            'email' => 'requester@apg.test',
            'role_id' => $requesterRole->id,
            'is_active' => false,
        ])->assertOk()->assertJsonPath('data.is_active', false);
    }

    public function test_account_validation_and_admin_lockout_guards_are_enforced(): void
    {
        $admin = $this->user('superadmin');
        $requesterRole = Role::query()->where('key', 'requester')->firstOrFail();

        $this->actingAs($admin)->postJson('/api/v1/admin/users/requester', [
            'office_mode' => 'pusat',
            'name' => 'Invalid',
            'email' => 'invalid',
            'phone' => 'x',
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertUnprocessable()->assertJsonValidationErrors(['email', 'phone', 'password']);

        $this->actingAs($admin)->putJson("/api/v1/admin/users/{$admin->id}", [
            'name' => $admin->name,
            'email' => $admin->email,
            'role_id' => $requesterRole->id,
            'is_active' => false,
        ])->assertUnprocessable();

        $this->assertDatabaseHas('users', ['id' => $admin->id, 'role_id' => $admin->role_id, 'is_active' => true]);
    }

    public function test_options_expose_default_workflow_roles_and_steps(): void
    {
        $response = $this->actingAs($this->user('superadmin'))->getJson('/api/v1/admin/users/options')->assertOk();
        $response->assertJsonPath('data.workflow_role_keys.0', 'requester')
            ->assertJsonFragment(['key' => 'supervisor_it', 'name' => 'Supervisor IT'])
            ->assertJsonCount(5, 'data.workflow');
    }

    private function user(string $roleKey): User
    {
        return User::factory()->create([
            'role_id' => Role::query()->where('key', $roleKey)->firstOrFail()->id,
            'is_active' => true,
        ]);
    }
}
