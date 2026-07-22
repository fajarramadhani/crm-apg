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
        $divisionId = Division::first()?->id ?? 1;

        return [
            'ticket_number' => 'TIC-'.date('ymd').'-'.rand(100000, 999999),
            'requester_id' => User::factory(),
            'division_id' => $divisionId,
            'current_division_id' => $divisionId,
            'application_id' => Application::first()?->id ?? 1,
            'ticket_category_id' => TicketCategory::first()?->id ?? 1,
            'requested_priority_id' => TicketPriority::first()?->id ?? 1,
            'title' => $this->faker->sentence(),
            'description' => $this->faker->paragraph(),
            'status' => 'pending_validation',
        ];
    }
}
