<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationAndAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_login_succeeds_and_returns_safe_user_role_permissions_and_request_id(): void
    {
        $user = $this->createUser('requester');

        $response = $this->spaPost('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.role.key', 'requester')
            ->assertJsonPath('data.user.permissions.0', 'dashboard.requester.view')
            ->assertJsonStructure(['meta' => ['request_id']])
            ->assertJsonMissingPath('data.user.password')
            ->assertJsonMissingPath('data.user.remember_token');

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_wrong_credentials_use_a_safe_generic_error(): void
    {
        $user = $this->createUser('requester');

        $this->spaPost('/api/v1/auth/login', ['email' => $user->email, 'password' => 'wrong'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_CREDENTIALS')
            ->assertJsonPath('message', 'Email atau password tidak sesuai.')
            ->assertJsonStructure(['meta' => ['request_id']]);
    }

    public function test_inactive_user_is_rejected(): void
    {
        $user = $this->createUser('requester', false);

        $this->spaPost('/api/v1/auth/login', ['email' => $user->email, 'password' => 'password'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_CREDENTIALS');
    }

    public function test_user_with_inactive_role_is_rejected(): void
    {
        $role = Role::query()->where('key', 'requester')->firstOrFail();
        $role->update(['is_active' => false]);
        $user = $this->createUser('requester');

        $this->spaPost('/api/v1/auth/login', ['email' => $user->email, 'password' => 'password'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_CREDENTIALS');
    }

    public function test_login_validation_uses_standard_envelope(): void
    {
        $this->spaPost('/api/v1/auth/login', ['email' => 'not-email', 'password' => ''])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['email', 'password'], 'meta' => ['request_id']]);
    }

    public function test_me_requires_authentication_and_returns_standard_json_401(): void
    {
        $this->getJson('/api/v1/auth/me')
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'UNAUTHENTICATED')
            ->assertJsonStructure(['meta' => ['request_id']]);
    }

    public function test_me_returns_role_and_permissions(): void
    {
        $user = $this->createUser('executive');

        $this->actingAs($user)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.user.role.key', 'executive')
            ->assertJsonPath('data.user.permissions.1', 'executive.aggregate.view');
    }

    public function test_logout_invalidates_the_session(): void
    {
        $user = $this->createUser('requester');

        $this->spaPost('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk();

        $this->spaPost('/api/v1/auth/logout', [])
            ->assertOk()
            ->assertJsonStructure(['meta' => ['request_id']]);

        $this->app['auth']->forgetGuards();

        $this->withHeaders([
            'Origin' => 'http://localhost:5173',
            'Referer' => 'http://localhost:5173/',
        ])->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_admin_endpoint_enforces_role(): void
    {
        $admin = $this->createUser('admin');
        $requester = $this->createUser('requester');

        $this->actingAs($admin)->getJson('/api/v1/protected/admin')->assertOk();
        $this->actingAs($requester)->getJson('/api/v1/protected/admin')
            ->assertForbidden()
            ->assertJsonPath('error.code', 'FORBIDDEN')
            ->assertJsonStructure(['meta' => ['request_id']]);
    }

    public function test_executive_can_access_aggregate_but_not_technical_details(): void
    {
        $executive = $this->createUser('executive');

        $this->actingAs($executive)->getJson('/api/v1/protected/executive')->assertOk();
        $this->actingAs($executive)->getJson('/api/v1/protected/technical-ticket-details')
            ->assertForbidden()
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    public function test_technical_role_can_access_technical_details(): void
    {
        $pic = $this->createUser('pic');

        $this->actingAs($pic)->getJson('/api/v1/protected/technical-ticket-details')->assertOk();
    }

    public function test_login_is_rate_limited_after_repeated_attempts(): void
    {
        $payload = ['email' => 'unknown@tichub.local', 'password' => 'wrong'];

        foreach (range(1, 5) as $attempt) {
            $this->spaPost('/api/v1/auth/login', $payload)->assertStatus(422);
        }

        $this->spaPost('/api/v1/auth/login', $payload)
            ->assertStatus(429)
            ->assertJsonPath('error.code', 'TOO_MANY_ATTEMPTS')
            ->assertJsonStructure(['meta' => ['request_id']]);
    }

    public function test_all_roles_expose_the_configured_permissions(): void
    {
        foreach (config('permissions.roles') as $roleKey => $expectedPermissions) {
            $user = $this->createUser($roleKey);

            $this->assertSame($expectedPermissions, $user->permissions(), "Permission mismatch for {$roleKey}");
        }

        $this->assertCount(8, config('permissions.roles'));
    }

    public function test_testing_user_command_provisions_one_named_account_without_overwriting_it(): void
    {
        $password = 'Temporary-Testing-Password-42';
        putenv("TIC_HUB_BOOTSTRAP_PASSWORD={$password}");

        try {
            $this->artisan('users:provision-testing', [
                '--name' => 'Testing Operator',
                '--email' => 'operator@testing.invalid',
                '--role' => 'admin',
            ])->assertSuccessful();

            $user = User::query()->where('email', 'operator@testing.invalid')->firstOrFail();
            $this->assertSame('Testing Operator', $user->name);
            $this->assertSame('admin', $user->role->key);
            $this->assertTrue(Hash::check($password, $user->password));

            $this->artisan('users:provision-testing', [
                '--name' => 'Replacement Operator',
                '--email' => 'operator@testing.invalid',
                '--role' => 'requester',
            ])->assertFailed();

            $this->assertSame('admin', $user->fresh()->role->key);
        } finally {
            putenv('TIC_HUB_BOOTSTRAP_PASSWORD');
        }
    }

    private function createUser(string $roleKey, bool $isActive = true): User
    {
        $role = Role::query()->where('key', $roleKey)->firstOrFail();

        return User::factory()->create([
            'role_id' => $role->id,
            'email' => $roleKey.'-'.fake()->unique()->numberBetween(1, 999999).'@tichub.local',
            'password' => Hash::make('password'),
            'is_active' => $isActive,
        ]);
    }

    private function spaPost(string $uri, array $data)
    {
        return $this->withHeaders([
            'Origin' => 'http://localhost:5173',
            'Referer' => 'http://localhost:5173/',
        ])->postJson($uri, $data);
    }
}
