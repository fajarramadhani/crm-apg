<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Branch;
use App\Models\Division;
use App\Models\Role;
use App\Models\TicketCategory;
use App\Models\TicketPriority;
use App\Models\User;
use App\Models\WorkingCalendar;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_seeder_creates_only_canonical_roles(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(8, Role::query()->count());
        $this->assertSame(0, User::query()->count());
        $this->assertSame(0, Division::query()->count());
        $this->assertSame(0, Branch::query()->count());
        $this->assertSame(0, Application::query()->count());
        $this->assertSame(0, TicketCategory::query()->count());
        $this->assertSame(0, TicketPriority::query()->count());
        $this->assertSame(0, WorkingCalendar::query()->count());
    }
}
