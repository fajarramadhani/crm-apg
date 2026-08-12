<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Ticket;
use App\Models\User;
use App\Models\WorkflowDefinition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DynamicWorkflowDefinitionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $requester;

    private User $supervisorIt;

    protected function setUp(): void
    {
        parent::setUp();
        $adminRole = Role::factory()->create(['key' => 'superadmin', 'name' => 'Super Admin']);
        $requesterRole = Role::factory()->create(['key' => 'requester', 'name' => 'Requester']);
        $supervisorRole = Role::factory()->create(['key' => 'supervisor_it', 'name' => 'Supervisor IT']);

        $this->admin = User::factory()->create(['role_id' => $adminRole->id, 'is_active' => true]);
        $this->requester = User::factory()->create(['role_id' => $requesterRole->id, 'is_active' => true]);
        $this->supervisorIt = User::factory()->create(['role_id' => $supervisorRole->id, 'is_active' => true]);
    }

    public function test_admin_can_create_draft_workflow(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/api/v1/admin/workflows', [
                'code' => 'test_wf',
                'name' => 'Test Workflow',
                'version' => 1,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.workflow.code', 'test_wf')
            ->assertJsonPath('data.workflow.config_status', 'draft')
            ->assertJsonPath('data.workflow.is_active', false);
    }

    public function test_non_admin_cannot_create_workflow(): void
    {
        $this->actingAs($this->requester)
            ->postJson('/api/v1/admin/workflows', [
                'code' => 'test_wf',
                'name' => 'Test Workflow',
            ])
            ->assertStatus(403);

        $this->actingAs($this->supervisorIt)
            ->postJson('/api/v1/admin/workflows', [
                'code' => 'test_wf2',
                'name' => 'Test Workflow',
            ])
            ->assertStatus(403);
    }

    public function test_workflow_code_must_be_unique(): void
    {
        WorkflowDefinition::factory()->create(['code' => 'dup_wf', 'version' => 1]);

        $this->actingAs($this->admin)
            ->postJson('/api/v1/admin/workflows', [
                'code' => 'dup_wf',
                'name' => 'Duplicate',
                'version' => 1,
            ])
            ->assertStatus(422);
    }

    public function test_published_workflow_cannot_be_edited_directly(): void
    {
        $workflow = WorkflowDefinition::factory()->published()->create();

        $this->actingAs($this->admin)
            ->putJson("/api/v1/admin/workflows/{$workflow->id}", ['name' => 'New Name'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'WORKFLOW_NOT_DRAFT');
    }

    public function test_workflow_used_by_ticket_cannot_be_deleted(): void
    {
        $workflow = WorkflowDefinition::factory()->create(['config_status' => 'draft']);
        Ticket::factory()->create([
            'workflow_id' => $workflow->id,
            'workflow_mode' => 'dynamic',
        ]);

        $this->actingAs($this->admin)
            ->deleteJson("/api/v1/admin/workflows/{$workflow->id}")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'WORKFLOW_IN_USE');
    }

    public function test_creating_new_version_does_not_change_old_version(): void
    {
        $original = WorkflowDefinition::factory()->published()->create([
            'code' => 'versioned_wf',
            'version' => 1,
        ]);

        $this->actingAs($this->admin)
            ->postJson("/api/v1/admin/workflows/{$original->id}/create-version")
            ->assertStatus(201);

        $original->refresh();
        $this->assertEquals('published', $original->config_status);
        $this->assertEquals(1, $original->version);

        $this->assertDatabaseHas('workflow_definitions', [
            'code' => 'versioned_wf',
            'version' => 2,
            'config_status' => 'draft',
        ]);
    }

    public function test_creating_new_version_copies_stage_fields(): void
    {
        $original = WorkflowDefinition::factory()->published()->create([
            'code' => 'versioned_fields',
            'version' => 1,
        ]);
        $stage = $original->stages()->create([
            'stage_key' => 'start',
            'name' => 'Start',
            'order' => 1,
            'is_initial' => true,
            'is_terminal' => true,
            'stage_type' => 'normal',
        ]);
        $stage->fields()->create([
            'field_name' => 'resolution_notes',
            'is_required' => true,
            'is_readonly' => false,
            'is_hidden' => false,
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/v1/admin/workflows/{$original->id}/create-version")
            ->assertStatus(201);

        $newWorkflowId = $response->json('data.workflow.id');
        $this->assertDatabaseHas('workflow_stage_fields', [
            'stage_id' => WorkflowDefinition::query()->findOrFail($newWorkflowId)->stages()->firstOrFail()->id,
            'field_name' => 'resolution_notes',
            'is_required' => true,
        ]);
    }

    public function test_creating_new_version_copies_approval_fields(): void
    {
        $original = WorkflowDefinition::factory()->published()->create(['code' => 'versioned_approval']);
        $stage = $original->stages()->create([
            'stage_key' => 'pending_approval',
            'name' => 'Pending Approval',
            'order' => 1,
            'stage_type' => 'approval',
        ]);
        $config = $original->approvalConfigs()->create([
            'stage_id' => $stage->id,
            'approval_type' => 'single',
            'label' => 'Final Review',
            'notes_required' => true,
            'is_active' => false,
        ]);
        $config->steps()->create(['step_order' => 1, 'approver_role_key' => 'supervisor_it']);

        $newWorkflowId = $this->actingAs($this->admin)
            ->postJson("/api/v1/admin/workflows/{$original->id}/create-version")
            ->assertCreated()
            ->json('data.workflow.id');

        $this->assertDatabaseHas('workflow_approval_configs', [
            'workflow_id' => $newWorkflowId,
            'label' => 'Final Review',
            'notes_required' => true,
            'is_active' => false,
        ]);
    }

    public function test_admin_can_list_workflows(): void
    {
        WorkflowDefinition::factory()->count(3)->create();

        $this->actingAs($this->admin)
            ->getJson('/api/v1/admin/workflows')
            ->assertOk()
            ->assertJsonStructure(['data' => ['workflows', 'meta']]);
    }
}
