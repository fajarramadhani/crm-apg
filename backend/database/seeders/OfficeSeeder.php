<?php

namespace Database\Seeders;

use App\Models\Office;
use Illuminate\Database\Seeder;

class OfficeSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['name' => 'Kantor Pusat', 'office_type' => 'pusat'],
            ['name' => 'Bandung', 'office_type' => 'cabang'],
            ['name' => 'Lampung', 'office_type' => 'cabang'],
            ['name' => 'Makassar', 'office_type' => 'cabang'],
        ] as $office) {
            Office::query()->firstOrCreate($office);
        }
    }
}
