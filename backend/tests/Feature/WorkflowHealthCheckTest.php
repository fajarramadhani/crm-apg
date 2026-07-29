<?php

namespace Tests\Feature;

use App\Models\WorkflowDefinition;
use Database\Seeders\DefaultWorkflowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkflowHealthCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_fails_when_there_is_no_active_workflow(): void
    {
        $this->artisan('crm:workflow-check')->assertFailed();
    }

    public function test_fails_when_there_are_multiple_active_workflows(): void
    {
        $this->createValidWorkflow();
        $this->createValidWorkflow();

        $this->artisan('crm:workflow-check')->assertFailed();
    }

    public function test_fails_when_active_status_does_not_have_active_flag(): void
    {
        $this->createValidWorkflow(['is_active' => false]);

        $this->artisan('crm:workflow-check')->assertFailed();
    }

    public function test_fails_for_invalid_initial_stage(): void
    {
        $workflow = $this->createValidWorkflow();
        $workflow->stages()->update(['is_initial' => false]);

        $this->artisan('crm:workflow-check')->assertFailed();
    }

    public function test_fails_for_invalid_terminal_stage(): void
    {
        $workflow = $this->createValidWorkflow();
        $workflow->stages()->update(['is_terminal' => false]);

        $this->artisan('crm:workflow-check')->assertFailed();
    }

    public function test_fails_for_unreachable_stage(): void
    {
        $workflow = $this->createValidWorkflow();
        $workflow->stages()->create([
            'stage_key' => 'unreachable',
            'name' => 'Unreachable',
            'order' => 3,
            'is_initial' => false,
            'is_terminal' => true,
            'stage_type' => 'normal',
        ]);

        $this->artisan('crm:workflow-check')->assertFailed();
    }

    public function test_fails_for_transition_to_stage_outside_workflow(): void
    {
        $workflow = $this->createValidWorkflow();
        $otherWorkflow = WorkflowDefinition::factory()->create();
        $foreignStage = $otherWorkflow->stages()->create([
            'stage_key' => 'foreign',
            'name' => 'Foreign',
            'order' => 1,
            'is_initial' => true,
            'is_terminal' => true,
            'stage_type' => 'normal',
        ]);
        $workflow->transitions()->update(['to_stage_id' => $foreignStage->id]);

        $this->artisan('crm:workflow-check')->assertFailed();
    }

    public function test_fails_for_transition_without_permission(): void
    {
        $workflow = $this->createValidWorkflow();
        $workflow->transitions()->firstOrFail()->permissions()->delete();

        $this->artisan('crm:workflow-check')->assertFailed();
    }

    public function test_fails_when_snapshot_cannot_be_built(): void
    {
        $this->createValidWorkflow();
        config(['crm.workflow_snapshot_max_bytes' => 10]);

        $this->artisan('crm:workflow-check')->assertFailed();
    }

    public function test_all_fails_when_any_published_workflow_is_invalid(): void
    {
        $this->createValidWorkflow();
        WorkflowDefinition::factory()->published()->create();

        $this->artisan('crm:workflow-check', ['--all' => true])->assertFailed();
    }

    public function test_succeeds_for_valid_default_workflow(): void
    {
        $this->seed(DefaultWorkflowSeeder::class);
        WorkflowDefinition::query()->where('code', 'crm_default')->update([
            'config_status' => 'active',
            'is_active' => true,
            'published_at' => now(),
        ]);

        $this->artisan('crm:workflow-check')->assertSuccessful();
    }

    private function createValidWorkflow(array $attributes = []): WorkflowDefinition
    {
        $workflow = WorkflowDefinition::factory()->active()->create($attributes);
        $initial = $workflow->stages()->create([
            'stage_key' => 'start',
            'name' => 'Start',
            'order' => 1,
            'is_initial' => true,
            'is_terminal' => false,
            'stage_type' => 'normal',
        ]);
        $terminal = $workflow->stages()->create([
            'stage_key' => 'done',
            'name' => 'Done',
            'order' => 2,
            'is_initial' => false,
            'is_terminal' => true,
            'stage_type' => 'normal',
        ]);
        $transition = $workflow->transitions()->create([
            'from_stage_id' => $initial->id,
            'to_stage_id' => $terminal->id,
            'action_key' => 'complete',
            'name' => 'Complete',
            'requires_notes' => false,
        ]);
        $transition->permissions()->create(['role_key' => 'supervisor_it']);

        return $workflow;
    }
}
