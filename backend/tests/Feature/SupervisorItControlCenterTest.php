<?php

namespace Tests\Feature;

use App\Enums\TicketStatus;
use App\Models\Application;
use App\Models\Branch;
use App\Models\Division;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketPriority;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class SupervisorItControlCenterTest extends TestCase
{
    use RefreshDatabase;

    private User $supervisorIt;

    private User $picSupport;

    private User $picDevelop;

    private User $requester;

    private Division $division;

    private Branch $branch;

    private Application $appModel;

    private TicketCategory $category;

    private TicketPriority $priority;

    protected function setUp(): void
    {
        parent::setUp();

        $supervisorRole = Role::query()->firstOrCreate(['key' => 'supervisor_it'], ['name' => 'Supervisor IT', 'is_active' => true]);
        $picSupportRole = Role::query()->firstOrCreate(['key' => 'pic_it_support'], ['name' => 'PIC IT Support', 'is_active' => true]);
        $picDevelopRole = Role::query()->firstOrCreate(['key' => 'pic_it_develop'], ['name' => 'PIC IT Develop', 'is_active' => true]);
        $requesterRole = Role::query()->firstOrCreate(['key' => 'requester'], ['name' => 'Requester', 'is_active' => true]);

        $this->division = Division::query()->create(['code' => 'IT01', 'name' => 'Divisi IT', 'is_active' => true]);
        $this->branch = Branch::query()->create(['code' => 'PST', 'name' => 'Kantor Pusat', 'is_active' => true]);

        $this->supervisorIt = User::factory()->create([
            'role_id' => $supervisorRole->id,
            'division_id' => $this->division->id,
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);

        $this->picSupport = User::factory()->create([
            'role_id' => $picSupportRole->id,
            'division_id' => $this->division->id,
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);

        $this->picDevelop = User::factory()->create([
            'role_id' => $picDevelopRole->id,
            'division_id' => $this->division->id,
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);

        $this->requester = User::factory()->create([
            'role_id' => $requesterRole->id,
            'division_id' => $this->division->id,
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);

        $this->appModel = Application::query()->create(['code' => 'CRM', 'name' => 'CRM Application', 'is_active' => true]);
        $this->category = TicketCategory::query()->create(['code' => 'BUG', 'name' => 'Bug Sistem Internal', 'type' => 'incident', 'is_active' => true]);
        $this->priority = TicketPriority::query()->create(['key' => 'high', 'name' => 'Tinggi', 'level' => 2, 'is_active' => true]);
    }

    public function test_supervisor_it_can_view_dashboard(): void
    {
        Ticket::factory()->create([
            'requester_id' => $this->requester->id,
            'division_id' => $this->division->id,
            'status' => TicketStatus::PendingValidation,
        ]);

        Ticket::factory()->create([
            'requester_id' => $this->requester->id,
            'division_id' => $this->division->id,
            'status' => TicketStatus::UnderAnalysis,
        ]);

        $response = $this->actingAs($this->supervisorIt)
            ->getJson('/api/v1/supervisor-it/dashboard');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'stats' => [
                        'new_tickets',
                        'under_analysis',
                        'unassigned',
                        'in_progress',
                        'waiting_info',
                        'waiting_external',
                        'pending_final_review',
                        'overdue',
                        'completed_today',
                    ],
                    'action_required_tickets',
                    'high_priority_tickets',
                    'unassigned_tickets',
                    'overdue_tickets',
                    'pending_approval_tickets',
                ],
            ]);
    }

    public function test_supervisor_it_can_view_all_tickets_across_divisions(): void
    {
        $otherDiv = Division::query()->create(['code' => 'HR01', 'name' => 'HR Division', 'is_active' => true]);

        $t1 = Ticket::factory()->create([
            'requester_id' => $this->requester->id,
            'division_id' => $this->division->id,
            'current_division_id' => $this->division->id,
            'status' => TicketStatus::PendingValidation,
        ]);

        $t2 = Ticket::factory()->create([
            'requester_id' => $this->requester->id,
            'division_id' => $otherDiv->id,
            'current_division_id' => $otherDiv->id,
            'status' => TicketStatus::UnderAnalysis,
        ]);

        $response = $this->actingAs($this->supervisorIt)
            ->getJson('/api/v1/supervisor-it/tickets');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_summary_list_preloads_counts_and_omits_workflow_snapshot(): void
    {
        Ticket::factory()->count(2)->create([
            'requester_id' => $this->requester->id,
            'division_id' => $this->division->id,
            'current_division_id' => $this->division->id,
            'workflow_snapshot' => ['large' => str_repeat('x', 1000)],
        ]);

        DB::enableQueryLog();
        $this->actingAs($this->supervisorIt)->getJson('/api/v1/supervisor-it/tickets')->assertOk();
        $queries = collect(DB::getQueryLog())->pluck('query');

        $ticketQuery = $queries->first(fn (string $query): bool => str_contains($query, 'from "tickets"') && str_contains($query, 'defect_count'));
        $this->assertNotNull($ticketQuery);
        $this->assertStringNotContainsString('workflow_snapshot', $ticketQuery);
        $this->assertFalse($queries->contains(fn (string $query): bool => str_contains($query, 'count(*) as aggregate') && (str_contains($query, 'ticket_qa_defects') || str_contains($query, 'ticket_uat_findings'))));
    }

    public function test_assignment_candidates_are_aggregated_searchable_and_paginated(): void
    {
        $this->picSupport->update(['name' => 'Alpha Candidate']);
        $this->picDevelop->update(['name' => 'Beta Candidate']);
        Ticket::factory()->create([
            'requester_id' => $this->requester->id,
            'division_id' => $this->division->id,
            'current_division_id' => $this->division->id,
            'current_assignee_id' => $this->picSupport->id,
            'status' => TicketStatus::Assigned,
        ]);

        $response = $this->actingAs($this->supervisorIt)
            ->getJson('/api/v1/supervisor-it/assignees?search=Alpha&per_page=1');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->picSupport->id)
            ->assertJsonPath('data.0.active_ticket_count', 1)
            ->assertJsonPath('meta.pagination.per_page', 1)
            ->assertJsonPath('meta.pagination.total', 1);

        $this->actingAs($this->supervisorIt)
            ->getJson('/api/v1/supervisor-it/assignees?per_page=101')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('per_page');
    }

    public function test_audit_timeline_is_capped_and_reports_more_items(): void
    {
        $ticket = Ticket::factory()->create([
            'requester_id' => $this->requester->id,
            'division_id' => $this->division->id,
            'current_division_id' => $this->division->id,
        ]);
        foreach (range(1, 101) as $index) {
            $ticket->histories()->create([
                'from_status' => null,
                'to_status' => TicketStatus::PendingValidation->value,
                'action' => 'audit_'.$index,
                'actor_id' => $this->supervisorIt->id,
                'actor_role' => 'supervisor_it',
            ]);
        }

        $this->actingAs($this->supervisorIt)
            ->getJson("/api/v1/supervisor-it/tickets/{$ticket->id}")
            ->assertOk()
            ->assertJsonCount(100, 'data.audit_timeline')
            ->assertJsonPath('data.audit_timeline_meta.limit', 100)
            ->assertJsonPath('data.audit_timeline_meta.has_more', true);
    }

    public function test_supervisor_it_can_save_analysis_without_changing_requester_classification_or_assignment(): void
    {
        $requesterTarget = now()->addDays(10)->startOfDay();
        $ticket = Ticket::factory()->create([
            'requester_id' => $this->requester->id,
            'division_id' => $this->division->id,
            'application_id' => $this->appModel->id,
            'ticket_category_id' => $this->category->id,
            'final_priority_id' => $this->priority->id,
            'request_category' => 'error_bug',
            'urgency' => 'high',
            'target_needed_at' => $requesterTarget,
            'status' => TicketStatus::PendingValidation,
        ]);

        $payload = [
            'target_completion_date' => now()->addDays(3)->toDateString(),
            'analysis_summary' => 'Masalah teridentifikasi pada integrasi internal.',
            'handling_note' => 'Periksa log integrasi sebelum melakukan perbaikan.',
        ];

        $response = $this->actingAs($this->supervisorIt)
            ->postJson("/api/v1/supervisor-it/tickets/{$ticket->id}/analyze", $payload);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $ticket->refresh();
        $this->assertEquals($this->appModel->id, $ticket->application_id);
        $this->assertEquals($this->category->id, $ticket->ticket_category_id);
        $this->assertEquals($this->priority->id, $ticket->final_priority_id);
        $this->assertEquals('error_bug', $ticket->request_category);
        $this->assertEquals('high', $ticket->urgency);
        $this->assertTrue($ticket->target_needed_at->equalTo($requesterTarget));
        $this->assertNull($ticket->current_assignee_id);
        $this->assertEquals(TicketStatus::UnderAnalysis, $ticket->status);
        $this->assertNotNull($ticket->analysis_started_at);

        $this->assertDatabaseHas('ticket_comments', [
            'ticket_id' => $ticket->id,
            'type' => 'analysis_summary',
            'comment' => 'Masalah teridentifikasi pada integrasi internal.',
        ]);

        $this->assertDatabaseHas('ticket_comments', [
            'ticket_id' => $ticket->id,
            'type' => 'handling_note',
            'comment' => 'Periksa log integrasi sebelum melakukan perbaikan.',
        ]);
    }

    public function test_analysis_rejects_requester_classification_and_assignment_fields(): void
    {
        $ticket = Ticket::factory()->create([
            'requester_id' => $this->requester->id,
            'division_id' => $this->division->id,
            'status' => TicketStatus::PendingValidation,
        ]);

        $this->actingAs($this->supervisorIt)
            ->postJson("/api/v1/supervisor-it/tickets/{$ticket->id}/analyze", [
                'analysis_summary' => 'Analisis yang valid.',
                'application_id' => $this->appModel->id,
                'application_module_id' => 1,
                'ticket_category_id' => $this->category->id,
                'urgency' => 'low',
                'priority_id' => $this->priority->id,
                'assign_primary_user_id' => $this->picSupport->id,
                'secondary_user_ids' => [$this->picDevelop->id],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'application_id',
                'application_module_id',
                'ticket_category_id',
                'urgency',
                'priority_id',
                'assign_primary_user_id',
                'secondary_user_ids',
            ]);

        $this->assertDatabaseCount('ticket_assignments', 0);
    }

    public function test_supervisor_can_assign_self_as_pic(): void
    {
        $ticket = Ticket::factory()->create([
            'requester_id' => $this->requester->id,
            'division_id' => $this->division->id,
            'status' => TicketStatus::PendingValidation,
        ]);

        $payload = [
            'user_id' => $this->supervisorIt->id,
            'notes' => 'Tangani sendiri oleh supervisor',
            'reason' => 'Kebutuhan mendesak',
        ];

        $response = $this->actingAs($this->supervisorIt)
            ->postJson("/api/v1/supervisor-it/tickets/{$ticket->id}/assign-primary", $payload);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $ticket->refresh();
        $this->assertEquals($this->supervisorIt->id, $ticket->current_assignee_id);

        $this->assertDatabaseHas('ticket_assignments', [
            'ticket_id' => $ticket->id,
            'assigned_to' => $this->supervisorIt->id,
            'assignment_type' => 'primary',
            'role_at_assignment' => 'supervisor_it',
            'is_current' => true,
        ]);
    }

    public function test_supervisor_it_can_request_info_from_requester(): void
    {
        $ticket = Ticket::factory()->create([
            'requester_id' => $this->requester->id,
            'division_id' => $this->division->id,
            'status' => TicketStatus::UnderAnalysis,
        ]);

        $response = $this->actingAs($this->supervisorIt)
            ->postJson("/api/v1/supervisor-it/tickets/{$ticket->id}/request-info", [
                'notes' => 'Mohon sertakan screenshot tambahan',
            ]);

        $response->assertStatus(200);

        $ticket->refresh();
        $this->assertEquals(TicketStatus::NeedInfo, $ticket->status);
        $this->assertDatabaseHas('ticket_comments', [
            'ticket_id' => $ticket->id,
            'comment' => 'Mohon sertakan screenshot tambahan',
            'is_internal' => false,
        ]);
    }

    public function test_supervisor_it_can_approve_ticket(): void
    {
        $ticket = Ticket::factory()->create([
            'requester_id' => $this->requester->id,
            'division_id' => $this->division->id,
            'status' => TicketStatus::PendingApproval,
        ]);

        $response = $this->actingAs($this->supervisorIt)
            ->postJson("/api/v1/supervisor-it/tickets/{$ticket->id}/approve", [
                'summary_for_requester' => 'Tiket telah selesai ditangani dengan baik.',
            ]);

        $response->assertStatus(200);

        $ticket->refresh();
        $this->assertEquals(TicketStatus::Done, $ticket->status);
        $this->assertFalse($ticket->histories()->where('action', 'approved')->sole()->metadata['self_approval']);
    }

    public function test_supervisor_it_self_approval_requires_notes_and_is_audited(): void
    {
        $ticket = Ticket::factory()->create([
            'requester_id' => $this->requester->id,
            'division_id' => $this->division->id,
            'current_assignee_id' => $this->supervisorIt->id,
            'workflow_mode' => 'simplified',
            'status' => TicketStatus::PendingApproval,
        ]);
        $ticket->assignments()->create([
            'assigned_to' => $this->supervisorIt->id,
            'assigned_by' => $this->supervisorIt->id,
            'assignment_type' => 'primary',
            'role_at_assignment' => 'supervisor_it',
            'acting_as_pic' => true,
            'started_at' => now(),
            'is_current' => true,
        ]);

        $this->actingAs($this->supervisorIt)
            ->postJson("/api/v1/supervisor-it/tickets/{$ticket->id}/approve", ['notes' => '   '])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('notes');

        $this->assertEquals(TicketStatus::PendingApproval, $ticket->fresh()->status);

        $this->actingAs($this->supervisorIt)
            ->postJson("/api/v1/supervisor-it/tickets/{$ticket->id}/approve", ['notes' => 'Independent review completed'])
            ->assertOk();

        $approval = $ticket->histories()->where('action', 'approved')->sole();
        $this->assertSame('Independent review completed', $approval->notes);
        $this->assertTrue($approval->metadata['self_approval']);
    }

    public function test_supervisor_it_can_reject_ticket(): void
    {
        $ticket = Ticket::factory()->create([
            'requester_id' => $this->requester->id,
            'division_id' => $this->division->id,
            'status' => TicketStatus::UnderAnalysis,
        ]);

        $response = $this->actingAs($this->supervisorIt)
            ->postJson("/api/v1/supervisor-it/tickets/{$ticket->id}/reject", [
                'reason' => 'Bukan merupakan kendala sistem IT',
                'summary_for_requester' => 'Permintaan ditolak karena di luar cakupan IT.',
            ]);

        $response->assertStatus(200);

        $ticket->refresh();
        $this->assertEquals(TicketStatus::Rejected, $ticket->status);
    }

    public function test_supervisor_it_can_reopen_ticket(): void
    {
        $ticket = Ticket::factory()->create([
            'requester_id' => $this->requester->id,
            'division_id' => $this->division->id,
            'status' => TicketStatus::Done,
        ]);

        $response = $this->actingAs($this->supervisorIt)
            ->postJson("/api/v1/supervisor-it/tickets/{$ticket->id}/reopen", [
                'reason' => 'Masalah muncul kembali',
                'primary_user_id' => $this->picSupport->id,
            ]);

        $response->assertStatus(200);

        $ticket->refresh();
        $this->assertEquals(TicketStatus::Reopened, $ticket->status);
        $this->assertEquals($this->picSupport->id, $ticket->current_assignee_id);
    }

    public function test_requester_cannot_access_supervisor_it_endpoints(): void
    {
        $ticket = Ticket::factory()->create([
            'requester_id' => $this->requester->id,
            'division_id' => $this->division->id,
            'status' => TicketStatus::PendingValidation,
        ]);

        $response = $this->actingAs($this->requester)
            ->getJson('/api/v1/supervisor-it/dashboard');

        $response->assertStatus(403);

        $response2 = $this->actingAs($this->requester)
            ->postJson("/api/v1/supervisor-it/tickets/{$ticket->id}/approve", [
                'notes' => 'Self approve',
            ]);

        $response2->assertStatus(403);
    }
}
