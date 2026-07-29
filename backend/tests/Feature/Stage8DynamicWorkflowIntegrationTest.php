<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Division;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Services\DynamicAssignmentService;
use App\Services\RequesterTicketService;
use App\Services\WorkflowEngineService;
use Database\Seeders\DefaultWorkflowSeeder;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Stage8DynamicWorkflowIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private WorkflowEngineService $engine;

    private DynamicAssignmentService $assignments;

    private RequesterTicketService $requesterTickets;

    private WorkflowDefinition $workflow;

    private User $requester;

    private User $otherRequester;

    private User $supervisor;

    private User $supportPic;

    private User $developPic;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('tickets.attachment_disk', 'local'));
        $this->seed([RoleSeeder::class, MasterDataSeeder::class, DefaultWorkflowSeeder::class]);

        $this->workflow = WorkflowDefinition::query()
            ->where('code', 'crm_default')
            ->where('version', 1)
            ->firstOrFail();
        $this->activate($this->workflow);

        $division = Division::query()->where('code', 'IT')->firstOrFail();
        $this->requester = $this->user('requester', $division);
        $this->otherRequester = $this->user('requester', $division);
        $this->supervisor = $this->user('supervisor_it', $division);
        $this->supportPic = $this->user('pic_it_support', $division);
        $this->developPic = $this->user('pic_it_develop', $division);

        $this->engine = app(WorkflowEngineService::class);
        $this->assignments = app(DynamicAssignmentService::class);
        $this->requesterTickets = app(RequesterTicketService::class);
    }

    public function test_testing_master_data_has_exact_stage_8_category_and_system_names(): void
    {
        $this->assertEqualsCanonicalizing(
            ['Bug Sistem Internal', 'Bug Sistem dari Asuransi'],
            TicketCategory::query()
                ->whereIn('name', ['Bug Sistem Internal', 'Bug Sistem dari Asuransi'])
                ->pluck('name')
                ->all(),
        );
        $this->assertEqualsCanonicalizing(
            ['Sistem Internal', 'Sistem Asuransi atau Eksternal'],
            Application::query()->pluck('name')->all(),
        );
    }

    public function test_feature_flag_off_keeps_requester_creation_legacy_with_null_snapshot(): void
    {
        config(['crm.dynamic_workflow_enabled' => false]);

        $ticket = $this->createTicket();

        $this->assertNull($ticket->workflow_mode);
        $this->assertNull($ticket->workflow_id);
        $this->assertNull($ticket->workflow_version);
        $this->assertNull($ticket->workflow_snapshot);
        $this->assertNull($ticket->current_workflow_stage);
        $this->assertSame('pending_validation', $ticket->status->value);
        $this->assertSame(['created', 'submitted'], $ticket->histories()->pluck('action')->all());
    }

    public function test_feature_flag_on_attaches_active_default_snapshot_at_submitted_once(): void
    {
        $ticket = $this->createDynamicTicket();

        $this->assertSame('dynamic', $ticket->workflow_mode);
        $this->assertSame($this->workflow->id, $ticket->workflow_id);
        $this->assertSame(1, $ticket->workflow_version);
        $this->assertSame('crm_default', $ticket->workflow_snapshot['workflow_code']);
        $this->assertSame('submitted', $ticket->workflow_snapshot['initial_stage']);
        $this->assertSame('submitted', $ticket->current_workflow_stage);
        $this->assertSame('submitted', $ticket->status->value);
        $this->assertHistory($ticket, ['created']);
    }

    public function test_complete_default_flow_records_each_transition_once(): void
    {
        $ticket = $this->createDynamicTicket();

        $ticket = $this->transition($ticket, $this->supervisor, 'start_analysis');
        $this->assignments->assignPrimary($ticket, $this->supervisor, $this->supportPic);
        $ticket = $this->transition($ticket, $this->supervisor, 'assign');
        $ticket = $this->transition($ticket, $this->supportPic, 'start_work');
        $ticket = $this->transition($ticket, $this->supportPic, 'request_info', ['notes' => 'Need logs']);
        $ticket = $this->transition($ticket, $this->requester, 'requester_reply');
        $ticket = $this->transition($ticket, $this->supportPic, 'wait_external', ['notes' => 'Waiting vendor']);
        $ticket = $this->transition($ticket, $this->supportPic, 'resume_work');
        $ticket = $this->transition($ticket, $this->supportPic, 'submit_for_approval', ['result_summary' => 'Fixed']);
        $ticket = $this->transition($ticket, $this->supervisor, 'request_revision', ['notes' => 'Add tests']);
        $ticket = $this->transition($ticket, $this->supportPic, 'resume_revision');
        $ticket = $this->transition($ticket, $this->supportPic, 'submit_for_approval', ['result_summary' => 'Fixed and tested']);
        $ticket = $this->transition($ticket, $this->supervisor, 'approve');

        $this->assertSame('done', $ticket->current_workflow_stage);
        $this->assertNotNull($ticket->closed_at);
        $this->assertHistory($ticket, [
            'created', 'start_analysis', 'assign', 'start_work', 'request_info', 'requester_reply',
            'wait_external', 'resume_work', 'submit_for_approval', 'request_revision',
            'resume_revision', 'submit_for_approval', 'approve',
        ]);
        $this->assertSame(1, $ticket->assignmentHistories()->count());
    }

    public function test_reject_cancel_reopen_and_reassign_branches_record_no_duplicate_histories(): void
    {
        $rejected = $this->transition($this->createDynamicTicket(), $this->supervisor, 'reject', ['notes' => 'Invalid request']);
        $cancelled = $this->transition($this->createDynamicTicket(), $this->requester, 'cancel', ['notes' => 'No longer needed']);

        $done = $this->ticketAtDone($this->supportPic);
        $reopened = $this->transition($done, $this->supervisor, 'reopen', ['notes' => 'Issue recurred']);
        $this->assignments->reassign($reopened, $this->supervisor, $this->developPic, reason: 'Different skill set');
        $reassigned = $this->transition($reopened, $this->supervisor, 'reassign');

        $this->assertSame('rejected', $rejected->current_workflow_stage);
        $this->assertSame('cancelled', $cancelled->current_workflow_stage);
        $this->assertSame('assigned', $reassigned->current_workflow_stage);
        $this->assertSame($this->developPic->id, $reassigned->current_assignee_id);
        $this->assertHistory($rejected, ['created', 'reject']);
        $this->assertHistory($cancelled, ['created', 'cancel']);
        $this->assertSame(1, $reassigned->histories()->where('action', 'reopen')->count());
        $this->assertSame(1, $reassigned->histories()->where('action', 'reassign')->count());
        $this->assertSame(2, $reassigned->assignmentHistories()->count());
    }

    public function test_assignment_and_requester_ownership_rules_are_enforced(): void
    {
        $unassigned = $this->transition($this->createDynamicTicket(), $this->supervisor, 'start_analysis');
        $this->assertFalse($this->engine->canTransition($unassigned, $this->supportPic, 'assign'));

        $ticket = $this->createDynamicTicket();
        $ticket = $this->transition($ticket, $this->supervisor, 'start_analysis');
        $this->assignments->assignPrimary($ticket, $this->supervisor, $this->supportPic);
        $ticket = $this->transition($ticket, $this->supervisor, 'assign');
        $ticket = $this->transition($ticket, $this->supportPic, 'start_work');
        $ticket = $this->transition($ticket, $this->supportPic, 'request_info', ['notes' => 'Need details']);

        $this->assertFalse($this->engine->canTransition($ticket, $this->otherRequester, 'requester_reply'));
        $this->assertTrue($this->engine->canTransition($ticket, $this->requester, 'requester_reply'));
    }

    public function test_unassigned_pic_is_forbidden_and_secondary_pic_cannot_submit(): void
    {
        $unassignedPic = $this->user('pic_it_support', Division::query()->where('code', 'IT')->firstOrFail());
        $ticket = $this->createDynamicTicket();
        $ticket = $this->transition($ticket, $this->supervisor, 'start_analysis');
        $this->assignments->assignPrimary($ticket, $this->supervisor, $this->supportPic);
        $this->assignments->addSecondary($ticket, $this->supervisor, $this->developPic);
        $ticket = $this->transition($ticket, $this->supervisor, 'assign');

        $this->actingAs($unassignedPic)
            ->postJson("/api/v1/tickets/{$ticket->id}/workflow-transition", ['action_key' => 'start_work'])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'TRANSITION_FORBIDDEN');

        $ticket = $this->transition($ticket, $this->supportPic, 'start_work');
        $this->assertFalse($this->engine->canTransition($ticket, $this->developPic, 'submit_for_approval'));
        $this->actingAs($this->developPic)
            ->postJson("/api/v1/tickets/{$ticket->id}/workflow-transition", [
                'action_key' => 'submit_for_approval',
                'result_summary' => 'Secondary result',
            ])
            ->assertForbidden();
    }

    public function test_supervisor_primary_pic_can_work_submit_and_self_approval_is_audited(): void
    {
        $ticket = $this->createDynamicTicket();
        $ticket = $this->transition($ticket, $this->supervisor, 'start_analysis');
        $assignment = $this->assignments->assignPrimary($ticket, $this->supervisor, $this->supervisor);
        $ticket = $this->transition($ticket, $this->supervisor, 'assign');
        $ticket = $this->transition($ticket, $this->supervisor, 'start_work');
        $ticket = $this->transition($ticket, $this->supervisor, 'submit_for_approval', ['result_summary' => 'Supervisor fix']);

        $this->actingAs($this->supervisor)
            ->postJson("/api/v1/tickets/{$ticket->id}/workflow-transition", [
                'action_key' => 'approve',
                'notes' => '   ',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'TRANSITION_ERROR');

        $this->assertSame('pending_approval', $ticket->fresh()->current_workflow_stage);
        $ticket = $this->transition($ticket, $this->supervisor, 'approve', ['notes' => 'Reviewed my own implementation']);

        $this->assertTrue($assignment->acting_as_pic);
        $approval = $ticket->histories()->where('action', 'approve')->sole();
        $this->assertTrue($approval->metadata['self_approval']);
        $this->assertSame($this->supervisor->id, $approval->metadata['actor_id']);
        $this->assertSame('Reviewed my own implementation', $approval->metadata['payload']['notes']);
        $this->assertHistory($ticket, ['created', 'start_analysis', 'assign', 'start_work', 'submit_for_approval', 'approve']);
    }

    public function test_ticket_snapshot_v1_is_immutable_after_v2_is_activated_and_ticket_b_uses_v2(): void
    {
        $ticketA = $this->createDynamicTicket();
        $snapshotV1 = $ticketA->workflow_snapshot;

        $workflowV2 = $this->cloneAsVersionTwo($this->workflow);
        $workflowV2->stages()->where('stage_key', 'submitted')->update(['name' => 'Submitted V2']);
        $this->activate($workflowV2);
        $ticketB = $this->createDynamicTicket();

        $this->assertSame(1, $ticketA->fresh()->workflow_version);
        $this->assertSame($snapshotV1, $ticketA->fresh()->workflow_snapshot);
        $this->assertSame('Diajukan', collect($ticketA->workflow_snapshot['stages'])->firstWhere('stage_key', 'submitted')['name']);
        $this->assertSame(2, $ticketB->workflow_version);
        $this->assertSame('Submitted V2', collect($ticketB->workflow_snapshot['stages'])->firstWhere('stage_key', 'submitted')['name']);
    }

    public function test_fake_action_is_forbidden_and_stale_expected_stage_is_conflict_without_history(): void
    {
        $ticket = $this->createDynamicTicket();

        $this->actingAs($this->supervisor)
            ->postJson("/api/v1/tickets/{$ticket->id}/workflow-transition", ['action_key' => 'fake_action'])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'TRANSITION_FORBIDDEN');
        $this->actingAs($this->supervisor)
            ->postJson("/api/v1/tickets/{$ticket->id}/workflow-transition", [
                'action_key' => 'start_analysis',
                'expected_stage' => 'under_analysis',
            ])
            ->assertConflict()
            ->assertJsonPath('error.code', 'STAGE_CONFLICT');

        $this->assertSame('submitted', $ticket->fresh()->current_workflow_stage);
        $this->assertHistory($ticket, ['created']);
    }

    public function test_support_and_develop_pics_can_be_cross_assigned_across_both_categories_and_systems(): void
    {
        $categories = TicketCategory::query()
            ->whereIn('name', ['Bug Sistem Internal', 'Bug Sistem dari Asuransi'])
            ->orderBy('id')
            ->get();
        $systems = Application::query()->orderBy('id')->get();
        $pics = [$this->supportPic, $this->developPic];

        foreach ($categories as $index => $category) {
            $ticket = $this->createDynamicTicket();
            $ticket->update([
                'ticket_category_id' => $category->id,
                'application_id' => $systems[$index]->id,
            ]);
            $ticket = $this->transition($ticket, $this->supervisor, 'start_analysis');
            $this->assignments->assignPrimary($ticket, $this->supervisor, $pics[1 - $index]);
            $ticket = $this->transition($ticket, $this->supervisor, 'assign');
            $ticket = $this->transition($ticket, $pics[1 - $index], 'start_work');

            $this->assertSame('in_progress', $ticket->current_workflow_stage);
            $this->assertSame(1, $ticket->assignmentHistories()->count());
            $this->assertHistory($ticket, ['created', 'start_analysis', 'assign', 'start_work']);
        }
    }

    private function createTicket(): Ticket
    {
        return $this->requesterTickets->createTicket($this->requester, [
            'title' => 'Stage 8 integration ticket',
            'description' => 'Exercise the seeded default dynamic workflow.',
            'affected_url' => 'https://example.test/tickets/stage-8',
            'reference' => 'STAGE-8',
        ], []);
    }

    private function createDynamicTicket(): Ticket
    {
        config(['crm.dynamic_workflow_enabled' => true]);

        return $this->createTicket();
    }

    private function transition(Ticket $ticket, User $actor, string $action, array $payload = []): Ticket
    {
        $before = $ticket->histories()->where('action', $action)->count();
        $updated = $this->engine->executeTransition($ticket, $actor, $action, $payload, $ticket->current_workflow_stage);

        $this->assertSame($before + 1, $updated->histories()->where('action', $action)->count());

        return $updated;
    }

    private function ticketAtDone(User $primaryPic): Ticket
    {
        $ticket = $this->createDynamicTicket();
        $ticket = $this->transition($ticket, $this->supervisor, 'start_analysis');
        $this->assignments->assignPrimary($ticket, $this->supervisor, $primaryPic);
        $ticket = $this->transition($ticket, $this->supervisor, 'assign');
        $ticket = $this->transition($ticket, $primaryPic, 'start_work');
        $ticket = $this->transition($ticket, $primaryPic, 'submit_for_approval', ['result_summary' => 'Resolved']);

        return $this->transition($ticket, $this->supervisor, 'approve');
    }

    private function activate(WorkflowDefinition $workflow): void
    {
        WorkflowDefinition::query()->whereKeyNot($workflow->id)->update([
            'config_status' => 'inactive',
            'is_active' => false,
        ]);
        $workflow->update([
            'config_status' => 'active',
            'is_active' => true,
            'published_at' => now(),
        ]);
    }

    private function cloneAsVersionTwo(WorkflowDefinition $source): WorkflowDefinition
    {
        $source->load(['stages.fields', 'transitions.permissions', 'transitions.notifications', 'approvalConfigs.steps']);
        $copy = $source->replicate();
        $copy->version = 2;
        $copy->config_status = 'published';
        $copy->is_active = false;
        $copy->published_at = now()->addSecond();
        $copy->save();

        $stageIds = [];
        foreach ($source->stages as $stage) {
            $newStage = $copy->stages()->create($stage->only([
                'stage_key', 'name', 'description', 'order', 'stage_type', 'is_initial', 'is_terminal',
            ]));
            $stageIds[$stage->id] = $newStage->id;
            foreach ($stage->fields as $field) {
                $newStage->fields()->create($field->only(['field_key', 'label', 'field_type', 'is_required', 'validation_rules', 'options', 'order']));
            }
        }
        foreach ($source->transitions as $transition) {
            $newTransition = $copy->transitions()->create([
                'from_stage_id' => $stageIds[$transition->from_stage_id],
                'to_stage_id' => $stageIds[$transition->to_stage_id],
                'action_key' => $transition->action_key,
                'name' => $transition->name,
                'requires_notes' => $transition->requires_notes,
            ]);
            foreach ($transition->permissions as $permission) {
                $newTransition->permissions()->create($permission->only(['role_key', 'permission_code']));
            }
            foreach ($transition->notifications as $notification) {
                $newTransition->notifications()->create($notification->only(['recipient_type', 'channel', 'template_code']));
            }
        }

        return $copy;
    }

    private function user(string $roleKey, Division $division): User
    {
        return User::factory()->create([
            'role_id' => Role::query()->where('key', $roleKey)->firstOrFail()->id,
            'division_id' => $division->id,
            'is_active' => true,
        ]);
    }

    private function assertHistory(Ticket $ticket, array $actions): void
    {
        $this->assertSame($actions, $ticket->histories()->pluck('action')->all());
        $this->assertSame(count($actions), $ticket->histories()->count());
    }
}
