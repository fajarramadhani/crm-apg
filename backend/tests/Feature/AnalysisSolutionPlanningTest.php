<?php

namespace Tests\Feature;

use App\Enums\TicketStatus;
use App\Events\TicketAnalysisCompleted;
use App\Events\TicketAnalysisStarted;
use App\Events\TicketSolutionPlanApproved;
use App\Events\TicketSolutionPlanRevisionRequested;
use App\Events\TicketSolutionPlanSubmitted;
use App\Models\Division;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketPriority;
use App\Models\User;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class AnalysisSolutionPlanningTest extends TestCase
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

    public function test_only_active_pic_can_start_analysis_and_stale_start_is_conflict(): void
    {
        Event::fake([TicketAnalysisStarted::class]);
        $ticket = $this->assignedTicket();
        $this->actingAs($this->otherPic)->postJson("/api/v1/pic/tickets/{$ticket->id}/start-analysis")->assertForbidden();
        $this->actingAs($this->requester)->postJson("/api/v1/pic/tickets/{$ticket->id}/start-analysis")->assertForbidden();
        $this->actingAs($this->pic)->postJson("/api/v1/pic/tickets/{$ticket->id}/start-analysis")->assertOk()->assertJsonPath('data.status', 'analysis')->assertJsonStructure(['meta' => ['request_id']]);
        $this->assertDatabaseHas('ticket_status_histories', ['ticket_id' => $ticket->id, 'action' => 'analysis_started', 'actor_id' => $this->pic->id]);
        Event::assertDispatched(TicketAnalysisStarted::class);
        $this->actingAs($this->pic)->postJson("/api/v1/pic/tickets/{$ticket->id}/start-analysis")->assertConflict();
        $invalid = $this->assignedTicket(TicketStatus::Triage);
        $this->actingAs($this->pic)->postJson("/api/v1/pic/tickets/{$invalid->id}/start-analysis")->assertConflict();
    }

    public function test_analysis_validates_updates_with_optimistic_lock_and_completes(): void
    {
        Event::fake([TicketAnalysisCompleted::class]);
        $ticket = $this->assignedTicket(TicketStatus::Analysis);
        $this->actingAs($this->pic)->postJson("/api/v1/pic/tickets/{$ticket->id}/analysis", [])->assertUnprocessable()->assertJsonValidationErrors(['problem_summary', 'technical_impact']);
        $created = $this->actingAs($this->pic)->postJson("/api/v1/pic/tickets/{$ticket->id}/analysis", $this->analysisPayload(rootCause: null))->assertCreated()->assertJsonPath('data.version', 1)->assertJsonPath('data.lock_version', 1);
        $id = $created->json('data.id');
        $this->actingAs($this->pic)->postJson("/api/v1/pic/tickets/{$ticket->id}/analysis/{$id}/complete")->assertUnprocessable()->assertJsonValidationErrors('root_cause');
        $updated = $this->analysisPayload();
        $updated['expected_lock_version'] = 1;
        $this->actingAs($this->pic)->putJson("/api/v1/pic/tickets/{$ticket->id}/analysis/{$id}", $updated)->assertOk()->assertJsonPath('data.lock_version', 2);
        $this->actingAs($this->pic)->putJson("/api/v1/pic/tickets/{$ticket->id}/analysis/{$id}", $updated)->assertConflict();
        $this->actingAs($this->pic)->postJson("/api/v1/pic/tickets/{$ticket->id}/analysis/{$id}/complete")->assertOk()->assertJsonPath('data.completed_at', fn ($value) => $value !== null);
        $this->assertSame(TicketStatus::SolutionPlanning, $ticket->fresh()->status);
        $this->assertDatabaseHas('ticket_analyses', ['id' => $id, 'version' => 1, 'analyst_id' => $this->pic->id, 'is_current' => true]);
        $this->assertDatabaseHas('ticket_status_histories', ['ticket_id' => $ticket->id, 'action' => 'analysis_completed']);
        Event::assertDispatched(TicketAnalysisCompleted::class);
        $this->actingAs($this->pic)->putJson("/api/v1/pic/tickets/{$ticket->id}/analysis/{$id}", [...$updated, 'expected_lock_version' => 2])->assertConflict();
    }

    public function test_solution_plan_validation_and_submitted_plan_is_immutable(): void
    {
        Event::fake([TicketSolutionPlanSubmitted::class]);
        [$ticket] = $this->completedAnalysisTicket();
        $this->actingAs($this->pic)->postJson("/api/v1/pic/tickets/{$ticket->id}/solution-plan", [])->assertUnprocessable()->assertJsonValidationErrors(['solution_summary', 'implementation_steps', 'estimated_effort_minutes', 'risk_level', 'testing_plan']);
        $this->actingAs($this->pic)->postJson("/api/v1/pic/tickets/{$ticket->id}/solution-plan", $this->planPayload(['estimated_effort_minutes' => 0]))->assertUnprocessable()->assertJsonValidationErrors('estimated_effort_minutes');
        $this->actingAs($this->pic)->postJson("/api/v1/pic/tickets/{$ticket->id}/solution-plan", $this->planPayload(['risk_level' => 'high', 'rollback_plan' => null]))->assertUnprocessable()->assertJsonValidationErrors('rollback_plan');
        $created = $this->actingAs($this->pic)->postJson("/api/v1/pic/tickets/{$ticket->id}/solution-plan", $this->planPayload())->assertCreated()->assertJsonPath('data.status', 'draft');
        $planId = $created->json('data.id');
        $this->actingAs($this->pic)->postJson("/api/v1/pic/tickets/{$ticket->id}/solution-plan/{$planId}/submit")->assertOk()->assertJsonPath('data.status', 'submitted');
        $this->assertSame(TicketStatus::PlanReview, $ticket->fresh()->status);
        $this->actingAs($this->pic)->putJson("/api/v1/pic/tickets/{$ticket->id}/solution-plan/{$planId}", [...$this->planPayload(), 'expected_lock_version' => 1])->assertConflict();
        Event::assertDispatched(TicketSolutionPlanSubmitted::class);
    }

    public function test_it_lead_revision_creates_new_version_then_approval_is_final_and_not_repeatable(): void
    {
        Event::fake([TicketSolutionPlanRevisionRequested::class, TicketSolutionPlanApproved::class]);
        [$ticket, $first] = $this->submittedPlanTicket();
        $this->actingAs($this->itLead)->postJson("/api/v1/it-lead/tickets/{$ticket->id}/solution-plan/{$first}/request-revision", [])->assertUnprocessable()->assertJsonValidationErrors('review_notes');
        $this->actingAs($this->itLead)->postJson("/api/v1/it-lead/tickets/{$ticket->id}/solution-plan/{$first}/request-revision", ['review_notes' => 'Add database rollback and integration checks.'])->assertOk()->assertJsonPath('data.status', 'revision_requested');
        $this->assertSame(TicketStatus::SolutionPlanning, $ticket->fresh()->status);
        Event::assertDispatched(TicketSolutionPlanRevisionRequested::class);
        $revision = $this->actingAs($this->pic)->postJson("/api/v1/pic/tickets/{$ticket->id}/solution-plan", $this->planPayload(['solution_summary' => 'Revised safe solution']))->assertCreated()->assertJsonPath('data.version', 2);
        $second = $revision->json('data.id');
        $this->assertDatabaseHas('ticket_solution_plans', ['id' => $first, 'version' => 1, 'status' => 'revision_requested', 'is_current' => false]);
        $this->actingAs($this->pic)->postJson("/api/v1/pic/tickets/{$ticket->id}/solution-plan/{$second}/submit")->assertOk();
        $this->actingAs($this->itLead)->postJson("/api/v1/it-lead/tickets/{$ticket->id}/solution-plan/{$second}/approve")->assertOk()->assertJsonPath('data.status', 'approved');
        $this->assertSame(TicketStatus::ReadyForDevelopment, $ticket->fresh()->status);
        $this->actingAs($this->itLead)->postJson("/api/v1/it-lead/tickets/{$ticket->id}/solution-plan/{$second}/approve")->assertConflict();
        Event::assertDispatched(TicketSolutionPlanApproved::class);
        foreach (['analysis_completed', 'solution_plan_created', 'solution_plan_submitted', 'solution_plan_revision_requested', 'solution_plan_resubmitted', 'solution_plan_approved'] as $action) {
            $this->assertDatabaseHas('ticket_status_histories', ['ticket_id' => $ticket->id, 'action' => $action]);
        }
    }

    public function test_review_queue_is_authorized_filtered_and_paginated(): void
    {
        [$ticket] = $this->submittedPlanTicket();
        $this->actingAs($this->itLead)->getJson('/api/v1/it-lead/plan-review-queue?search=Phase&per_page=1')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $ticket->id)->assertJsonPath('meta.pagination.total', 1)->assertJsonStructure(['meta' => ['request_id']]);
        $this->actingAs($this->requester)->getJson('/api/v1/it-lead/plan-review-queue')->assertForbidden();
        $this->actingAs($this->pic)->getJson('/api/v1/it-lead/plan-review-queue')->assertForbidden();
    }

    public function test_requester_is_redacted_and_non_technical_roles_cannot_open_phase7_endpoints(): void
    {
        [$ticket] = $this->submittedPlanTicket();
        $response = $this->actingAs($this->requester)->getJson("/api/v1/tickets/{$ticket->id}")->assertOk()->assertJsonPath('data.status', 'plan_review')->assertJsonPath('data.solution_plan_summary.status', 'submitted');
        $response->assertJsonMissingPath('data.current_analysis')->assertJsonMissingPath('data.root_cause')->assertJsonMissingPath('data.implementation_steps')->assertJsonMissingPath('data.risk_level');
        $history = collect($response->json('data.history'))->firstWhere('action', 'solution_plan_submitted');
        $this->assertNull($history['notes']);
        $this->assertNull($history['metadata']);
        $this->actingAs($this->requester)->getJson("/api/v1/pic/tickets/{$ticket->id}/analysis")->assertForbidden();
        $this->actingAs($this->user('executive'))->getJson("/api/v1/it-lead/tickets/{$ticket->id}/solution-plan")->assertForbidden();
    }

    private function completedAnalysisTicket(): array
    {
        $ticket = $this->assignedTicket(TicketStatus::Analysis);
        $analysis = $this->actingAs($this->pic)->postJson("/api/v1/pic/tickets/{$ticket->id}/analysis", $this->analysisPayload())->json('data');
        $this->actingAs($this->pic)->postJson("/api/v1/pic/tickets/{$ticket->id}/analysis/{$analysis['id']}/complete")->assertOk();

        return [$ticket->fresh(), $analysis['id']];
    }

    private function submittedPlanTicket(): array
    {
        [$ticket] = $this->completedAnalysisTicket();
        $plan = $this->actingAs($this->pic)->postJson("/api/v1/pic/tickets/{$ticket->id}/solution-plan", $this->planPayload())->json('data');
        $this->actingAs($this->pic)->postJson("/api/v1/pic/tickets/{$ticket->id}/solution-plan/{$plan['id']}/submit")->assertOk();

        return [$ticket->fresh(), $plan['id']];
    }

    private function analysisPayload(?string $rootCause = 'Missing deployment validation'): array
    {
        return ['problem_summary' => 'Policy PDF generation fails after deployment.', 'root_cause' => $rootCause, 'technical_impact' => 'Template loader returns a file-not-found exception.', 'business_impact' => 'Policy issuance is delayed.', 'affected_components' => ['PDF service'], 'evidence' => 'See ticket attachment metadata.', 'assumptions' => 'Latest release is active.', 'limitations' => 'Production shell access unavailable.'];
    }

    private function planPayload(array $overrides = []): array
    {
        return [...['solution_summary' => 'Restore template and add release validation.', 'implementation_steps' => [['order' => 1, 'description' => 'Restore the approved template.'], ['order' => 2, 'description' => 'Add a deployment smoke test.']], 'affected_components' => ['PDF service'], 'dependencies' => ['Release pipeline'], 'estimated_effort_minutes' => 480, 'risk_level' => 'medium', 'risk_description' => 'Short maintenance window.', 'rollback_plan' => 'Restore the prior package.', 'testing_plan' => 'Generate and compare a sample policy PDF.', 'deployment_consideration' => 'Deploy outside issuance peak.'], ...$overrides];
    }

    private function assignedTicket(TicketStatus $status = TicketStatus::Assigned): Ticket
    {
        static $number = 0;
        $ticket = Ticket::create(['ticket_number' => 'TIC-202607-'.str_pad((string) ++$number, 6, '0', STR_PAD_LEFT), 'requester_id' => $this->requester->id, 'division_id' => $this->division->id, 'current_division_id' => $this->division->id, 'ticket_category_id' => TicketCategory::firstOrFail()->id, 'requested_priority_id' => TicketPriority::where('key', 'medium')->firstOrFail()->id, 'title' => 'Phase 7 ticket', 'description' => 'Description', 'status' => $status, 'submitted_at' => now(), 'validated_at' => now(), 'triage_started_at' => now(), 'assigned_at' => now(), 'analysis_started_at' => in_array($status, [TicketStatus::Analysis, TicketStatus::SolutionPlanning, TicketStatus::PlanReview], true) ? now() : null, 'current_assignee_id' => $this->pic->id, 'assigned_by' => $this->itLead->id]);
        $ticket->assignments()->create(['assigned_to' => $this->pic->id, 'assigned_by' => $this->itLead->id, 'assignment_type' => 'primary', 'started_at' => now(), 'is_current' => true]);

        return $ticket;
    }

    private function user(string $role): User
    {
        return User::factory()->create(['role_id' => Role::where('key', $role)->firstOrFail()->id, 'division_id' => $this->division->id, 'is_active' => true]);
    }
}
