<?php

namespace Tests\Feature;

use App\Models\Application;
use Database\Seeders\ApplicationSystemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplicationSystemSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_is_idempotent_and_preserves_admin_edits_and_unrelated_systems(): void
    {
        $this->seed(ApplicationSystemSeeder::class);
        Application::query()->where('code', 'HRIS')->update(['name' => 'HRIS Custom Admin']);
        Application::query()->create(['code' => 'CUSTOM_APP', 'name' => 'Custom App', 'is_active' => true]);

        $this->seed(ApplicationSystemSeeder::class);

        $this->assertSame(8, Application::query()->count());
        $this->assertSame(7, Application::query()->whereIn('code', ['BPR_CORE', 'DWP_CORE', 'BROKER_CARAKA', 'FINANCE', 'HRIS', 'PRADA_CORE', 'COMPANY_PROFILE'])->count());
        $this->assertDatabaseHas('applications', ['code' => 'HRIS', 'name' => 'HRIS Custom Admin']);
        $this->assertDatabaseHas('applications', ['code' => 'CUSTOM_APP', 'name' => 'Custom App']);
    }
}
