<?php

namespace Tests\Feature;

use App\Models\WorkflowDefinition;
use App\Services\WorkflowSnapshotBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests for WorkflowSnapshotBuilder.
 */
class WorkflowSnapshotTest extends TestCase
{
    use RefreshDatabase;

    private WorkflowSnapshotBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = app(WorkflowSnapshotBuilder::class);
    }

    private function buildMinimalWorkflow(): WorkflowDefinition
    {
        $wf = WorkflowDefinition::factory()->create(['version' => 2, 'code' => 'snap_test']);

        $s1 = $wf->stages()->create(['stage_key' => 'submitted', 'name' => 'Submitted', 'order' => 1, 'is_initial' => true,  'is_terminal' => false, 'stage_type' => 'normal']);
        $s2 = $wf->stages()->create(['stage_key' => 'done',      'name' => 'Done',      'order' => 2, 'is_initial' => false, 'is_terminal' => true,  'stage_type' => 'normal']);

        $t = $wf->transitions()->create([
            'from_stage_id' => $s1->id,
            'to_stage_id' => $s2->id,
            'action_key' => 'complete',
            'name' => 'Complete',
            'requires_notes' => false,
        ]);
        $t->permissions()->create(['role_key' => 'supervisor_it']);
        $t->notifications()->create(['recipient_type' => 'requester', 'channel' => 'database', 'template_code' => 'ticket.done']);

        return $wf;
    }

    public function test_builds_snapshot_with_correct_structure(): void
    {
        $wf = $this->buildMinimalWorkflow();
        $snapshot = $this->builder->build($wf);

        $this->assertEquals('snap_test', $snapshot['workflow_code']);
        $this->assertEquals(2, $snapshot['workflow_version']);
        $this->assertEquals('submitted', $snapshot['initial_stage']);
        $this->assertEquals(['done'], $snapshot['terminal_stages']);
        $this->assertCount(2, $snapshot['stages']);
        $this->assertNotNull($snapshot['snapshot_built_at']);
    }

    public function test_snapshot_stages_have_transitions_with_permissions(): void
    {
        $wf = $this->buildMinimalWorkflow();
        $snapshot = $this->builder->build($wf);

        $submittedStage = collect($snapshot['stages'])->firstWhere('stage_key', 'submitted');
        $this->assertNotNull($submittedStage);
        $this->assertCount(1, $submittedStage['transitions']);

        $transition = $submittedStage['transitions'][0];
        $this->assertEquals('complete', $transition['action_key']);
        $this->assertEquals('done', $transition['to_stage_key']);
        $this->assertNotEmpty($transition['permissions']);
        $this->assertEquals('supervisor_it', $transition['permissions'][0]['role_key']);
        $this->assertNotEmpty($transition['notifications']);
    }

    public function test_snapshot_includes_approval_configuration_fields(): void
    {
        $wf = $this->buildMinimalWorkflow();
        $stage = $wf->stages()->where('stage_key', 'submitted')->firstOrFail();
        $stage->update(['stage_type' => 'approval']);
        $config = $wf->approvalConfigs()->create([
            'stage_id' => $stage->id,
            'approval_type' => 'single',
            'label' => 'Supervisor Review',
            'notes_required' => true,
            'is_active' => true,
        ]);
        $config->steps()->create(['step_order' => 1, 'approver_role_key' => 'supervisor_it']);

        $approval = collect($this->builder->build($wf)['stages'])->firstWhere('stage_key', 'submitted')['approval'];

        $this->assertSame('Supervisor Review', $approval['label']);
        $this->assertTrue($approval['notes_required']);
        $this->assertTrue($approval['is_active']);
        $this->assertSame('supervisor_it', $approval['steps'][0]['approver_role_key']);
    }

    public function test_snapshot_is_immutable_after_workflow_change(): void
    {
        $wf = $this->buildMinimalWorkflow();
        $snapshot1 = $this->builder->build($wf);

        // Change workflow name (published cannot be changed - this simulates admin using create-version)
        $wf->update(['name' => 'Changed Name']);
        $snapshot2 = $this->builder->build($wf);

        // Both snapshots have the same stage structure
        $this->assertCount(count($snapshot1['stages']), $snapshot2['stages']);
        // But the name would differ if stored vs re-built (ticket stores snapshot1)
        $this->assertEquals('Changed Name', $snapshot2['workflow_name']);
    }

    public function test_get_stage_from_snapshot_returns_null_for_unknown_stage(): void
    {
        $wf = $this->buildMinimalWorkflow();
        $snapshot = $this->builder->build($wf);

        $stage = $this->builder->getStageFromSnapshot($snapshot, 'nonexistent_stage');
        $this->assertNull($stage);
    }

    public function test_is_terminal_returns_correctly(): void
    {
        $wf = $this->buildMinimalWorkflow();
        $snapshot = $this->builder->build($wf);

        $this->assertTrue($this->builder->isTerminal($snapshot, 'done'));
        $this->assertFalse($this->builder->isTerminal($snapshot, 'submitted'));
    }

    public function test_snapshot_too_large_throws_exception(): void
    {
        // Override max size
        config(['crm.workflow_snapshot_max_bytes' => 10]);

        $wf = $this->buildMinimalWorkflow();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/terlalu besar/');

        $this->builder->build($wf);
    }
}
