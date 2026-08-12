<?php

namespace Database\Factories;

use App\Models\Application;
use App\Models\Division;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketPriority;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TicketFactory extends Factory
{
    protected $model = Ticket::class;

    public function definition(): array
    {
        $division = Division::query()->first() ?? Division::query()->create([
            'code' => 'D'.uniqid(),
            'name' => 'Division '.uniqid(),
            'description' => null,
            'parent_id' => null,
            'is_active' => true,
        ]);

        $application = Application::query()->first() ?? Application::query()->create([
            'code' => 'APP'.uniqid(),
            'name' => 'Application '.uniqid(),
            'owner_division_id' => $division->id,
            'is_active' => true,
        ]);

        $category = TicketCategory::query()->first() ?? TicketCategory::query()->create([
            'code' => 'CAT'.uniqid(),
            'name' => 'Category '.uniqid(),
            'type' => 'incident',
            'description' => null,
            'is_active' => true,
        ]);

        $priority = TicketPriority::query()->first() ?? TicketPriority::query()->create([
            'key' => 'priority_'.uniqid(),
            'name' => 'Priority '.uniqid(),
            'level' => 1,
            'description' => null,
            'is_active' => true,
        ]);

        return [
            'ticket_number' => 'TIC-'.date('ymd').'-'.rand(100000, 999999),
            'requester_id' => User::factory(),
            'division_id' => $division->id,
            'current_division_id' => $division->id,
            'application_id' => $application->id,
            'ticket_category_id' => $category->id,
            'requested_priority_id' => $priority->id,
            'title' => $this->faker->sentence(),
            'description' => $this->faker->paragraph(),
            'status' => 'pending_validation',
        ];
    }
}
