<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Division;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\ApplicationSystemSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequesterPublicHandlingTest extends TestCase
{
    use RefreshDatabase;

    private User $requester;

    private Ticket $ticket;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, ApplicationSystemSeeder::class]);
        $division = Division::query()->create(['code' => 'IT', 'name' => 'IT', 'is_active' => true]);
        $this->requester = $this->user('requester', $division->id);
        $this->ticket = Ticket::query()->create([
            'ticket_number' => 'TIC-202607-999001',
            'requester_id' => $this->requester->id,
            'division_id' => $division->id,
            'current_division_id' => $division->id,
            'request_category' => 'error_bug',
            'application_id' => Application::query()->where('code', 'HRIS')->firstOrFail()->id,
            'title' => 'Public handling test',
            'description' => 'Public handling response must not leak private PIC data.',
            'affected_url' => 'https://example.test/error',
            'urgency' => 'high',
            'status' => 'assigned',
            'submitted_at' => now(),
        ]);
    }

    public function test_unassigned_ticket_returns_supervisor_message(): void
    {
        $this->actingAs($this->requester)->getJson("/api/v1/tickets/{$this->ticket->id}")
            ->assertOk()
            ->assertJsonPath('data.handling.state', 'supervisor_review')
            ->assertJsonPath('data.handling.message', 'Sedang dalam proses penanganan oleh Supervisor IT.')
            ->assertJsonPath('data.handling.primary_pic', null);
    }

    public function test_active_primary_and_secondary_pics_are_public_without_private_fields(): void
    {
        $primary = $this->user('pic_it_support', $this->ticket->division_id, 'primary@apg.test', '0811111111');
        $secondary = $this->user('pic_it_develop', $this->ticket->division_id, 'secondary@apg.test', '0822222222');
        $this->assignment($primary, 'primary', 'Internal primary reason');
        $this->assignment($secondary, 'secondary', 'Internal secondary reason');

        $response = $this->actingAs($this->requester)->getJson("/api/v1/tickets/{$this->ticket->id}")
            ->assertOk()
            ->assertJsonPath('data.handling.state', 'assigned')
            ->assertJsonPath('data.handling.primary_pic.name', $primary->name)
            ->assertJsonPath('data.handling.primary_pic.role', 'IT Support')
            ->assertJsonPath('data.handling.secondary_pics.0.name', $secondary->name)
            ->assertJsonPath('data.handling.secondary_pics.0.role', 'IT Developer');

        $handling = json_encode($response->json('data.handling'), JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('primary@apg.test', $handling);
        $this->assertStringNotContainsString('secondary@apg.test', $handling);
        $this->assertStringNotContainsString('0811111111', $handling);
        $this->assertStringNotContainsString('Internal primary reason', $handling);
        $this->assertStringNotContainsString('pic_it_support', $handling);
    }

    public function test_inactive_assignment_is_hidden_and_reassignment_uses_latest_active_primary(): void
    {
        $old = $this->user('pic_it_support', $this->ticket->division_id);
        $new = $this->user('supervisor_it', $this->ticket->division_id);
        $oldAssignment = $this->assignment($old, 'primary');
        $oldAssignment->update(['is_current' => false, 'ended_at' => now()]);
        $this->assignment($new, 'primary');

        $this->actingAs($this->requester)->getJson("/api/v1/tickets/{$this->ticket->id}")
            ->assertOk()
            ->assertJsonPath('data.handling.primary_pic.name', $new->name)
            ->assertJsonPath('data.handling.primary_pic.role', 'Supervisor IT')
            ->assertJsonMissing(['name' => $old->name]);
    }

    public function test_other_requester_cannot_view_public_handling(): void
    {
        $other = $this->user('requester', $this->ticket->division_id);
        $this->actingAs($other)->getJson("/api/v1/tickets/{$this->ticket->id}")->assertForbidden();
    }

    private function user(string $role, int $divisionId, ?string $email = null, ?string $phone = null): User
    {
        return User::factory()->create([
            'role_id' => Role::query()->where('key', $role)->firstOrFail()->id,
            'division_id' => $divisionId,
            'email' => $email ?? fake()->unique()->safeEmail(),
            'phone' => $phone,
            'is_active' => true,
        ]);
    }

    private function assignment(User $user, string $type, ?string $notes = null)
    {
        return $this->ticket->assignments()->create([
            'assigned_to' => $user->id,
            'assigned_by' => $user->id,
            'assignment_type' => $type,
            'started_at' => now(),
            'is_current' => true,
            'notes' => $notes,
        ]);
    }
}
