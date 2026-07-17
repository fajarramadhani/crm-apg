<?php

namespace Tests\Feature;

use App\Enums\TicketStatus;
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
use Tests\TestCase;

class QaDefectRetestTest extends TestCase
{
    use RefreshDatabase;

    private User $requester;

    private User $pic;

    private User $itLead;

    private User $qa1;

    private User $qa2;

    private Division $division;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, MasterDataSeeder::class]);
        $this->division = Division::where('code', 'IT')->firstOrFail();
        $this->requester = $this->user('requester');
        $this->pic = $this->user('pic');
        $this->itLead = $this->user('it_lead');
        $this->qa1 = $this->user('qa');
        $this->qa2 = $this->user('qa');
    }

    public function test_qa_assignment_and_duplicate_prevention(): void
    {
        $ticket = $this->readyForQaTicket();

        // 1. Assign QA successfully
        $this->actingAs($this->itLead)
            ->postJson("/api/v1/it-lead/tickets/{$ticket->id}/assign-qa", [
                'qa_user_id' => $this->qa1->id,
                'notes' => 'Assign to QA 1',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'qa_assignment')
            ->assertJsonPath('data.qa_assignee.id', $this->qa1->id);

        // 2. Duplicate assignment throws HTTP 409 Conflict
        $this->actingAs($this->itLead)
            ->postJson("/api/v1/it-lead/tickets/{$ticket->id}/assign-qa", [
                'qa_user_id' => $this->qa2->id,
            ])
            ->assertStatus(409);
    }

    public function test_assigned_qa_can_only_execute_qa_actions(): void
    {
        $ticket = $this->readyForQaTicket();

        // IT Lead assigns QA 1
        $this->actingAs($this->itLead)->postJson("/api/v1/it-lead/tickets/{$ticket->id}/assign-qa", ['qa_user_id' => $this->qa1->id])->assertOk();

        // Other QA cannot start testing
        $this->actingAs($this->qa2)->postJson("/api/v1/qa/tickets/{$ticket->id}/start")->assertForbidden();

        // Assigned QA starts testing
        $this->actingAs($this->qa1)->postJson("/api/v1/qa/tickets/{$ticket->id}/start")
            ->assertOk()
            ->assertJsonPath('data.status', 'qa_in_progress')
            ->assertJsonPath('data.qa_cycle_number', 1);
    }

    public function test_failed_test_case_result_without_defect_is_rejected(): void
    {
        $ticket = $this->readyForQaTicket();
        $this->actingAs($this->itLead)->postJson("/api/v1/it-lead/tickets/{$ticket->id}/assign-qa", ['qa_user_id' => $this->qa1->id])->assertOk();
        $this->actingAs($this->qa1)->postJson("/api/v1/qa/tickets/{$ticket->id}/start")->assertOk();

        // Create a test case
        $caseRes = $this->actingAs($this->qa1)->postJson("/api/v1/qa/tickets/{$ticket->id}/test-cases", [
            'case_number' => 'TC-01',
            'title' => 'Sample Case',
            'test_type' => 'functional',
            'steps' => ['Step 1', 'Step 2'],
            'expected_result' => 'Pass',
            'priority' => 'high',
        ])->assertOk();
        $caseId = $caseRes->json('data.id');

        // Start test run
        $runRes = $this->actingAs($this->qa1)->postJson("/api/v1/qa/tickets/{$ticket->id}/test-runs", [
            'environment' => 'staging',
        ])->assertOk();
        $runId = $runRes->json('data.id');

        // Record failed result
        $this->actingAs($this->qa1)->postJson("/api/v1/qa/tickets/{$ticket->id}/test-runs/{$runId}/results", [
            'qa_test_case_id' => $caseId,
            'status' => 'failed',
            'actual_result' => 'Failed at step 2',
        ])->assertOk();

        // Complete run without defect reported must fail with HTTP 409
        $this->actingAs($this->qa1)->postJson("/api/v1/qa/tickets/{$ticket->id}/test-runs/{$runId}/complete")
            ->assertStatus(409);
    }

    public function test_defect_creation_rework_preconditions_and_cycle_increment(): void
    {
        $ticket = $this->readyForQaTicket();
        $this->actingAs($this->itLead)->postJson("/api/v1/it-lead/tickets/{$ticket->id}/assign-qa", ['qa_user_id' => $this->qa1->id])->assertOk();
        $this->actingAs($this->qa1)->postJson("/api/v1/qa/tickets/{$ticket->id}/start")->assertOk();

        // Create test case
        $caseId = $this->actingAs($this->qa1)->postJson("/api/v1/qa/tickets/{$ticket->id}/test-cases", [
            'case_number' => 'TC-01',
            'title' => 'Sample Case',
            'test_type' => 'functional',
            'steps' => ['Step 1'],
            'expected_result' => 'Pass',
            'priority' => 'high',
        ])->assertOk()->json('data.id');

        // Start run 1
        $runId = $this->actingAs($this->qa1)->postJson("/api/v1/qa/tickets/{$ticket->id}/test-runs", ['environment' => 'staging'])->assertOk()->json('data.id');

        // Record failed result
        $this->actingAs($this->qa1)->postJson("/api/v1/qa/tickets/{$ticket->id}/test-runs/{$runId}/results", [
            'qa_test_case_id' => $caseId,
            'status' => 'failed',
            'actual_result' => 'Failed',
        ])->assertOk();

        // Report a defect
        $defectRes = $this->actingAs($this->qa1)->postJson("/api/v1/qa/tickets/{$ticket->id}/defects", [
            'qa_test_run_id' => $runId,
            'qa_test_case_id' => $caseId,
            'title' => 'Broken Page',
            'description' => 'Detailed description of the bug.',
            'severity' => 'critical',
            'priority' => 'urgent',
            'steps_to_reproduce' => 'Click button',
            'expected_result' => 'Should load',
            'actual_result' => 'Blank page',
        ])->assertOk();
        $defectId = $defectRes->json('data.id');

        // Verify defect history is recorded
        $this->assertDatabaseHas('ticket_qa_defect_histories', [
            'defect_id' => $defectId,
            'to_status' => 'open',
            'action' => 'created',
            'actor_id' => $this->qa1->id,
        ]);

        // Complete run (fails QA)
        $this->actingAs($this->qa1)->postJson("/api/v1/qa/tickets/{$ticket->id}/test-runs/{$runId}/complete")->assertOk();

        // Ticket transitions: qa_in_progress -> qa_failed -> development_in_progress
        $ticket = $ticket->fresh();
        $this->assertEquals(TicketStatus::DevelopmentInProgress, $ticket->status);
        $this->assertLessThanOrEqual(90, $ticket->progress_percentage);

        // PIC starts rework on defect
        $this->actingAs($this->pic)->postJson("/api/v1/pic/tickets/{$ticket->id}/qa-defects/{$defectId}/start")->assertOk();

        // PIC resolves defect
        $this->actingAs($this->pic)->postJson("/api/v1/pic/tickets/{$ticket->id}/qa-defects/{$defectId}/resolve", [
            'resolution_notes' => 'Fixed a null pointer exception.',
        ])->assertOk();

        $this->actingAs($this->pic)->postJson("/api/v1/pic/tickets/{$ticket->id}/development-updates", [
            'progress_percentage' => 100,
            'expected_progress' => 90,
            'summary' => 'Fixed bug, progress is 100%',
        ])->assertCreated();

        // Verify retest submission preconditions:
        $this->actingAs($this->pic)->postJson("/api/v1/pic/tickets/{$ticket->id}/submit-qa-retest")->assertStatus(409);

        // PIC adds rework worklog
        $this->actingAs($this->pic)->postJson("/api/v1/pic/tickets/{$ticket->id}/worklogs", [
            'work_date' => now()->toDateString(),
            'minutes_spent' => 60,
            'activity_type' => 'rework',
            'description' => 'Reworking the code for the defect.',
            'progress_after' => 100,
            'expected_progress' => 100,
        ])->assertCreated();

        // 2. PIC cannot submit retest without passed internal test run since failure
        $this->actingAs($this->pic)->postJson("/api/v1/pic/tickets/{$ticket->id}/submit-qa-retest")->assertStatus(409);

        // PIC runs internal test case and run
        $internalCaseId = $ticket->internalTestCases()->create([
            'created_by' => $this->pic->id,
            'case_number' => 'ITC-01',
            'title' => 'Internal Smoke Test',
            'steps' => ['Step 1'],
            'expected_result' => 'Passed',
            'is_active' => true,
        ])->id;
        $internalRunId = $this->actingAs($this->pic)->postJson("/api/v1/pic/tickets/{$ticket->id}/internal-test-runs", [
            'environment' => 'staging',
            'build_reference' => 'b2',
        ])->json('data.id');
        $this->actingAs($this->pic)->postJson("/api/v1/pic/tickets/{$ticket->id}/internal-test-runs/{$internalRunId}/results", [
            'test_case_id' => $internalCaseId,
            'status' => 'passed',
            'actual_result' => 'Passed',
        ])->assertCreated();
        $this->actingAs($this->pic)->postJson("/api/v1/pic/tickets/{$ticket->id}/internal-test-runs/{$internalRunId}/complete")->assertOk();

        // Submit retest now passes
        $this->actingAs($this->pic)->postJson("/api/v1/pic/tickets/{$ticket->id}/submit-qa-retest")->assertOk();
        $ticket = $ticket->fresh();
        $this->assertEquals(TicketStatus::QaRetest, $ticket->status);
        $this->assertEquals(100, $ticket->progress_percentage);

        // QA starts testing (cycle 2)
        $this->actingAs($this->qa1)->postJson("/api/v1/qa/tickets/{$ticket->id}/start")
            ->assertOk()
            ->assertJsonPath('data.status', 'qa_in_progress')
            ->assertJsonPath('data.qa_cycle_number', 2);
    }

    public function test_nesting_resource_validation(): void
    {
        $ticketA = $this->readyForQaTicket();
        $ticketB = $this->readyForQaTicket();

        $this->actingAs($this->itLead)->postJson("/api/v1/it-lead/tickets/{$ticketA->id}/assign-qa", ['qa_user_id' => $this->qa1->id])->assertOk();
        $this->actingAs($this->qa1)->postJson("/api/v1/qa/tickets/{$ticketA->id}/start")->assertOk();

        $this->actingAs($this->itLead)->postJson("/api/v1/it-lead/tickets/{$ticketB->id}/assign-qa", ['qa_user_id' => $this->qa1->id])->assertOk();
        $this->actingAs($this->qa1)->postJson("/api/v1/qa/tickets/{$ticketB->id}/start")->assertOk();

        // Create test case on Ticket A
        $caseId = $this->actingAs($this->qa1)->postJson("/api/v1/qa/tickets/{$ticketA->id}/test-cases", [
            'case_number' => 'TC-A1',
            'title' => 'Case A',
            'test_type' => 'functional',
            'steps' => ['Step 1'],
            'expected_result' => 'Pass',
            'priority' => 'high',
        ])->assertOk()->json('data.id');

        // Requesting test case using ticket B in url must return HTTP 404/403
        $this->actingAs($this->qa1)
            ->putJson("/api/v1/qa/tickets/{$ticketB->id}/test-cases/{$caseId}", [
                'case_number' => 'TC-A1',
                'title' => 'Update Case',
                'test_type' => 'functional',
                'steps' => ['Step 1'],
                'expected_result' => 'Pass',
                'priority' => 'high',
            ])
            ->assertStatus(404);
    }

    public function test_requester_executive_redaction(): void
    {
        $ticket = $this->readyForQaTicket();
        $this->actingAs($this->itLead)->postJson("/api/v1/it-lead/tickets/{$ticket->id}/assign-qa", ['qa_user_id' => $this->qa1->id])->assertOk();
        $this->actingAs($this->qa1)->postJson("/api/v1/qa/tickets/{$ticket->id}/start")->assertOk();

        $this->actingAs($this->qa1)->postJson("/api/v1/qa/tickets/{$ticket->id}/test-cases", [
            'case_number' => 'TC-01',
            'title' => 'Sample Case',
            'test_type' => 'functional',
            'steps' => ['Step 1'],
            'expected_result' => 'Pass',
            'priority' => 'high',
        ])->assertOk();

        $runId = $this->actingAs($this->qa1)->postJson("/api/v1/qa/tickets/{$ticket->id}/test-runs", ['environment' => 'staging'])->json('data.id');

        // Create defect
        $defectRes = $this->actingAs($this->qa1)->postJson("/api/v1/qa/tickets/{$ticket->id}/defects", [
            'qa_test_run_id' => $runId,
            'title' => 'Technical Error',
            'description' => 'Details.',
            'severity' => 'major',
            'priority' => 'medium',
            'steps_to_reproduce' => 'Steps',
            'expected_result' => 'Expect',
            'actual_result' => 'Actual',
        ])->assertOk();
        $defectId = $defectRes->json('data.id');

        // Create comments and attachments linked to the defect
        $comment = $ticket->comments()->create([
            'user_id' => $this->qa1->id,
            'type' => 'text',
            'comment' => 'This is a defect-related internal note.',
            'is_internal' => true,
            'defect_id' => $defectId,
        ]);

        $attachment = $ticket->attachments()->create([
            'uploaded_by' => $this->qa1->id,
            'original_name' => 'error.png',
            'stored_name' => 'err',
            'disk' => 'local',
            'path' => 'tickets/error.png',
            'mime_type' => 'image/png',
            'size' => 100,
            'category' => 'defect_evidence',
            'visibility' => 'internal',
            'defect_id' => $defectId,
        ]);

        // Load ticket as requester
        $res = $this->actingAs($this->requester)->getJson("/api/v1/tickets/{$ticket->id}")->assertOk();

        // Assert comments/attachments linked to defects are redacted
        $res->assertJsonCount(0, 'data.comments');
        $res->assertJsonCount(0, 'data.attachments');
    }

    public function test_evidence_upload_authorization_and_nested_validation(): void
    {
        $ticket = $this->readyForQaTicket();
        $freshFile = fn () => UploadedFile::fake()->create('test.txt', 100, 'text/plain');

        // 1. Try uploading QA evidence when QA has not been assigned yet (ticket is ready_for_qa, so not in_progress)
        $this->actingAs($this->qa1)
            ->postJson("/api/v1/qa/tickets/{$ticket->id}/evidence", [
                'file' => $freshFile(),
                'category' => 'qa_evidence',
            ])
            ->assertStatus(403); // Forbidden because not the assigned QA

        // Assign QA
        $this->actingAs($this->itLead)
            ->postJson("/api/v1/it-lead/tickets/{$ticket->id}/assign-qa", [
                'qa_user_id' => $this->qa1->id,
            ])
            ->assertOk();

        // Still status is qa_assignment (not qa_in_progress), so try to upload -> should reject with 409
        $this->actingAs($this->qa1)
            ->postJson("/api/v1/qa/tickets/{$ticket->id}/evidence", [
                'file' => $freshFile(),
                'category' => 'qa_evidence',
            ])
            ->assertStatus(409);

        // Start QA (transitions to qa_in_progress)
        $this->actingAs($this->qa1)->postJson("/api/v1/qa/tickets/{$ticket->id}/start")->assertOk();

        // Upload QA evidence successfully
        $this->actingAs($this->qa1)
            ->postJson("/api/v1/qa/tickets/{$ticket->id}/evidence", [
                'file' => $freshFile(),
                'category' => 'qa_evidence',
            ])
            ->assertStatus(201);

        $otherTicket = $this->readyForQaTicket();
        $this->actingAs($this->itLead)
            ->postJson("/api/v1/it-lead/tickets/{$otherTicket->id}/assign-qa", [
                'qa_user_id' => $this->qa1->id,
            ])
            ->assertOk();
        $this->actingAs($this->qa1)->postJson("/api/v1/qa/tickets/{$otherTicket->id}/start")->assertOk();

        // Create a test case on otherTicket
        $this->actingAs($this->qa1)
            ->postJson("/api/v1/qa/tickets/{$otherTicket->id}/test-cases", [
                'case_number' => 'TC-999',
                'title' => 'Other Scenario',
                'test_type' => 'functional',
                'steps' => ['Step 1'],
                'expected_result' => 'Work',
                'priority' => 'medium',
            ])
            ->assertOk();

        $otherRunRes = $this->actingAs($this->qa1)->postJson("/api/v1/qa/tickets/{$otherTicket->id}/test-runs", ['environment' => 'staging'])->assertOk();
        $otherRunId = $otherRunRes->json('data.id');

        $defectRes = $this->actingAs($this->qa1)
            ->postJson("/api/v1/qa/tickets/{$otherTicket->id}/defects", [
                'qa_test_run_id' => $otherRunId,
                'title' => 'Other defect',
                'description' => 'Desc',
                'defect_number' => 'DEF-999',
                'severity' => 'major',
                'priority' => 'medium',
                'steps_to_reproduce' => 'Click X',
                'expected_result' => 'Should work',
                'actual_result' => 'Error',
            ])
            ->assertStatus(200);
        $otherDefectId = $defectRes->json('data.id');

        // Try uploading QA evidence on the original ticket using the other ticket's defect_id -> must fail validation (422)
        $this->actingAs($this->qa1)
            ->postJson("/api/v1/qa/tickets/{$ticket->id}/evidence", [
                'file' => $freshFile(),
                'category' => 'qa_evidence',
                'defect_id' => $otherDefectId,
            ])
            ->assertStatus(422);

        // 2. PIC evidence testing
        // Try uploading PIC evidence when status is qa_in_progress -> must yield 409
        $this->actingAs($this->pic)
            ->postJson("/api/v1/pic/tickets/{$ticket->id}/development-evidence", [
                'file' => $freshFile(),
                'category' => 'development_evidence',
            ])
            ->assertStatus(409);

        // Try uploading as an unrelated PIC -> must yield 403
        $unrelatedPic = $this->user('pic');
        $this->actingAs($unrelatedPic)
            ->postJson("/api/v1/pic/tickets/{$ticket->id}/development-evidence", [
                'file' => $freshFile(),
                'category' => 'development_evidence',
            ])
            ->assertStatus(403);
    }

    private function readyForQaTicket(): Ticket
    {
        static $number = 0;
        $ticket = Ticket::create([
            'ticket_number' => 'TIC-202607-QA'.str_pad((string) ++$number, 4, '0', STR_PAD_LEFT),
            'requester_id' => $this->requester->id,
            'division_id' => $this->division->id,
            'current_division_id' => $this->division->id,
            'ticket_category_id' => TicketCategory::firstOrFail()->id,
            'requested_priority_id' => TicketPriority::where('key', 'medium')->firstOrFail()->id,
            'final_priority_id' => TicketPriority::where('key', 'medium')->firstOrFail()->id,
            'title' => 'QA test ticket',
            'description' => 'Description',
            'status' => TicketStatus::ReadyForQa,
            'submitted_at' => now(),
            'validated_at' => now(),
            'assigned_at' => now(),
            'analysis_started_at' => now(),
            'analysis_completed_at' => now(),
            'plan_submitted_at' => now(),
            'plan_approved_at' => now(),
            'development_started_at' => now(),
            'development_completed_at' => now(),
            'internal_testing_started_at' => now(),
            'internal_testing_completed_at' => now(),
            'ready_for_qa_at' => now(),
            'current_assignee_id' => $this->pic->id,
            'assigned_by' => $this->itLead->id,
            'progress_percentage' => 100,
        ]);
        $ticket->assignments()->create([
            'assigned_to' => $this->pic->id,
            'assigned_by' => $this->itLead->id,
            'assignment_type' => 'primary',
            'started_at' => now(),
            'is_current' => true,
        ]);

        return $ticket->fresh();
    }

    private function user(string $role): User
    {
        return User::factory()->create([
            'role_id' => Role::where('key', $role)->firstOrFail()->id,
            'division_id' => $this->division->id,
            'is_active' => true,
        ]);
    }
}
