<?php

namespace Tests\Feature;

use App\Enums\TicketStatus;
use App\Events\TicketDevelopmentStarted;
use App\Events\TicketInternalTestingFailed;
use App\Events\TicketReadyForQa;
use App\Models\Division;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketPriority;
use App\Models\User;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DevelopmentInternalTestingTest extends TestCase
{
    use RefreshDatabase;

    private User $requester;

    private User $pic;

    private User $otherPic;

    private User $itLead;

    private Division $division;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, MasterDataSeeder::class]);
        $this->division = Division::where('code', 'IT')->firstOrFail();
        $this->requester = $this->user('requester');
        $this->pic = $this->user('pic');
        $this->otherPic = $this->user('pic');
        $this->itLead = $this->user('it_lead');
    }

    public function test_active_pic_starts_development_once_with_history_and_event(): void
    {
        Event::fake([TicketDevelopmentStarted::class]);
        $ticket = $this->approvedTicket();
        $this->actingAs($this->otherPic)->postJson("/api/v1/pic/tickets/{$ticket->id}/start-development")->assertForbidden();
        $this->actingAs($this->requester)->postJson("/api/v1/pic/tickets/{$ticket->id}/start-development")->assertForbidden();
        $this->actingAs($this->pic)->postJson("/api/v1/pic/tickets/{$ticket->id}/start-development")->assertOk()->assertJsonPath('data.status', 'development_in_progress')->assertJsonPath('data.progress_percentage', 0)->assertJsonStructure(['meta' => ['request_id']]);
        $this->assertDatabaseHas('ticket_status_histories', ['ticket_id' => $ticket->id, 'action' => 'development_started', 'actor_id' => $this->pic->id]);
        Event::assertDispatched(TicketDevelopmentStarted::class);
        $this->actingAs($this->pic)->postJson("/api/v1/pic/tickets/{$ticket->id}/start-development")->assertConflict();
    }

    public function test_worklog_and_progress_are_session_owned_monotonic_and_version_checked(): void
    {
        $ticket = $this->startedTicket();
        $this->actingAs($this->pic)->postJson("/api/v1/pic/tickets/{$ticket->id}/worklogs", ['work_date' => now()->toDateString(), 'minutes_spent' => 0, 'activity_type' => 'development', 'description' => 'Implement', 'progress_after' => 20, 'expected_progress' => 0])->assertUnprocessable()->assertJsonValidationErrors('minutes_spent');
        $this->actingAs($this->pic)->postJson("/api/v1/pic/tickets/{$ticket->id}/worklogs", ['user_id' => $this->otherPic->id, 'work_date' => now()->toDateString(), 'minutes_spent' => 60, 'activity_type' => 'development', 'description' => 'Implement approved change', 'progress_after' => 20, 'expected_progress' => 0])->assertCreated();
        $this->assertDatabaseHas('ticket_worklogs', ['ticket_id' => $ticket->id, 'user_id' => $this->pic->id, 'minutes_spent' => 60]);
        $this->actingAs($this->pic)->postJson("/api/v1/pic/tickets/{$ticket->id}/development-updates", ['progress_percentage' => 101, 'expected_progress' => 20, 'summary' => 'Invalid'])->assertUnprocessable();
        $this->actingAs($this->pic)->postJson("/api/v1/pic/tickets/{$ticket->id}/development-updates", ['progress_percentage' => 10, 'expected_progress' => 20, 'summary' => 'Decrease'])->assertConflict();
        $this->actingAs($this->pic)->postJson("/api/v1/pic/tickets/{$ticket->id}/development-updates", ['progress_percentage' => 60, 'expected_progress' => 0, 'summary' => 'Stale'])->assertConflict();
        $this->actingAs($this->pic)->postJson("/api/v1/pic/tickets/{$ticket->id}/development-updates", ['progress_percentage' => 100, 'expected_progress' => 20, 'summary' => 'Implementation complete', 'completed_items' => ['Code'], 'remaining_items' => [], 'blockers' => [], 'next_steps' => ['Internal test']])->assertCreated();
        $this->assertDatabaseHas('ticket_development_updates', ['ticket_id' => $ticket->id, 'progress_percentage' => 100]);
        $this->actingAs($this->otherPic)->getJson("/api/v1/pic/tickets/{$ticket->id}/worklogs")->assertForbidden();
    }

    public function test_evidence_is_internal_and_requester_response_is_redacted(): void
    {
        Storage::fake('local');
        $ticket = $this->startedTicket();
        $this->actingAs($this->pic)->post("/api/v1/pic/tickets/{$ticket->id}/development-evidence", ['file' => UploadedFile::fake()->create('proof.txt', 1, 'text/plain'), 'category' => 'development_evidence'])->assertCreated()->assertJsonPath('data.visibility', 'internal');
        $this->actingAs($this->requester)->getJson("/api/v1/tickets/{$ticket->id}")->assertOk()->assertJsonPath('data.progress_percentage', 0)->assertJsonCount(0, 'data.attachments')->assertJsonMissingPath('data.actual_work_minutes')->assertJsonMissingPath('data.latest_development_update');
    }

    public function test_test_case_validation_and_active_run_conflicts(): void
    {
        $ticket = $this->progressCompleteTicket();
        $this->actingAs($this->pic)->postJson("/api/v1/pic/tickets/{$ticket->id}/internal-test-cases", ['case_number' => 'IT-01', 'title' => 'Smoke', 'steps' => [], 'expected_result' => 'Works'])->assertUnprocessable()->assertJsonValidationErrors('steps');
        $case = $this->createCase($ticket, 'IT-01');
        $this->actingAs($this->pic)->postJson("/api/v1/pic/tickets/{$ticket->id}/internal-test-cases", ['case_number' => 'IT-01', 'title' => 'Duplicate', 'steps' => ['Run'], 'expected_result' => 'Works'])->assertUnprocessable()->assertJsonValidationErrors('case_number');
        $run = $this->actingAs($this->pic)->postJson("/api/v1/pic/tickets/{$ticket->id}/internal-test-runs", ['environment' => 'staging', 'build_reference' => 'release-8'])->assertCreated()->assertJsonPath('data.status', 'in_progress');
        $this->assertSame(TicketStatus::InternalTesting, $ticket->fresh()->status);
        $this->actingAs($this->pic)->postJson("/api/v1/pic/tickets/{$ticket->id}/internal-test-runs", ['environment' => 'staging'])->assertConflict();
        $this->actingAs($this->pic)->postJson("/api/v1/pic/tickets/{$ticket->id}/internal-test-runs/{$run->json('data.id')}/results", ['test_case_id' => $case, 'status' => 'failed'])->assertUnprocessable()->assertJsonValidationErrors('notes');
    }

    public function test_failed_run_reworks_then_second_pass_reaches_ready_for_qa(): void
    {
        Event::fake([TicketInternalTestingFailed::class, TicketReadyForQa::class]);
        $ticket = $this->progressCompleteTicket();
        $case = $this->createCase($ticket, 'IT-01');
        $run1 = $this->startRun($ticket);
        $this->record($ticket, $run1, $case, 'failed', 'Observed mismatch');
        $this->actingAs($this->pic)->postJson("/api/v1/pic/tickets/{$ticket->id}/internal-test-runs/{$run1}/complete", ['summary' => 'Requires rework'])->assertOk()->assertJsonPath('data.status', 'failed');
        $this->assertSame(TicketStatus::DevelopmentInProgress, $ticket->fresh()->status);
        $this->assertSame(90, $ticket->fresh()->progress_percentage);
        Event::assertDispatched(TicketInternalTestingFailed::class);
        $this->actingAs($this->pic)->postJson("/api/v1/pic/tickets/{$ticket->id}/internal-test-runs/{$run1}/complete")->assertConflict();
        $this->actingAs($this->pic)->postJson("/api/v1/pic/tickets/{$ticket->id}/development-updates", ['progress_percentage' => 100, 'expected_progress' => 90, 'summary' => 'Rework complete'])->assertCreated();
        $run2 = $this->startRun($ticket);
        $this->record($ticket, $run2, $case, 'passed', 'Matches expected result');
        $this->actingAs($this->pic)->postJson("/api/v1/pic/tickets/{$ticket->id}/internal-test-runs/{$run2}/complete", ['summary' => 'All passed'])->assertOk()->assertJsonPath('data.status', 'passed');
        $this->assertSame(TicketStatus::ReadyForQa, $ticket->fresh()->status);
        $this->assertNotNull($ticket->fresh()->ready_for_qa_at);
        Event::assertDispatched(TicketReadyForQa::class);
        $this->assertDatabaseHas('ticket_internal_test_runs', ['id' => $run1, 'status' => 'failed']);
        $this->assertDatabaseHas('ticket_status_histories', ['ticket_id' => $ticket->id, 'action' => 'development_rework_started']);
    }

    public function test_it_lead_queue_filters_paginates_and_technical_roles_are_denied(): void
    {
        $ticket = $this->startedTicket();
        $this->actingAs($this->itLead)->getJson('/api/v1/it-lead/development-queue?status=development_in_progress&search=Phase&per_page=1')->assertOk()->assertJsonPath('data.0.id', $ticket->id)->assertJsonPath('meta.pagination.total', 1)->assertJsonStructure(['meta' => ['request_id']]);
        $this->actingAs($this->itLead)->getJson("/api/v1/it-lead/tickets/{$ticket->id}/development")->assertOk()->assertJsonStructure(['data' => ['ticket', 'worklogs', 'updates', 'test_cases', 'test_runs']]);
        $this->actingAs($this->requester)->getJson('/api/v1/it-lead/development-queue')->assertForbidden();
        $this->actingAs($this->user('executive'))->getJson("/api/v1/it-lead/tickets/{$ticket->id}/development")->assertForbidden();
    }

    private function createCase(Ticket $ticket, string $number): int
    {
        return $this->actingAs($this->pic)->postJson("/api/v1/pic/tickets/{$ticket->id}/internal-test-cases", ['case_number' => $number, 'title' => 'Generate policy', 'steps' => ['Open form', 'Generate PDF'], 'expected_result' => 'PDF generated'])->assertCreated()->json('data.id');
    }

    private function startRun(Ticket $ticket): int
    {
        return $this->actingAs($this->pic)->postJson("/api/v1/pic/tickets/{$ticket->id}/internal-test-runs", ['environment' => 'staging', 'build_reference' => 'release-8'])->assertCreated()->json('data.id');
    }

    private function record(Ticket $ticket, int $run, int $case, string $status, string $actual): void
    {
        $this->actingAs($this->pic)->postJson("/api/v1/pic/tickets/{$ticket->id}/internal-test-runs/{$run}/results", ['test_case_id' => $case, 'status' => $status, 'actual_result' => $actual])->assertCreated();
    }

    private function progressCompleteTicket(): Ticket
    {
        $ticket = $this->startedTicket();
        $this->actingAs($this->pic)->postJson("/api/v1/pic/tickets/{$ticket->id}/development-updates", ['progress_percentage' => 100, 'expected_progress' => 0, 'summary' => 'Complete'])->assertCreated();

        return $ticket->fresh();
    }

    private function startedTicket(): Ticket
    {
        $ticket = $this->approvedTicket();
        $this->actingAs($this->pic)->postJson("/api/v1/pic/tickets/{$ticket->id}/start-development")->assertOk();

        return $ticket->fresh();
    }

    private function approvedTicket(): Ticket
    {
        static $number = 0;
        $ticket = Ticket::create(['ticket_number' => 'TIC-202607-'.str_pad((string) ++$number, 6, '0', STR_PAD_LEFT), 'requester_id' => $this->requester->id, 'division_id' => $this->division->id, 'current_division_id' => $this->division->id, 'ticket_category_id' => TicketCategory::firstOrFail()->id, 'requested_priority_id' => TicketPriority::where('key', 'medium')->firstOrFail()->id, 'final_priority_id' => TicketPriority::where('key', 'medium')->firstOrFail()->id, 'title' => 'Phase 8 development ticket', 'description' => 'Description', 'status' => TicketStatus::ReadyForDevelopment, 'submitted_at' => now(), 'validated_at' => now(), 'assigned_at' => now(), 'analysis_started_at' => now(), 'analysis_completed_at' => now(), 'plan_submitted_at' => now(), 'plan_approved_at' => now(), 'current_assignee_id' => $this->pic->id, 'assigned_by' => $this->itLead->id]);
        $ticket->assignments()->create(['assigned_to' => $this->pic->id, 'assigned_by' => $this->itLead->id, 'assignment_type' => 'primary', 'started_at' => now(), 'is_current' => true]);
        $plan = $ticket->solutionPlans()->create(['created_by' => $this->pic->id, 'version' => 1, 'lock_version' => 1, 'is_current' => true, 'solution_summary' => 'Approved solution', 'implementation_steps' => [['order' => 1, 'description' => 'Implement']], 'affected_components' => ['PDF'], 'dependencies' => [], 'estimated_effort_minutes' => 480, 'risk_level' => 'medium', 'testing_plan' => 'Internal smoke test', 'status' => 'approved', 'submitted_at' => now(), 'reviewed_at' => now(), 'reviewed_by' => $this->itLead->id]);
        $ticket->update(['current_solution_plan_id' => $plan->id]);

        return $ticket->fresh();
    }

    private function user(string $role): User
    {
        return User::factory()->create(['role_id' => Role::where('key',$role)->firstOrFail()->id, 'division_id' => $this->division->id, 'is_active' => true]);
    }
}
