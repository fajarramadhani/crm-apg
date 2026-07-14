<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['key' => 'requester', 'name' => 'Requester'],
            ['key' => 'supervisor', 'name' => 'Supervisor'],
            ['key' => 'it_lead', 'name' => 'IT Lead'],
            ['key' => 'pic', 'name' => 'PIC / IT Member'],
            ['key' => 'qa', 'name' => 'QA'],
            ['key' => 'manager', 'name' => 'Manager'],
            ['key' => 'executive', 'name' => 'Executive'],
            ['key' => 'admin', 'name' => 'Admin'],
        ];

        foreach ($roles as $role) {
            Role::query()->updateOrCreate(
                ['key' => $role['key']],
                [...$role, 'is_active' => true],
            );
        }
    }
}
