<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Models\Division;
use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class ProvisionTestingUserCommand extends Command
{
    protected $signature = 'users:provision-testing
        {--name= : Accountable user name}
        {--email= : Accountable user email}
        {--role= : Existing role key}
        {--division= : Existing division code}
        {--branch= : Existing branch code}';

    protected $description = 'Provision one named user for a non-production testing environment';

    public function handle(): int
    {
        if (! app()->environment(['local', 'testing', 'staging'])) {
            $this->error('Testing user provisioning is disabled in this environment.');

            return self::FAILURE;
        }

        $name = trim((string) $this->option('name'));
        $email = strtolower(trim((string) $this->option('email')));
        $roleKey = trim((string) $this->option('role'));
        $password = (string) getenv('TIC_HUB_BOOTSTRAP_PASSWORD');

        if ($name === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL) || $roleKey === '') {
            $this->error('Valid --name, --email, and --role options are required.');

            return self::INVALID;
        }

        if (strlen($password) < 12) {
            $this->error('TIC_HUB_BOOTSTRAP_PASSWORD must contain at least 12 characters.');

            return self::INVALID;
        }

        if (User::query()->where('email', $email)->exists()) {
            $this->error('The user already exists; this command never overwrites credentials.');

            return self::FAILURE;
        }

        $role = Role::query()->where('key', $roleKey)->where('is_active', true)->first();
        if (! $role) {
            $this->error('The requested active role does not exist. Run the production-safe RoleSeeder first.');

            return self::INVALID;
        }

        $division = $this->findOrganizationRecord(Division::query(), 'division');
        $branch = $this->findOrganizationRecord(Branch::query(), 'branch');
        if ($division === false || $branch === false) {
            return self::INVALID;
        }

        User::query()->create([
            'role_id' => $role->id,
            'division_id' => $division?->id,
            'branch_id' => $branch?->id,
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'is_active' => true,
        ]);

        $this->info("Provisioned {$email} with role {$roleKey}. No credential was printed.");

        return self::SUCCESS;
    }

    private function findOrganizationRecord($query, string $option): mixed
    {
        $code = strtoupper(trim((string) $this->option($option)));
        if ($code === '') {
            return null;
        }

        $record = $query->where('code', $code)->where('is_active', true)->first();
        if (! $record) {
            $this->error("The requested active {$option} does not exist.");

            return false;
        }

        return $record;
    }
}
