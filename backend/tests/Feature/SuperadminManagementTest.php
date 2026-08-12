<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperadminManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_role_seeder_creates_superadmin_and_not_admin(): void
    {
        $this->assertDatabaseHas('roles', ['key' => 'superadmin', 'name' => 'Super Admin']);
        $this->assertDatabaseMissing('roles', ['key' => 'admin']);
    }

    public function test_superadmin_has_every_registered_permission(): void
    {
        $superadmin = $this->user('superadmin');
        $allPermissions = array_unique(array_merge(...array_values(config('permissions.roles'))));

        foreach ($allPermissions as $permission) {
            $this->assertTrue($superadmin->hasPermission($permission), "Super Admin tidak memiliki {$permission}");
        }
    }

    public function test_superadmin_can_soft_delete_another_account_but_not_self(): void
    {
        $superadmin = $this->user('superadmin');
        $requester = $this->user('requester');

        $this->actingAs($superadmin)->deleteJson("/api/v1/admin/users/{$requester->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Akun berhasil dihapus.');

        $this->assertSoftDeleted('users', ['id' => $requester->id]);
        $this->assertDatabaseHas('users', ['id' => $requester->id, 'is_active' => false]);

        $this->actingAs($superadmin)->deleteJson("/api/v1/admin/users/{$superadmin->id}")
            ->assertConflict()
            ->assertJsonPath('error.code', 'SELF_DELETE_FORBIDDEN');

        $this->assertNotSoftDeleted('users', ['id' => $superadmin->id]);
    }

    public function test_non_superadmin_cannot_delete_accounts_or_roles(): void
    {
        $requester = $this->user('requester');
        $target = $this->user('pic_it_support');
        $role = Role::query()->where('key', 'executive')->firstOrFail();

        $this->actingAs($requester)->deleteJson("/api/v1/admin/users/{$target->id}")->assertForbidden();
        $this->actingAs($requester)->deleteJson("/api/v1/admin/roles/{$role->id}")->assertForbidden();
    }

    public function test_superadmin_role_is_protected_and_used_role_cannot_be_deleted(): void
    {
        $superadmin = $this->user('superadmin');
        $requester = $this->user('requester');

        $this->actingAs($superadmin)->deleteJson("/api/v1/admin/roles/{$superadmin->role_id}")
            ->assertConflict()
            ->assertJsonPath('error.code', 'PROTECTED_ROLE');

        $this->actingAs($superadmin)->deleteJson("/api/v1/admin/roles/{$requester->role_id}")
            ->assertConflict()
            ->assertJsonPath('error.code', 'ROLE_IN_USE');
    }

    public function test_superadmin_can_soft_delete_unused_role(): void
    {
        $superadmin = $this->user('superadmin');
        $role = Role::query()->create([
            'key' => 'temporary_reviewer',
            'name' => 'Temporary Reviewer',
            'is_active' => true,
        ]);

        $this->actingAs($superadmin)->deleteJson("/api/v1/admin/roles/{$role->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Role berhasil dihapus.');

        $this->assertSoftDeleted('roles', ['id' => $role->id]);
    }

    private function user(string $roleKey): User
    {
        return User::factory()->create([
            'role_id' => Role::query()->where('key', $roleKey)->firstOrFail()->id,
            'is_active' => true,
        ]);
    }
}
