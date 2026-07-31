<?php

namespace Tests\Feature;

use App\Models\Office;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\OfficeSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminAccountCreationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, OfficeSeeder::class]);
    }

    public function test_admin_can_create_head_office_requester_with_backend_role_and_hashed_password(): void
    {
        $response = $this->actingAs($this->user('superadmin'))->postJson('/api/v1/admin/users/requester', [
            ...$this->basePayload('pusat@apg.test'),
            'office_mode' => 'pusat',
            'role' => 'superadmin',
            'is_active' => false,
        ])->assertCreated()
            ->assertJsonPath('data.role.key', 'requester')
            ->assertJsonPath('data.office.office_type', 'pusat')
            ->assertJsonMissingPath('data.password');

        $user = User::query()->findOrFail($response->json('data.id'));
        $this->assertTrue(Hash::check('password12345', $user->password));
        $this->assertNotSame('password12345', $user->password);
        $this->assertTrue($user->is_active);
    }

    public function test_admin_can_create_branch_requester_and_branch_is_required_and_type_checked(): void
    {
        $admin = $this->user('superadmin');
        $branch = Office::query()->cabang()->firstOrFail();

        $this->actingAs($admin)->postJson('/api/v1/admin/users/requester', [
            ...$this->basePayload('branch@apg.test'),
            'office_mode' => 'cabang',
            'office_id' => $branch->id,
        ])->assertCreated()->assertJsonPath('data.office.id', $branch->id);

        $this->actingAs($admin)->postJson('/api/v1/admin/users/requester', [
            ...$this->basePayload('missing@apg.test'),
            'office_mode' => 'cabang',
        ])->assertUnprocessable()->assertJsonValidationErrors('office_id');

        $this->actingAs($admin)->postJson('/api/v1/admin/users/requester', [
            ...$this->basePayload('wrong@apg.test'),
            'office_mode' => 'cabang',
            'office_id' => Office::query()->pusat()->firstOrFail()->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('office_id');

        $this->actingAs($admin)->postJson('/api/v1/admin/users/requester', [
            ...$this->basePayload('fake@apg.test'),
            'office_mode' => 'cabang',
            'office_id' => 999999,
        ])->assertUnprocessable()->assertJsonValidationErrors('office_id');
    }

    public function test_requester_creation_fails_safely_without_head_office(): void
    {
        Office::query()->pusat()->delete();

        $this->actingAs($this->user('superadmin'))->postJson('/api/v1/admin/users/requester', [
            ...$this->basePayload('no-head@apg.test'),
            'office_mode' => 'pusat',
        ])->assertUnprocessable()->assertJsonValidationErrors('office_id');
    }

    public function test_admin_can_only_create_whitelisted_it_roles(): void
    {
        $admin = $this->user('superadmin');
        foreach (['supervisor_it', 'pic_it_develop', 'pic_it_support'] as $index => $role) {
            $this->actingAs($admin)->postJson('/api/v1/admin/users/it', [
                ...$this->basePayload("it{$index}@apg.test"),
                'role' => $role,
                'office_id' => Office::query()->firstOrFail()->id,
            ])->assertCreated()->assertJsonPath('data.role.key', $role)->assertJsonPath('data.office', null);
        }

        foreach (['superadmin', 'requester', 'qa'] as $index => $role) {
            $this->actingAs($admin)->postJson('/api/v1/admin/users/it', [
                ...$this->basePayload("invalid{$index}@apg.test"),
                'role' => $role,
            ])->assertUnprocessable()->assertJsonValidationErrors('role');
        }

        $this->actingAs($admin)->postJson('/api/v1/admin/users', [])->assertMethodNotAllowed();
    }

    public function test_duplicate_email_password_mismatch_and_non_admin_access_are_rejected(): void
    {
        $payload = [...$this->basePayload('forbidden@apg.test'), 'role' => 'supervisor_it'];
        $this->postJson('/api/v1/admin/users/it', $payload)->assertUnauthorized();
        $this->actingAs($this->user('requester'))->postJson('/api/v1/admin/users/it', $payload)->assertForbidden();

        $admin = $this->user('superadmin');
        User::factory()->create(['email' => 'duplicate@apg.test']);

        $this->actingAs($admin)->postJson('/api/v1/admin/users/it', [
            ...$this->basePayload('duplicate@apg.test'),
            'role' => 'supervisor_it',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');

        $this->actingAs($admin)->postJson('/api/v1/admin/users/it', [
            ...$this->basePayload('mismatch@apg.test'),
            'password_confirmation' => 'different12345',
            'role' => 'supervisor_it',
        ])->assertUnprocessable()->assertJsonValidationErrors('password');
    }

    private function basePayload(string $email): array
    {
        return [
            'name' => 'Test Account',
            'email' => $email,
            'phone' => '+62 812-3456-7890',
            'password' => 'password12345',
            'password_confirmation' => 'password12345',
        ];
    }

    private function user(string $roleKey): User
    {
        return User::factory()->create([
            'role_id' => Role::query()->where('key', $roleKey)->firstOrFail()->id,
            'is_active' => true,
        ]);
    }
}
