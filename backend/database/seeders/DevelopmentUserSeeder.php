<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Division;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DevelopmentUserSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn('Development accounts were not seeded outside local/testing environments.');

            return;
        }

        $accounts = [
            'requester' => ['Requester Demo', 'requester@tichub.local'],
            'supervisor' => ['Supervisor Demo', 'supervisor@tichub.local'],
            'it_lead' => ['IT Lead Demo', 'itlead@tichub.local'],
            'pic' => ['PIC Demo', 'pic@tichub.local'],
            'qa' => ['QA Demo', 'qa@tichub.local'],
            'manager' => ['Manager Demo', 'manager@tichub.local'],
            'executive' => ['Executive Demo', 'executive@tichub.local'],
            'admin' => ['Admin Demo', 'admin@tichub.local'],
        ];
        $division = Division::query()->where('code', 'IT')->first();
        $branch = Branch::query()->where('code', 'JKT')->first();

        foreach ($accounts as $roleKey => [$name, $email]) {
            $role = Role::query()->where('key', $roleKey)->firstOrFail();

            User::query()->updateOrCreate(
                ['email' => $email],
                [
                    'role_id' => $role->id,
                    'division_id' => $division?->id,
                    'branch_id' => $branch?->id,
                    'name' => $name,
                    'password' => Hash::make('password'),
                    'is_active' => true,
                ],
            );
        }
    }
}
