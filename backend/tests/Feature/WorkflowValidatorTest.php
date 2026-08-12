<?php

namespace Tests\Feature;

use App\Models\WorkflowDefinition;
use App\Services\WorkflowValidatorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkflowValidatorTest extends TestCase
{
    use RefreshDatabase;

    private WorkflowValidatorService $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = app(WorkflowValidatorService::class);
    }

    private function buildMinimalValidWorkflow(): WorkflowDefinition
    {
        $wf = WorkflowDefinition::factory()->create(['version' => 1]);
        $initial = $wf->stages()->create(['stage_key' => 'start',  'name' => 'Start', 'order' => 1, 'is_initial' => true,  'is_terminal' => false, 'stage_type' => 'normal']);
        $terminal = $wf->stages()->create(['stage_key' => 'done',   'name' => 'Done',  'order' => 2, 'is_initial' => false, 'is_terminal' => true,  'stage_type' => 'normal']);
        $t = $wf->transitions()->create(['from_stage_id' => $initial->id, 'to_stage_id' => $terminal->id, 'action_key' => 'complete', 'name' => 'Complete', 'requires_notes' => false]);
        $t->permissions()->create(['role_key' => 'supervisor_it']);

        return $wf;
    }

    public function test_accepts_minimal_valid_workflow(): void
    {
        $wf = $this->buildMinimalValidWorkflow();
        $errors = $this->validator->validate($wf);
        $this->assertEmpty($errors, 'Expected no validation errors: '.implode(', ', $errors));
    }

    public function test_rejects_workflow_without_initial_stage(): void
    {
        $wf = WorkflowDefinition::factory()->create(['version' => 1]);
        $wf->stages()->create(['stage_key' => 'only', 'name' => 'Only', 'order' => 1, 'is_initial' => false, 'is_terminal' => true, 'stage_type' => 'normal']);
        $errors = $this->validator->validate($wf);
        $this->assertNotEmpty($errors);
        $this->assertTrue(collect($errors)->contains(fn ($e) => str_contains($e, 'initial stage')));
    }

    public function test_rejects_workflow_with_more_than_one_initial_stage(): void
    {
        $wf = WorkflowDefinition::factory()->create(['version' => 1]);
        $s1 = $wf->stages()->create(['stage_key' => 's1', 'name' => 'S1', 'order' => 1, 'is_initial' => true, 'is_terminal' => false, 'stage_type' => 'normal']);
        $s2 = $wf->stages()->create(['stage_key' => 's2', 'name' => 'S2', 'order' => 2, 'is_initial' => true, 'is_terminal' => true, 'stage_type' => 'normal']);
        $t = $wf->transitions()->create(['from_stage_id' => $s1->id, 'to_stage_id' => $s2->id, 'action_key' => 'go', 'name' => 'Go', 'requires_notes' => false]);
        $t->permissions()->create(['role_key' => 'supervisor_it']);
        $errors = $this->validator->validate($wf);
        $this->assertTrue(collect($errors)->contains(fn ($e) => str_contains($e, 'lebih dari satu initial')));
    }

    public function test_rejects_workflow_without_terminal_stage(): void
    {
        $wf = WorkflowDefinition::factory()->create(['version' => 1]);
        $wf->stages()->create(['stage_key' => 's1', 'name' => 'S1', 'order' => 1, 'is_initial' => true, 'is_terminal' => false, 'stage_type' => 'normal']);
        $errors = $this->validator->validate($wf);
        $this->assertTrue(collect($errors)->contains(fn ($e) => str_contains($e, 'terminal stage')));
    }

    public function test_rejects_unreachable_stage(): void
    {
        $wf = WorkflowDefinition::factory()->create(['version' => 1]);
        $s1 = $wf->stages()->create(['stage_key' => 's1', 'name' => 'S1', 'order' => 1, 'is_initial' => true, 'is_terminal' => false, 'stage_type' => 'normal']);
        $s2 = $wf->stages()->create(['stage_key' => 's2', 'name' => 'S2', 'order' => 2, 'is_initial' => false, 'is_terminal' => true, 'stage_type' => 'normal']);
        $s3 = $wf->stages()->create(['stage_key' => 's3', 'name' => 'S3', 'order' => 3, 'is_initial' => false, 'is_terminal' => false, 'stage_type' => 'normal']);

        $t = $wf->transitions()->create(['from_stage_id' => $s1->id, 'to_stage_id' => $s2->id, 'action_key' => 'go',  'name' => 'Go',  'requires_notes' => false]);
        $t->permissions()->create(['role_key' => 'supervisor_it']);
        $t2 = $wf->transitions()->create(['from_stage_id' => $s3->id, 'to_stage_id' => $s2->id, 'action_key' => 'go2', 'name' => 'Go2', 'requires_notes' => false]);
        $t2->permissions()->create(['role_key' => 'supervisor_it']);

        $errors = $this->validator->validate($wf);
        $this->assertTrue(collect($errors)->contains(fn ($e) => str_contains($e, 's3') && str_contains($e, 'tidak dapat dicapai')));
    }

    public function test_rejects_transition_without_permission(): void
    {
        $wf = WorkflowDefinition::factory()->create(['version' => 1]);
        $s1 = $wf->stages()->create(['stage_key' => 's1', 'name' => 'S1', 'order' => 1, 'is_initial' => true,  'is_terminal' => false, 'stage_type' => 'normal']);
        $s2 = $wf->stages()->create(['stage_key' => 's2', 'name' => 'S2', 'order' => 2, 'is_initial' => false, 'is_terminal' => true,  'stage_type' => 'normal']);
        // No permissions created
        $wf->transitions()->create(['from_stage_id' => $s1->id, 'to_stage_id' => $s2->id, 'action_key' => 'go', 'name' => 'Go', 'requires_notes' => false]);
        $errors = $this->validator->validate($wf);
        $this->assertTrue(collect($errors)->contains(fn ($e) => str_contains($e, 'permission actor')));
    }

    public function test_rejects_duplicate_action_key_on_same_stage(): void
    {
        $wf = WorkflowDefinition::factory()->create(['version' => 1]);
        $s1 = $wf->stages()->create(['stage_key' => 's1', 'name' => 'S1', 'order' => 1, 'is_initial' => true,  'is_terminal' => false, 'stage_type' => 'normal']);
        $s2 = $wf->stages()->create(['stage_key' => 's2', 'name' => 'S2', 'order' => 2, 'is_initial' => false, 'is_terminal' => true,  'stage_type' => 'normal']);

        $t1 = $wf->transitions()->create(['from_stage_id' => $s1->id, 'to_stage_id' => $s2->id, 'action_key' => 'dup_action', 'name' => 'T1', 'requires_notes' => false]);
        $t1->permissions()->create(['role_key' => 'supervisor_it']);
        $t2 = $wf->transitions()->create(['from_stage_id' => $s1->id, 'to_stage_id' => $s2->id, 'action_key' => 'dup_action', 'name' => 'T2', 'requires_notes' => false]);
        $t2->permissions()->create(['role_key' => 'supervisor_it']);

        $errors = $this->validator->validate($wf);
        $this->assertTrue(collect($errors)->contains(fn ($e) => str_contains($e, 'action_key duplikat')));
    }

    public function test_rejects_unknown_role_key(): void
    {
        $wf = WorkflowDefinition::factory()->create(['version' => 1]);
        $s1 = $wf->stages()->create(['stage_key' => 's1', 'name' => 'S1', 'order' => 1, 'is_initial' => true,  'is_terminal' => false, 'stage_type' => 'normal']);
        $s2 = $wf->stages()->create(['stage_key' => 's2', 'name' => 'S2', 'order' => 2, 'is_initial' => false, 'is_terminal' => true,  'stage_type' => 'normal']);
        $t = $wf->transitions()->create(['from_stage_id' => $s1->id, 'to_stage_id' => $s2->id, 'action_key' => 'go', 'name' => 'Go', 'requires_notes' => false]);
        $t->permissions()->create(['role_key' => 'unknown_alien_role']);

        $errors = $this->validator->validate($wf);
        $this->assertTrue(collect($errors)->contains(fn ($e) => str_contains($e, 'role_key tidak dikenal')));
    }

    public function test_rejects_empty_permission_entry(): void
    {
        $wf = $this->buildMinimalValidWorkflow();
        $wf->transitions()->firstOrFail()->permissions()->update([
            'role_key' => null,
            'permission_code' => null,
        ]);

        $errors = $this->validator->validate($wf);

        $this->assertTrue(collect($errors)->contains(fn ($e) => str_contains($e, 'permission actor kosong')));
    }

    public function test_rejects_non_supervisor_it_approval_role(): void
    {
        $wf = $this->buildMinimalValidWorkflow();
        $approvalStage = $wf->stages()->where('stage_key', 'done')->firstOrFail();
        $approvalStage->update(['stage_type' => 'approval']);
        $config = $wf->approvalConfigs()->create([
            'stage_id' => $approvalStage->id,
            'approval_type' => 'single',
        ]);
        $config->steps()->create([
            'step_order' => 1,
            'approver_role_key' => 'manager',
        ]);

        $errors = $this->validator->validate($wf);

        $this->assertTrue(collect($errors)->contains(fn ($e) => str_contains($e, "approver_role_key 'supervisor_it'")));
    }

    public function test_rejects_multiple_approval_steps(): void
    {
        $wf = $this->buildMinimalValidWorkflow();
        $approvalStage = $wf->stages()->where('stage_key', 'done')->firstOrFail();
        $approvalStage->update(['stage_type' => 'approval']);
        $config = $wf->approvalConfigs()->create([
            'stage_id' => $approvalStage->id,
            'approval_type' => 'single',
        ]);
        $config->steps()->createMany([
            ['step_order' => 1, 'approver_role_key' => 'supervisor_it'],
            ['step_order' => 2, 'approver_role_key' => 'supervisor_it'],
        ]);

        $errors = $this->validator->validate($wf);

        $this->assertTrue(collect($errors)->contains(fn ($error) => str_contains($error, 'tepat satu approval step')));
    }
}
