<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class BootstrapProductionUserCommand extends Command
{
    private const MIN_PASSWORD_LENGTH = 12;

    private const GENERATED_PASSWORD_LENGTH = 16;

    protected $signature = 'users:bootstrap-production
        {--name= : Full name of the initial super admin}
        {--email= : Email address of the initial super admin}';

    protected $description = 'Create the first super admin account for a fresh production environment (single-use)';

    public function handle(): int
    {
        if ((string) config('app.env') !== 'production') {
            $this->error('This command may only run in the production environment.');

            return self::FAILURE;
        }

        $name = trim((string) $this->option('name'));
        $email = strtolower(trim((string) $this->option('email')));

        if ($name === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Valid --name and --email options are required.');

            return self::INVALID;
        }

        if ($this->environmentAlreadyBootstrapped($email)) {
            return self::FAILURE;
        }

        $role = Role::query()->where('key', 'superadmin')->where('is_active', true)->first();
        if (! $role) {
            $this->error('The active superadmin role does not exist. Run php artisan db:seed --class=RoleSeeder first.');

            return self::INVALID;
        }

        [$password, $generated] = $this->resolvePassword();
        if ($password === null) {
            return self::INVALID;
        }

        /** @var User $user */
        $user = DB::transaction(function () use ($name, $email, $role, $password): User {
            $created = User::query()->create([
                'role_id' => $role->id,
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
                'is_active' => true,
                'must_change_password' => true,
            ]);

            AuditLogger::record(
                'identity.bootstrap.completed',
                actor: $created,
                auditable: $created,
                newValues: [
                    'email' => $email,
                    'role' => 'superadmin',
                    'must_change_password' => true,
                ],
                metadata: ['source' => 'users:bootstrap-production'],
            );

            return $created;
        });

        $this->info("Super admin {$user->email} created with role superadmin.");

        if ($generated) {
            $this->warn('One-time password (displayed once, never stored or logged again):');
            $this->line($password);
        } else {
            $this->info('Password was taken from TIC_HUB_ADMIN_PASSWORD and is not displayed.');
        }

        $this->line('The account must change this password at first login before continuing to use Tic Hub.');

        return self::SUCCESS;
    }

    private function environmentAlreadyBootstrapped(string $email): bool
    {
        if (! User::withTrashed()->exists()) {
            return false;
        }

        AuditLogger::record(
            'identity.bootstrap.refused',
            metadata: ['reason' => 'users_already_exist', 'requested_email' => $email],
        );

        $this->error('This environment already has user accounts; the production bootstrap is single-use only.');

        return true;
    }

    /** @return array{0: ?string, 1: bool} */
    private function resolvePassword(): array
    {
        $fromEnvironment = getenv('TIC_HUB_ADMIN_PASSWORD');

        if ($fromEnvironment === false || $fromEnvironment === '') {
            return [Str::password(self::GENERATED_PASSWORD_LENGTH), true];
        }

        if (strlen((string) $fromEnvironment) < self::MIN_PASSWORD_LENGTH) {
            $this->error(sprintf('TIC_HUB_ADMIN_PASSWORD must contain at least %d characters.', self::MIN_PASSWORD_LENGTH));

            return [null, false];
        }

        return [(string) $fromEnvironment, false];
    }
}
