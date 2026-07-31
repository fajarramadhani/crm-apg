<?php

namespace Tests\Feature;

use App\Models\Office;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowDefinition;
use Database\Seeders\OfficeSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfficeSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_office_seeder_is_idempotent_and_preserves_existing_data_roles_and_workflow(): void
    {
        $this->seed([RoleSeeder::class, OfficeSeeder::class]);
        $role = Role::query()->where('key', 'requester')->firstOrFail();
        $user = User::factory()->create(['role_id' => $role->id]);
        $existing = Office::query()->create(['name' => 'Medan', 'office_type' => 'cabang']);
        $workflowCount = WorkflowDefinition::query()->count();

        $this->seed(OfficeSeeder::class);

        $this->assertSame(5, Office::query()->count());
        $this->assertSame(4, Office::query()->whereIn('name', ['Kantor Pusat', 'Bandung', 'Lampung', 'Makassar'])->count());
        $uniqueOfficeCount = Office::query()->get(['name', 'office_type'])
            ->unique(fn (Office $office) => "{$office->name}|{$office->office_type}")
            ->count();
        $this->assertSame(Office::query()->count(), $uniqueOfficeCount);
        $this->assertDatabaseHas('offices', ['id' => $existing->id, 'name' => 'Medan']);
        $this->assertSame($role->id, $user->fresh()->role_id);
        $this->assertSame($workflowCount, WorkflowDefinition::query()->count());
        $this->assertSame(0, WorkflowDefinition::query()->where('is_active', true)->count());
    }
}
