<?php

namespace Database\Seeders;

use App\Models\Application;
use Illuminate\Database\Seeder;

class ApplicationSystemSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['code' => 'BPR_CORE', 'name' => 'BPR CORE'],
            ['code' => 'DWP_CORE', 'name' => 'DWP CORE'],
            ['code' => 'BROKER_CARAKA', 'name' => 'BROKER CARAKA'],
            ['code' => 'FINANCE', 'name' => 'FINANCE'],
            ['code' => 'HRIS', 'name' => 'HRIS'],
            ['code' => 'PRADA_CORE', 'name' => 'PRADA CORE'],
            ['code' => 'COMPANY_PROFILE', 'name' => 'COMPANY PROFILE'],
        ] as $system) {
            Application::query()->firstOrCreate(
                ['code' => $system['code']],
                [...$system, 'description' => 'Sistem yang dapat dipilih pada pengajuan tiket Requester.', 'is_active' => true],
            );
        }
    }
}
