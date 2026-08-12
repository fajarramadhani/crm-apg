<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowDefinition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminWorkflowApprovalTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $requester;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::factory()->create(['key' => 'superadmin', 'name' => 'Super Admin']);
        $requesterRole = Role::factory()->create(['key' => 'requester', 'name' => 'Requester']);
        $this->admin = User::factory()->create(['role_id' => $adminRole->id, 'is_active' => true]);
        $this->requester = User::factory()->create(['role_id' => $requesterRole->id, 'is_active' => true]);
    }

    public function test_admin_can_get_and_put_single_supervisor_approval_config(): void
    {
        $workflow = WorkflowDefinition::factory()->create();
        $stage = $workflow->stages()->create([
            'stage_key' => 'pending_approval',
            'name' => 'Pending Approval',
            'order' => 1,
            'stage_type' => 'approval',
        ]);

        $this->actingAs($this->admin)
            ->getJson("/api/v1/admin/workflows/{$workflow->id}/approval")
            ->assertOk()
            ->assertJsonPath('data.approval', null);

        $this->actingAs($this->admin)
            ->putJson("/api/v1/admin/workflows/{$workflow->id}/approval", [
                'stage_id' => $stage->id,
                'label' => 'Final Supervisor Review',
                'notes_required' => true,
                'is_active' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.approval.approval_type', 'single')
            ->assertJsonPath('data.approval.approver_role_key', 'supervisor_it')
            ->assertJsonPath('data.approval.label', 'Final Supervisor Review')
            ->assertJsonPath('data.approval.notes_required', true);

        $this->assertDatabaseCount('workflow_approval_configs', 1);
        $this->assertDatabaseCount('workflow_approval_steps', 1);
        $this->assertDatabaseHas('workflow_approval_steps', [
            'step_order' => 1,
            'approver_role_key' => 'supervisor_it',
        ]);
    }

    public function test_put_rejects_non_approval_stage_and_unsupported_raw_shape(): void
    {
        $workflow = WorkflowDefinition::factory()->create();
        $stage = $workflow->stages()->create([
            'stage_key' => 'start',
            'name' => 'Start',
            'order' => 1,
            'stage_type' => 'normal',
        ]);

        $this->actingAs($this->admin)
            ->putJson("/api/v1/admin/workflows/{$workflow->id}/approval", [
                'stage_id' => $stage->id,
                'label' => 'Review',
                'notes_required' => false,
                'is_active' => true,
                'steps' => [['approver_role_key' => 'manager']],
                'query' => 'select * from users',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['stage_id', 'steps', 'query']);
    }

    public function test_published_workflow_approval_is_read_only(): void
    {
        $workflow = WorkflowDefinition::factory()->published()->create();
        $stage = $workflow->stages()->create([
            'stage_key' => 'pending_approval',
            'name' => 'Pending Approval',
            'order' => 1,
            'stage_type' => 'approval',
        ]);

        $this->actingAs($this->admin)
            ->putJson("/api/v1/admin/workflows/{$workflow->id}/approval", [
                'stage_id' => $stage->id,
                'label' => 'Review',
                'notes_required' => false,
                'is_active' => true,
            ])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'WORKFLOW_NOT_DRAFT');
    }

    public function test_user_without_permission_cannot_view_or_update_approval(): void
    {
        $workflow = WorkflowDefinition::factory()->create();

        $this->actingAs($this->requester)
            ->getJson("/api/v1/admin/workflows/{$workflow->id}/approval")
            ->assertForbidden();
    }
}
