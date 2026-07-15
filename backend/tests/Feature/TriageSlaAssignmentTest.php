<?php

namespace Tests\Feature;

use App\Enums\TicketStatus;
use App\Events\TicketAssigned;
use App\Events\TicketTriageStarted;
use App\Models\Division;
use App\Models\Holiday;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketPriority;
use App\Models\User;
use App\Models\WorkingCalendar;
use App\Services\WorkingTimeCalculator;
use Carbon\CarbonImmutable;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class TriageSlaAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private User $requester;

    private User $itLead;

    private User $pic;

    private Division $division;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, MasterDataSeeder::class]);
        $this->division = Division::where('code', 'IT')->firstOrFail();
        $this->requester = $this->user('requester');
        $this->itLead = $this->user('it_lead');
        $this->pic = $this->user('pic');
    }

    public function test_queue_is_authorized_filtered_and_paginated(): void
    {
        $validated = $this->ticket(TicketStatus::Validated, 'Validated search');
        $triage = $this->ticket(TicketStatus::Triage, 'Triage search');
        $this->ticket(TicketStatus::PendingValidation, 'Should stay hidden');
        $this->actingAs($this->itLead)->getJson('/api/v1/it-lead/triage-queue?search=search&per_page=1')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.pagination.total', 2)->assertJsonStructure(['meta' => ['request_id']]);
        $this->actingAs($this->itLead)->getJson('/api/v1/it-lead/triage-queue?status=triage')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $triage->id);
        $this->actingAs($this->requester)->getJson('/api/v1/it-lead/triage-queue')->assertForbidden();
        $this->assertNotSame($validated->id, $triage->id);
    }

    public function test_start_triage_is_locked_records_history_dispatches_event_and_rejects_stale_action(): void
    {
        Event::fake([TicketTriageStarted::class]);
        $ticket = $this->ticket(TicketStatus::Validated);
        $this->actingAs($this->itLead)->postJson("/api/v1/it-lead/tickets/{$ticket->id}/start-triage")->assertOk()->assertJsonPath('data.status', 'triage')->assertJsonPath('data.allowed_actions.0', 'assign');
        $this->assertDatabaseHas('ticket_status_histories', ['ticket_id' => $ticket->id, 'from_status' => 'validated', 'to_status' => 'triage', 'action' => 'triage_started', 'actor_id' => $this->itLead->id]);
        $this->assertNotNull($ticket->fresh()->triage_started_at);
        Event::assertDispatched(TicketTriageStarted::class);
        $this->actingAs($this->itLead)->postJson("/api/v1/it-lead/tickets/{$ticket->id}/start-triage")->assertConflict()->assertJsonPath('error.code', 'INVALID_TRANSITION');
    }

    public function test_pic_options_exclude_inactive_and_non_pic_and_report_workload_without_duplicate_assignment(): void
    {
        $inactive = $this->user('pic', false);
        $this->ticket(TicketStatus::Assigned, currentAssignee: $this->pic)->assignments()->create(['assigned_to' => $this->pic->id, 'assigned_by' => $this->itLead->id, 'assignment_type' => 'primary', 'started_at' => now(), 'is_current' => true]);
        $response = $this->actingAs($this->itLead)->getJson('/api/v1/it-lead/pic-options')->assertOk();
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($this->pic->id));
        $this->assertFalse($ids->contains($inactive->id));
        $this->assertFalse($ids->contains($this->requester->id));
        $this->assertSame(1, collect($response->json('data'))->firstWhere('id', $this->pic->id)['active_assignment_count']);
    }

    public function test_assignment_is_atomic_calculates_sla_and_exposes_only_safe_requester_history(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-07-17 09:00:00', 'Asia/Jakarta'));
        Event::fake([TicketAssigned::class]);
        $ticket = $this->ticket(TicketStatus::Triage);
        $priority = TicketPriority::where('key', 'critical')->firstOrFail();
        $response = $this->actingAs($this->itLead)->postJson("/api/v1/it-lead/tickets/{$ticket->id}/assign", ['final_priority_id' => $priority->id, 'pic_user_id' => $this->pic->id, 'notes' => 'Internal authentication investigation'])->assertOk()->assertJsonPath('data.status', 'assigned')->assertJsonPath('data.final_priority.key', 'critical')->assertJsonPath('data.assignee.id', $this->pic->id);
        $this->assertSame('2026-07-17T06:00:00.000000Z', $response->json('data.resolution_due_at'));
        $this->assertDatabaseHas('ticket_assignments', ['ticket_id' => $ticket->id, 'assigned_to' => $this->pic->id, 'assignment_type' => 'primary', 'is_current' => true]);
        $this->assertDatabaseHas('ticket_status_histories', ['ticket_id' => $ticket->id, 'action' => 'assigned']);
        Event::assertDispatched(TicketAssigned::class);
        $this->actingAs($this->itLead)->postJson("/api/v1/it-lead/tickets/{$ticket->id}/assign", ['final_priority_id' => $priority->id, 'pic_user_id' => $this->pic->id])->assertConflict();
        $requesterView = $this->actingAs($this->requester)->getJson("/api/v1/tickets/{$ticket->id}")->assertOk()->assertJsonPath('data.status', 'assigned')->assertJsonPath('data.final_priority.key', 'critical')->assertJsonPath('data.assignee.name', $this->pic->name);
        $assignedHistory = collect($requesterView->json('data.history'))->firstWhere('action', 'assigned');
        $this->assertNull($assignedHistory['notes']);
        $this->assertNull($assignedHistory['metadata']);
        $this->travelBack();
    }

    public function test_assignment_validates_priority_pic_and_active_sla_policy(): void
    {
        $ticket = $this->ticket(TicketStatus::Triage);
        $inactivePriority = TicketPriority::where('key', 'low')->firstOrFail();
        $inactivePriority->update(['is_active' => false]);
        $this->actingAs($this->itLead)->postJson("/api/v1/it-lead/tickets/{$ticket->id}/assign", ['pic_user_id' => $this->pic->id])->assertUnprocessable()->assertJsonValidationErrors('final_priority_id');
        $this->actingAs($this->itLead)->postJson("/api/v1/it-lead/tickets/{$ticket->id}/assign", ['final_priority_id' => $inactivePriority->id, 'pic_user_id' => $this->pic->id])->assertUnprocessable()->assertJsonValidationErrors('final_priority_id');
        $priority = TicketPriority::where('key', 'high')->firstOrFail();
        $this->actingAs($this->itLead)->postJson("/api/v1/it-lead/tickets/{$ticket->id}/assign", ['final_priority_id' => $priority->id, 'pic_user_id' => $this->requester->id])->assertUnprocessable()->assertJsonValidationErrors('pic_user_id');
        $this->assertDatabaseMissing('ticket_assignments', ['ticket_id' => $ticket->id]);
        $this->assertSame(TicketStatus::Triage, $ticket->fresh()->status);
    }

    public function test_working_time_normalizes_after_hours_skips_weekend_and_holiday(): void
    {
        $calendar = WorkingCalendar::firstOrFail();
        Holiday::create(['working_calendar_id' => $calendar->id, 'date' => '2026-07-20', 'name' => 'Test holiday', 'is_recurring' => false]);
        $calculator = app(WorkingTimeCalculator::class);
        $friday = CarbonImmutable::parse('2026-07-17 16:00:00', 'Asia/Jakarta');
        $this->assertSame('2026-07-21 11:00:00', $calculator->addWorkingMinutes($calendar, $friday, 240)->setTimezone('Asia/Jakarta')->format('Y-m-d H:i:s'));
        $afterHours = CarbonImmutable::parse('2026-07-17 19:00:00', 'Asia/Jakarta');
        $this->assertSame('2026-07-21 09:00:00', $calculator->addWorkingMinutes($calendar, $afterHours, 60)->setTimezone('Asia/Jakarta')->format('Y-m-d H:i:s'));
    }

    public function test_only_current_pic_can_list_and_open_assignment_and_executive_is_denied(): void
    {
        $otherPic = $this->user('pic');
        $ticket = $this->ticket(TicketStatus::Assigned, currentAssignee: $this->pic);
        $ticket->assignments()->create(['assigned_to' => $this->pic->id, 'assigned_by' => $this->itLead->id, 'assignment_type' => 'primary', 'started_at' => now(), 'is_current' => true]);
        $this->actingAs($this->pic)->getJson('/api/v1/pic/assignments?status=assigned')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $ticket->id);
        $this->actingAs($this->pic)->getJson("/api/v1/pic/tickets/{$ticket->id}")->assertOk();
        $this->actingAs($otherPic)->getJson("/api/v1/pic/tickets/{$ticket->id}")->assertForbidden();
        $executive = $this->user('executive');
        $this->actingAs($executive)->getJson("/api/v1/it-lead/tickets/{$ticket->id}")->assertForbidden();
        $this->actingAs($executive)->getJson("/api/v1/pic/tickets/{$ticket->id}")->assertForbidden();
    }

    private function user(string $role, bool $active = true): User
    {
        return User::factory()->create(['role_id' => Role::where('key', $role)->firstOrFail()->id, 'division_id' => $this->division->id, 'is_active' => $active]);
    }

    private function ticket(TicketStatus $status, string $title = 'Phase 6 ticket', ?User $currentAssignee = null): Ticket
    {
        static $number = 0;

        return Ticket::create(['ticket_number' => 'TIC-202607-'.str_pad((string) ++$number, 6, '0', STR_PAD_LEFT), 'requester_id' => $this->requester->id, 'division_id' => $this->division->id, 'current_division_id' => $this->division->id, 'ticket_category_id' => TicketCategory::firstOrFail()->id, 'requested_priority_id' => TicketPriority::where('key', 'medium')->firstOrFail()->id, 'title' => $title, 'description' => 'Description', 'status' => $status, 'submitted_at' => now(), 'validated_at' => in_array($status, [TicketStatus::Validated, TicketStatus::Triage, TicketStatus::Assigned], true) ? now() : null, 'triage_started_at' => in_array($status, [TicketStatus::Triage, TicketStatus::Assigned], true) ? now() : null, 'current_assignee_id' => $currentAssignee?->id, 'assigned_at' => $currentAssignee ? now() : null]);
    }
}
