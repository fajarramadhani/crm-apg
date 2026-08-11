<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrmRoleMappingCommandTest extends TestCase
{
    use RefreshDatabase;

    private const HEADER = 'user_id,name,email,current_role,proposed_role,reason,approved_by,mapping_status,notes';

    public function test_valid_dry_run_reports_change_without_updating_role(): void
    {
        $user = $this->userWithRole('pic');
        $path = $this->csv([
            [$user->id, $user->name, $user->email, 'pic', 'pic_it_develop', 'Developer assignment', 'Head of IT', 'pending', 'Manual review'],
        ]);

        try {
            $this->artisan('crm:role-mapping', ['--file' => $path, '--dry-run' => true])
                ->expectsOutputToContain('pic_it_develop')
                ->expectsOutputToContain('Proposed changes: 1; unchanged rows: 0.')
                ->expectsOutputToContain('No database changes were made.')
                ->assertSuccessful();

            $this->assertSame('pic', $user->fresh()->role->key);
        } finally {
            unlink($path);
        }
    }

    public function test_command_requires_dry_run_option(): void
    {
        $path = $this->csv([]);

        try {
            $this->artisan('crm:role-mapping', ['--file' => $path])
                ->expectsOutputToContain('This command has no apply mode.')
                ->assertExitCode(2);
        } finally {
            unlink($path);
        }
    }

    public function test_dry_run_rejects_wrong_header_and_roles_outside_final_five(): void
    {
        $wrongHeader = tempnam(sys_get_temp_dir(), 'crm-role-map-');
        file_put_contents($wrongHeader, "email,user_id\n");

        try {
            $this->artisan('crm:role-mapping', ['--file' => $wrongHeader, '--dry-run' => true])
                ->expectsOutputToContain('Invalid CSV header.')
                ->assertExitCode(2);
        } finally {
            unlink($wrongHeader);
        }

        $user = $this->userWithRole('qa');
        $path = $this->csv([
            [$user->id, $user->name, $user->email, 'qa', 'qa', 'Keep QA', 'Head of IT', 'pending', ''],
        ]);

        try {
            $this->artisan('crm:role-mapping', ['--file' => $path, '--dry-run' => true])
                ->expectsOutputToContain('proposed_role must be one of requester, supervisor_it, pic_it_support, pic_it_develop, superadmin.')
                ->assertExitCode(2);

            $this->assertSame('qa', $user->fresh()->role->key);
        } finally {
            unlink($path);
        }
    }

    public function test_dry_run_validates_existing_identity_current_role_and_duplicate_users(): void
    {
        $user = $this->userWithRole('supervisor');
        $path = $this->csv([
            [$user->id, 'Wrong Name', $user->email, 'requester', 'requester', 'Business supervisor', 'Head of Ops', 'pending', ''],
            [$user->id, $user->name, $user->email, 'supervisor', 'supervisor_it', 'IT supervisor', 'Head of IT', 'pending', ''],
            [999999, 'Missing User', 'missing@example.invalid', 'pic', 'pic_it_support', 'Support', 'Head of IT', 'pending', ''],
        ]);

        try {
            $this->artisan('crm:role-mapping', ['--file' => $path, '--dry-run' => true])
                ->expectsOutputToContain('name does not match; current_role does not match the current role.')
                ->expectsOutputToContain("user_id {$user->id} is duplicated")
                ->expectsOutputToContain('user_id 999999 does not exist.')
                ->expectsOutputToContain('No database changes were made.')
                ->assertExitCode(2);

            $this->assertSame('supervisor', $user->fresh()->role->key);
        } finally {
            unlink($path);
        }
    }

    private function userWithRole(string $roleKey): User
    {
        $role = Role::query()->create([
            'key' => $roleKey,
            'name' => str($roleKey)->headline(),
            'is_active' => true,
        ]);

        return User::factory()->create(['role_id' => $role->id]);
    }

    /** @param list<list<int|string>> $rows */
    private function csv(array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'crm-role-map-');
        $handle = fopen($path, 'wb');
        fwrite($handle, self::HEADER."\n");

        foreach ($rows as $row) {
            fputcsv($handle, $row, ',', '"', '');
        }

        fclose($handle);

        return $path;
    }
}
