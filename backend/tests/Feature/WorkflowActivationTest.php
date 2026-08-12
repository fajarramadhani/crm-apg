<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowDefinition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests for workflow lifecycle: validate → publish → activate → deactivate.
 */
class WorkflowActivationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $adminRole = Role::factory()->create(['key' => 'superadmin']);
        $this->admin = User::factory()->create(['role_id' => $adminRole->id, 'is_active' => true]);
    }

    public function test_can_publish_a_validated_draft_workflow(): void
    {
        // Build minimal valid workflow
        $wf = WorkflowDefinition::factory()->create(['version' => 1, 'config_status' => 'draft']);
        $s1 = $wf->stages()->create(['stage_key' => 'start', 'name' => 'Start', 'order' => 1, 'is_initial' => true,  'is_terminal' => false, 'stage_type' => 'normal']);
        $s2 = $wf->stages()->create(['stage_key' => 'done',  'name' => 'Done',  'order' => 2, 'is_initial' => false, 'is_terminal' => true,  'stage_type' => 'normal']);
        $t = $wf->transitions()->create(['from_stage_id' => $s1->id, 'to_stage_id' => $s2->id, 'action_key' => 'complete', 'name' => 'Complete', 'requires_notes' => false]);
        $t->permissions()->create(['role_key' => 'supervisor_it']);

        $this->actingAs($this->admin)
            ->postJson("/api/v1/admin/workflows/{$wf->id}/publish")
            ->assertOk()
            ->assertJsonPath('data.workflow.config_status', 'published');

        $this->assertDatabaseHas('workflow_definitions', ['id' => $wf->id, 'config_status' => 'published']);
    }

    public function test_cannot_publish_invalid_draft_workflow(): void
    {
        // Workflow with no stages = invalid
        $wf = WorkflowDefinition::factory()->create(['version' => 1, 'config_status' => 'draft']);

        $this->actingAs($this->admin)
            ->postJson("/api/v1/admin/workflows/{$wf->id}/publish")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'WORKFLOW_INVALID');
    }

    public function test_can_activate_published_workflow(): void
    {
        $wf = WorkflowDefinition::factory()->published()->create(['version' => 1]);
        $s1 = $wf->stages()->create(['stage_key' => 'start', 'name' => 'Start', 'order' => 1, 'is_initial' => true,  'is_terminal' => false, 'stage_type' => 'normal']);
        $s2 = $wf->stages()->create(['stage_key' => 'done',  'name' => 'Done',  'order' => 2, 'is_initial' => false, 'is_terminal' => true,  'stage_type' => 'normal']);
        $t = $wf->transitions()->create(['from_stage_id' => $s1->id, 'to_stage_id' => $s2->id, 'action_key' => 'complete', 'name' => 'Complete', 'requires_notes' => false]);
        $t->permissions()->create(['role_key' => 'supervisor_it']);

        $this->actingAs($this->admin)
            ->postJson("/api/v1/admin/workflows/{$wf->id}/activate")
            ->assertOk()
            ->assertJsonPath('data.workflow.config_status', 'active')
            ->assertJsonPath('data.workflow.is_active', true);
    }

    public function test_activating_new_workflow_deactivates_previous_active_workflow(): void
    {
        $oldActive = WorkflowDefinition::factory()->active()->create();
        $newWf = WorkflowDefinition::factory()->published()->create(['version' => 1]);
        $s1 = $newWf->stages()->create(['stage_key' => 'start', 'name' => 'Start', 'order' => 1, 'is_initial' => true, 'is_terminal' => false, 'stage_type' => 'normal']);
        $s2 = $newWf->stages()->create(['stage_key' => 'done', 'name' => 'Done', 'order' => 2, 'is_initial' => false, 'is_terminal' => true, 'stage_type' => 'normal']);
        $t = $newWf->transitions()->create(['from_stage_id' => $s1->id, 'to_stage_id' => $s2->id, 'action_key' => 'complete', 'name' => 'Complete', 'requires_notes' => false]);
        $t->permissions()->create(['role_key' => 'supervisor_it']);

        $this->actingAs($this->admin)
            ->postJson("/api/v1/admin/workflows/{$newWf->id}/activate")
            ->assertOk();

        $this->assertDatabaseHas('workflow_definitions', [
            'id' => $oldActive->id,
            'config_status' => 'inactive',
            'is_active' => false,
        ]);

        $this->assertDatabaseHas('workflow_definitions', [
            'id' => $newWf->id,
            'config_status' => 'active',
            'is_active' => true,
        ]);
    }

    public function test_can_deactivate_active_workflow(): void
    {
        $wf = WorkflowDefinition::factory()->active()->create();

        $this->actingAs($this->admin)
            ->postJson("/api/v1/admin/workflows/{$wf->id}/deactivate")
            ->assertOk()
            ->assertJsonPath('data.workflow.config_status', 'inactive')
            ->assertJsonPath('data.workflow.is_active', false);
    }

    public function test_cannot_activate_a_draft_workflow(): void
    {
        $wf = WorkflowDefinition::factory()->create(['config_status' => 'draft']);

        $this->actingAs($this->admin)
            ->postJson("/api/v1/admin/workflows/{$wf->id}/activate")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'WORKFLOW_NOT_PUBLISHED');
    }

    public function test_cannot_activate_a_published_workflow_that_is_no_longer_valid(): void
    {
        $wf = WorkflowDefinition::factory()->published()->create(['version' => 1]);

        $this->actingAs($this->admin)
            ->postJson("/api/v1/admin/workflows/{$wf->id}/activate")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'WORKFLOW_INVALID');

        $this->assertDatabaseHas('workflow_definitions', [
            'id' => $wf->id,
            'config_status' => 'published',
            'is_active' => false,
        ]);
    }
}
