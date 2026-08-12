<?php

namespace Tests\Feature;

use App\Enums\TicketStatus;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Services\WorkflowEngineService;
use App\Services\WorkflowSnapshotBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Tests for WorkflowEngineService runtime: availableActions, canTransition, executeTransition.
 */
class DynamicWorkflowTransitionTest extends TestCase
{
    use RefreshDatabase;

    private WorkflowEngineService $engine;

    private WorkflowSnapshotBuilder $snapshotBuilder;

    private User $supervisorIt;

    private User $pic;

    private User $requester;

    private Ticket $dynamicTicket;

    private WorkflowDefinition $workflow;

    private array $snapshot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->engine = app(WorkflowEngineService::class);
        $this->snapshotBuilder = app(WorkflowSnapshotBuilder::class);

        $supervisorRole = Role::factory()->create(['key' => 'supervisor_it', 'name' => 'Supervisor IT']);
        $picRole = Role::factory()->create(['key' => 'pic_it_support', 'name' => 'PIC IT Support']);
        $requesterRole = Role::factory()->create(['key' => 'requester',     'name' => 'Requester']);

        $this->supervisorIt = User::factory()->create(['role_id' => $supervisorRole->id, 'is_active' => true]);
        $this->pic = User::factory()->create(['role_id' => $picRole->id, 'is_active' => true]);
        $this->requester = User::factory()->create(['role_id' => $requesterRole->id, 'is_active' => true]);

        $this->workflow = $this->buildWorkflow();
        $this->snapshot = $this->snapshotBuilder->build($this->workflow);

        $this->dynamicTicket = Ticket::factory()->create([
            'status' => TicketStatus::Submitted,
            'workflow_id' => $this->workflow->id,
            'workflow_version' => 1,
            'workflow_snapshot' => $this->snapshot,
            'workflow_mode' => 'dynamic',
            'current_workflow_stage' => 'submitted',
            'workflow_stage_entered_at' => now(),
        ]);
    }

    private function buildWorkflow(): WorkflowDefinition
    {
        $wf = WorkflowDefinition::factory()->active()->create(['version' => 1, 'code' => 'test_engine_wf']);

        $submitted = $wf->stages()->create(['stage_key' => 'submitted',      'name' => 'Submitted',       'order' => 1, 'is_initial' => true,  'is_terminal' => false, 'stage_type' => 'normal']);
        $inProgress = $wf->stages()->create(['stage_key' => 'in_progress',    'name' => 'In Progress',     'order' => 2, 'is_initial' => false, 'is_terminal' => false, 'stage_type' => 'normal']);
        $needInfo = $wf->stages()->create(['stage_key' => 'need_info',      'name' => 'Need Info',       'order' => 3, 'is_initial' => false, 'is_terminal' => false, 'stage_type' => 'special']);
        $approval = $wf->stages()->create(['stage_key' => 'pending_approval', 'name' => 'Approval',       'order' => 4, 'is_initial' => false, 'is_terminal' => false, 'stage_type' => 'approval']);
        $done = $wf->stages()->create(['stage_key' => 'done',           'name' => 'Done',            'order' => 5, 'is_initial' => false, 'is_terminal' => true,  'stage_type' => 'normal']);
        $cancelled = $wf->stages()->create(['stage_key' => 'cancelled',      'name' => 'Cancelled',       'order' => 6, 'is_initial' => false, 'is_terminal' => true,  'stage_type' => 'special']);

        // submitted → in_progress (supervisor)
        $t1 = $wf->transitions()->create(['from_stage_id' => $submitted->id,  'to_stage_id' => $inProgress->id, 'action_key' => 'start_analysis', 'name' => 'Start',    'requires_notes' => false]);
        $t1->permissions()->create(['role_key' => 'supervisor_it']);

        // submitted → cancelled (supervisor)
        $t2 = $wf->transitions()->create(['from_stage_id' => $submitted->id,  'to_stage_id' => $cancelled->id,  'action_key' => 'cancel',          'name' => 'Cancel',   'requires_notes' => true]);
        $t2->permissions()->create(['role_key' => 'supervisor_it']);

        // in_progress → need_info (pic)
        $t3 = $wf->transitions()->create(['from_stage_id' => $inProgress->id, 'to_stage_id' => $needInfo->id,   'action_key' => 'request_info',    'name' => 'Info',     'requires_notes' => true]);
        $t3->permissions()->create(['role_key' => 'pic_it_support']);

        // need_info → in_progress (requester)
        $t4 = $wf->transitions()->create(['from_stage_id' => $needInfo->id,   'to_stage_id' => $inProgress->id, 'action_key' => 'requester_reply', 'name' => 'Reply',    'requires_notes' => false]);
        $t4->permissions()->create(['role_key' => 'requester']);

        // in_progress → pending_approval (pic)
        $t5 = $wf->transitions()->create(['from_stage_id' => $inProgress->id, 'to_stage_id' => $approval->id,   'action_key' => 'submit_for_approval', 'name' => 'Submit', 'requires_notes' => false]);
        $t5->permissions()->create(['role_key' => 'pic_it_support']);

        // pending_approval → done (supervisor)
        $t6 = $wf->transitions()->create(['from_stage_id' => $approval->id,   'to_stage_id' => $done->id,        'action_key' => 'approve',         'name' => 'Approve',  'requires_notes' => false]);
        $t6->permissions()->create(['role_key' => 'supervisor_it']);

        return $wf;
    }

    // =========================================================================
    // canTransition
    // =========================================================================

    public function test_supervisor_can_transition_start_analysis(): void
    {
        $this->assertTrue($this->engine->canTransition($this->dynamicTicket, $this->supervisorIt, 'start_analysis'));
    }

    public function test_requester_cannot_transition_start_analysis(): void
    {
        $this->assertFalse($this->engine->canTransition($this->dynamicTicket, $this->requester, 'start_analysis'));
    }

    public function test_legacy_ticket_cannot_use_dynamic_transition(): void
    {
        $legacyTicket = Ticket::factory()->create(['workflow_mode' => null, 'status' => TicketStatus::PendingValidation]);
        $this->assertFalse($this->engine->canTransition($legacyTicket, $this->supervisorIt, 'start_analysis'));
    }

    public function test_unavailable_action_returns_false(): void
    {
        $this->assertFalse($this->engine->canTransition($this->dynamicTicket, $this->supervisorIt, 'nonexistent_action'));
    }

    // =========================================================================
    // availableActions
    // =========================================================================

    public function test_supervisor_sees_start_analysis_and_cancel_from_submitted(): void
    {
        $actions = $this->engine->availableActions($this->dynamicTicket, $this->supervisorIt);
        $codes = array_column($actions, 'code');

        $this->assertContains('start_analysis', $codes);
        $this->assertContains('cancel', $codes);
    }

    public function test_pic_sees_no_actions_from_submitted_stage(): void
    {
        $actions = $this->engine->availableActions($this->dynamicTicket, $this->pic);
        $this->assertEmpty($actions, 'PIC should see no actions from submitted stage');
    }

    // =========================================================================
    // executeTransition
    // =========================================================================

    public function test_supervisor_can_execute_start_analysis(): void
    {
        $updated = $this->engine->executeTransition($this->dynamicTicket, $this->supervisorIt, 'start_analysis');

        $this->assertEquals('in_progress', $updated->current_workflow_stage);
        $this->assertNotNull($updated->workflow_stage_entered_at);
    }

    public function test_executing_transition_records_history(): void
    {
        $this->engine->executeTransition($this->dynamicTicket, $this->supervisorIt, 'start_analysis');

        $this->assertDatabaseHas('ticket_status_histories', [
            'ticket_id' => $this->dynamicTicket->id,
            'from_status' => 'submitted',
            'to_status' => 'in_progress',
            'action' => 'start_analysis',
            'actor_id' => $this->supervisorIt->id,
        ]);
    }

    public function test_cancel_transition_requires_notes(): void
    {
        // Without notes → validation fails
        $this->expectException(HttpException::class);
        $this->engine->executeTransition($this->dynamicTicket, $this->supervisorIt, 'cancel', []);
    }

    public function test_cancel_with_notes_succeeds(): void
    {
        $updated = $this->engine->executeTransition($this->dynamicTicket, $this->supervisorIt, 'cancel', ['notes' => 'Testing cancellation.']);
        $this->assertEquals('cancelled', $updated->current_workflow_stage);
    }

    public function test_active_approval_config_can_require_notes_for_approve(): void
    {
        $approvalStage = $this->workflow->stages()->where('stage_key', 'pending_approval')->firstOrFail();
        $config = $this->workflow->approvalConfigs()->create([
            'stage_id' => $approvalStage->id,
            'approval_type' => 'single',
            'label' => 'Supervisor Review',
            'notes_required' => true,
            'is_active' => true,
        ]);
        $config->steps()->create(['step_order' => 1, 'approver_role_key' => 'supervisor_it']);
        $this->dynamicTicket->update([
            'current_workflow_stage' => 'pending_approval',
            'workflow_snapshot' => $this->snapshotBuilder->build($this->workflow),
        ]);

        try {
            $this->engine->executeTransition($this->dynamicTicket->fresh(), $this->supervisorIt, 'approve');
            $this->fail('Approve without notes should fail.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }

        $updated = $this->engine->executeTransition(
            $this->dynamicTicket->fresh(),
            $this->supervisorIt,
            'approve',
            ['notes' => 'Reviewed and approved.']
        );

        $this->assertSame('done', $updated->current_workflow_stage);
    }

    public function test_concurrency_guard_raises_409_when_stage_has_changed(): void
    {
        // Simulate another process advancing the ticket
        $this->dynamicTicket->update(['current_workflow_stage' => 'in_progress']);

        $this->expectException(ConflictHttpException::class);

        $this->engine->executeTransition(
            $this->dynamicTicket,
            $this->supervisorIt,
            'start_analysis',
            [],
            'submitted' // expected stage mismatch
        );
    }

    public function test_http_endpoint_returns_403_when_transition_unauthorized(): void
    {
        $this->actingAs($this->requester)
            ->postJson("/api/v1/tickets/{$this->dynamicTicket->id}/workflow-transition", [
                'action_key' => 'start_analysis',
            ])
            ->assertStatus(403);
    }

    public function test_http_endpoint_returns_409_on_stage_conflict(): void
    {
        $this->actingAs($this->supervisorIt)
            ->postJson("/api/v1/tickets/{$this->dynamicTicket->id}/workflow-transition", [
                'action_key' => 'start_analysis',
                'expected_stage' => 'in_progress', // wrong expected stage
            ])
            ->assertStatus(409);
    }
}
