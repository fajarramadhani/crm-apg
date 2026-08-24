<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ChangePasswordTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_requires_authentication(): void
    {
        $this->postJson('/api/v1/auth/change-password', [
            'current_password' => 'old-password-123',
            'password' => 'new-password-456',
            'password_confirmation' => 'new-password-456',
        ])->assertUnauthorized();
    }

    public function test_rejects_wrong_current_password(): void
    {
        $user = $this->createUser(mustChangePassword: true);

        $this->actingAs($user)->postJson('/api/v1/auth/change-password', [
            'current_password' => 'wrong-current-pass',
            'password' => 'new-password-456',
            'password_confirmation' => 'new-password-456',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('current_password');

        $this->assertTrue($user->fresh()->must_change_password);
    }

    public function test_rejects_new_password_that_fails_policy(): void
    {
        $user = $this->createUser(mustChangePassword: true);

        $this->actingAs($user)->postJson('/api/v1/auth/change-password', [
            'current_password' => 'old-password-123',
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertUnprocessable()->assertJsonValidationErrors('password');

        $sameAsCurrent = $this->actingAs($user)->postJson('/api/v1/auth/change-password', [
            'current_password' => 'old-password-123',
            'password' => 'old-password-123',
            'password_confirmation' => 'old-password-123',
        ])->assertUnprocessable();

        unset($sameAsCurrent);
        $this->assertTrue(Hash::check('old-password-123', $user->fresh()->password));
    }

    public function test_changes_password_clears_flag_and_writes_audit_log(): void
    {
        $user = $this->createUser(mustChangePassword: true);

        $response = $this->actingAs($user)->postJson('/api/v1/auth/change-password', [
            'current_password' => 'old-password-123',
            'password' => 'new-password-456',
            'password_confirmation' => 'new-password-456',
        ]);

        $response->assertOk()->assertJsonPath('data.user.must_change_password', false);

        $fresh = $user->fresh();
        $this->assertFalse($fresh->must_change_password);
        $this->assertFalse(Hash::check('old-password-123', $fresh->password));
        $this->assertTrue(Hash::check('new-password-456', $fresh->password));

        $this->assertDatabaseHas(AuditLog::class, [
            'event' => 'identity.password_changed',
            'actor_id' => $user->id,
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
        ]);
    }

    public function test_me_endpoint_exposes_must_change_password_flag(): void
    {
        $user = $this->createUser(mustChangePassword: true);

        $this->actingAs($user)->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.user.must_change_password', true);
    }

    private function createUser(bool $mustChangePassword = false): User
    {
        return User::query()->create([
            'role_id' => Role::query()->where('key', 'requester')->firstOrFail()->id,
            'name' => 'Requester Test',
            'email' => 'change-password@apg.test',
            'password' => Hash::make('old-password-123'),
            'is_active' => true,
            'must_change_password' => $mustChangePassword,
        ]);
    }
}
