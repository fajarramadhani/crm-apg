<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\Console\Command\Command as BaseCommand;
use Tests\TestCase;

class ProductionIdentityBootstrapTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        putenv('TIC_HUB_ADMIN_PASSWORD');
    }

    protected function tearDown(): void
    {
        putenv('TIC_HUB_ADMIN_PASSWORD');

        parent::tearDown();
    }

    public function test_refuses_to_run_outside_production_environment(): void
    {
        config(['app.env' => 'testing']);

        $this->artisan('users:bootstrap-production', [
            '--name' => 'IT Admin APG',
            '--email' => 'admin@apg.co.id',
        ])->assertExitCode(BaseCommand::FAILURE);

        $this->assertDatabaseCount(User::class, 0);
    }

    public function test_creates_first_super_admin_with_mandatory_password_rotation_in_production(): void
    {
        config(['app.env' => 'production']);
        putenv('TIC_HUB_ADMIN_PASSWORD=Bootstrap#2026Secure');

        $this->artisan('users:bootstrap-production', [
            '--name' => 'IT Admin APG',
            '--email' => 'Admin@APG.co.id',
        ])->assertSuccessful();

        $user = User::query()->where('email', 'admin@apg.co.id')->firstOrFail();
        $this->assertTrue(Hash::check('Bootstrap#2026Secure', $user->password));
        $this->assertTrue($user->is_active);
        $this->assertTrue($user->must_change_password);
        $this->assertSame('superadmin', $user->role?->key);

        $this->assertDatabaseHas(AuditLog::class, [
            'event' => 'identity.bootstrap.completed',
            'actor_id' => $user->id,
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
        ]);
    }

    public function test_is_single_use_and_refuses_when_users_already_exist(): void
    {
        config(['app.env' => 'production']);
        $this->createUser('first@apg.co.id');

        $this->artisan('users:bootstrap-production', [
            '--name' => 'IT Admin APG',
            '--email' => 'second@apg.co.id',
        ])->assertExitCode(BaseCommand::FAILURE);

        $this->assertSame(1, User::count());
        $this->assertNull(User::query()->where('email', 'second@apg.co.id')->first());
        $this->assertDatabaseHas(AuditLog::class, ['event' => 'identity.bootstrap.refused']);
    }

    public function test_refuses_when_active_super_admin_role_is_missing(): void
    {
        config(['app.env' => 'production']);
        Role::query()->where('key', 'superadmin')->delete();

        $this->artisan('users:bootstrap-production', [
            '--name' => 'IT Admin APG',
            '--email' => 'admin@apg.co.id',
        ])->assertExitCode(BaseCommand::INVALID);

        $this->assertDatabaseCount(User::class, 0);
    }

    public function test_refuses_environment_password_shorter_than_minimum_length(): void
    {
        config(['app.env' => 'production']);
        putenv('TIC_HUB_ADMIN_PASSWORD=short');

        $this->artisan('users:bootstrap-production', [
            '--name' => 'IT Admin APG',
            '--email' => 'admin@apg.co.id',
        ])->assertExitCode(BaseCommand::INVALID);

        $this->assertDatabaseCount(User::class, 0);
    }

    private function createUser(string $email): User
    {
        return User::query()->create([
            'role_id' => Role::query()->where('key', 'superadmin')->firstOrFail()->id,
            'name' => 'Existing Admin',
            'email' => $email,
            'password' => Hash::make('existing-password-123'),
            'is_active' => true,
        ]);
    }
}
