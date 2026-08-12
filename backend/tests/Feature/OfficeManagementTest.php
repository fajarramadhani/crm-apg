<?php

namespace Tests\Feature;

use App\Models\Office;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\OfficeSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfficeManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, OfficeSeeder::class]);
    }

    public function test_only_admin_can_access_office_management(): void
    {
        $this->getJson('/api/v1/admin/offices')->assertUnauthorized();
        $this->actingAs($this->user('requester'))->getJson('/api/v1/admin/offices')->assertForbidden();
        $this->actingAs($this->user('superadmin'))->getJson('/api/v1/admin/offices')->assertOk();
    }

    public function test_admin_can_filter_create_show_and_update_offices(): void
    {
        $admin = $this->user('superadmin');

        $this->actingAs($admin)->getJson('/api/v1/admin/offices?office_type=cabang')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonMissing(['office_type' => 'pusat']);

        $created = $this->actingAs($admin)->postJson('/api/v1/admin/offices', [
            'name' => '  Surabaya  ',
            'office_type' => 'cabang',
            'is_active' => false,
        ])->assertCreated()
            ->assertJsonPath('data.name', 'Surabaya')
            ->assertJsonMissingPath('data.is_active');

        $id = $created->json('data.id');
        $this->actingAs($admin)->getJson("/api/v1/admin/offices/{$id}")->assertOk();
        $this->actingAs($admin)->putJson("/api/v1/admin/offices/{$id}", [
            'name' => 'Surabaya Barat',
            'office_type' => 'cabang',
        ])->assertOk()->assertJsonPath('data.name', 'Surabaya Barat');

        $this->actingAs($admin)->getJson('/api/v1/admin/offices/999999')->assertNotFound();
    }

    public function test_duplicate_office_and_invalid_type_are_rejected(): void
    {
        $admin = $this->user('superadmin');
        $this->actingAs($admin)->postJson('/api/v1/admin/offices', [
            'name' => 'Bandung',
            'office_type' => 'cabang',
        ])->assertUnprocessable()->assertJsonValidationErrors('name');

        $this->actingAs($admin)->postJson('/api/v1/admin/offices', [
            'name' => 'Bogor',
            'office_type' => 'regional',
        ])->assertUnprocessable()->assertJsonValidationErrors('office_type');
    }

    public function test_office_used_by_user_and_only_head_office_cannot_be_deleted(): void
    {
        $admin = $this->user('superadmin');
        $branch = Office::query()->cabang()->firstOrFail();
        User::factory()->create(['office_id' => $branch->id]);

        $this->actingAs($admin)->deleteJson("/api/v1/admin/offices/{$branch->id}")
            ->assertConflict()
            ->assertJsonPath('message', 'Kantor tidak dapat dihapus karena masih digunakan oleh akun pengguna.');

        $headOffice = Office::query()->pusat()->firstOrFail();
        $this->actingAs($admin)->deleteJson("/api/v1/admin/offices/{$headOffice->id}")
            ->assertConflict()
            ->assertJsonPath('error.code', 'LAST_HEAD_OFFICE');
    }

    public function test_only_head_office_cannot_be_changed_into_branch(): void
    {
        $admin = $this->user('superadmin');
        $headOffice = Office::query()->pusat()->firstOrFail();

        $this->actingAs($admin)->putJson("/api/v1/admin/offices/{$headOffice->id}", [
            'name' => $headOffice->name,
            'office_type' => 'cabang',
        ])->assertConflict()->assertJsonPath('error.code', 'LAST_HEAD_OFFICE');

        $this->assertDatabaseHas('offices', ['id' => $headOffice->id, 'office_type' => 'pusat']);
    }

    public function test_unused_branch_and_one_of_multiple_head_offices_can_be_deleted(): void
    {
        $admin = $this->user('superadmin');
        $branch = Office::query()->create(['name' => 'Medan', 'office_type' => 'cabang']);
        $secondHead = Office::query()->create(['name' => 'Kantor Pusat Dua', 'office_type' => 'pusat']);

        $this->actingAs($admin)->deleteJson("/api/v1/admin/offices/{$branch->id}")->assertOk();
        $this->actingAs($admin)->deleteJson("/api/v1/admin/offices/{$secondHead->id}")->assertOk();
        $this->assertDatabaseMissing('offices', ['id' => $branch->id]);
        $this->assertSame(1, Office::query()->pusat()->count());
    }

    private function user(string $roleKey): User
    {
        return User::factory()->create([
            'role_id' => Role::query()->where('key', $roleKey)->firstOrFail()->id,
            'is_active' => true,
        ]);
    }
}
