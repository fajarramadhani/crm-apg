<?php

namespace Database\Seeders;

use App\Models\Application;
use App\Models\Branch;
use App\Models\Division;
use App\Models\SlaPolicy;
use App\Models\TicketCategory;
use App\Models\TicketPriority;
use App\Models\WorkingCalendar;
use Illuminate\Database\Seeder;

class MasterDataSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['IT', 'Information Technology'], ['HR', 'Human Resources'], ['FIN', 'Finance'],
            ['OPS', 'Operations'], ['MKT', 'Marketing'],
        ] as [$code,$name]) {
            Division::updateOrCreate(['code' => $code], ['name' => $name, 'description' => 'Development seed data', 'is_active' => true]);
        }

        foreach ([['JKT', 'Jakarta', 'Jakarta'], ['SBY', 'Surabaya', 'Surabaya'], ['BDG', 'Bandung', 'Bandung']] as [$code,$name,$city]) {
            Branch::updateOrCreate(['code' => $code], ['name' => $name, 'city' => $city, 'address' => 'Development seed address', 'is_active' => true]);
        }

        $it = Division::where('code', 'IT')->firstOrFail();
        foreach ([['TIC_HUB', 'Tic Hub'], ['CMS_MULTI', 'CMS Multi Company'], ['APG_PROFILE', 'Company Profile APG'], ['BPR_BONDING', 'BPR Bonding'], ['DWP_INSURANCE', 'DWP Insurance']] as [$code,$name]) {
            Application::updateOrCreate(['code' => $code], ['name' => $name, 'description' => 'Development seed application', 'owner_division_id' => $it->id, 'is_active' => true]);
        }

        foreach ([['INCIDENT', 'Incident', 'incident'], ['REQUEST', 'Request', 'request'], ['CHANGE', 'Change', 'change'], ['PROBLEM', 'Problem', 'problem']] as [$code,$name,$type]) {
            TicketCategory::updateOrCreate(['code' => $code], ['name' => $name, 'type' => $type, 'description' => 'Development seed category', 'is_active' => true]);
        }

        $calendar = WorkingCalendar::updateOrCreate(['code' => 'JKT_WEEKDAY'], ['name' => 'Senin–Jumat 08:00–17:00', 'timezone' => 'Asia/Jakarta', 'workday_start' => '08:00', 'workday_end' => '17:00', 'working_days' => [1, 2, 3, 4, 5], 'is_active' => true]);
        foreach ([['critical', 'Critical', 1, 240], ['high', 'High', 2, 480], ['medium', 'Medium', 3, 960], ['low', 'Low', 4, 2400]] as [$key,$name,$level,$minutes]) {
            $priority = TicketPriority::updateOrCreate(['key' => $key], ['name' => $name, 'level' => $level, 'description' => 'Development seed priority', 'is_active' => true]);
            SlaPolicy::updateOrCreate(['priority_id' => $priority->id, 'working_calendar_id' => $calendar->id, 'is_active' => true], ['response_minutes' => null, 'resolution_minutes' => $minutes]);
        }
    }
}
