<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['key' => 'requester', 'name' => 'Requester', 'description' => 'Membuat tiket, melengkapi informasi, dan memantau penyelesaian.'],
            ['key' => 'supervisor', 'name' => 'Supervisor', 'description' => 'Role legacy untuk validasi tiket per divisi.'],
            ['key' => 'it_lead', 'name' => 'IT Lead', 'description' => 'Role legacy untuk triage, QA, UAT, release, dan deployment.'],
            ['key' => 'pic', 'name' => 'PIC / IT Member', 'description' => 'Role legacy untuk pelaksana teknis umum.'],
            ['key' => 'qa', 'name' => 'QA', 'description' => 'Melaksanakan pengujian kualitas pada workflow lanjutan.'],
            ['key' => 'manager', 'name' => 'Manager', 'description' => 'Memberikan business approval pada workflow lanjutan.'],
            ['key' => 'executive', 'name' => 'Executive', 'description' => 'Melihat laporan dan ringkasan eksekutif.'],
            ['key' => 'superadmin', 'name' => 'Super Admin', 'description' => 'Mengontrol seluruh administrasi akun, role, master data, SLA, dan workflow.'],
            ['key' => 'supervisor_it', 'name' => 'Supervisor IT', 'description' => 'Menganalisis, menugaskan PIC, memeriksa, dan menutup tiket.'],
            ['key' => 'pic_it_support', 'name' => 'PIC IT Support', 'description' => 'Menangani insiden dan request operasional/infrastruktur.'],
            ['key' => 'pic_it_develop', 'name' => 'PIC IT Develop', 'description' => 'Menangani perubahan aplikasi, bug, dan kebutuhan development.'],
        ];

        foreach ($roles as $role) {
            Role::query()->updateOrCreate(
                ['key' => $role['key']],
                [...$role, 'is_active' => true],
            );
        }
    }
}
