<?php

namespace Tests\Feature;

use App\Enums\TicketStatus;
use App\Models\Application;
use App\Models\Branch;
use App\Models\Division;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\TicketCategory;
use App\Models\TicketPriority;
use App\Models\User;
use App\Services\DynamicAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PicWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    private User $picSupport;

    private User $picDevelop;

    private User $supervisor;

    private User $requester;

    private User $unassignedPic;

    private Ticket $ticket;

    private DynamicAssignmentService $assignmentService;

    protected function setUp(): void
    {
        parent::setUp();

        $supportRole = Role::query()->firstOrCreate(['key' => 'pic_it_support'], ['name' => 'PIC IT Support', 'is_active' => true]);
        $developRole = Role::query()->firstOrCreate(['key' => 'pic_it_develop'], ['name' => 'PIC IT Develop', 'is_active' => true]);
        $supervisorRole = Role::query()->firstOrCreate(['key' => 'supervisor_it'], ['name' => 'Supervisor IT', 'is_active' => true]);
        $requesterRole = Role::query()->firstOrCreate(['key' => 'requester'], ['name' => 'Requester', 'is_active' => true]);

        $division = Division::query()->create(['code' => 'DIV-IT', 'name' => 'Divisi IT', 'is_active' => true]);
        $branch = Branch::query()->create(['code' => 'BR-01', 'name' => 'Cabang Utama', 'is_active' => true]);

        $this->picSupport = User::factory()->create(['role_id' => $supportRole->id, 'division_id' => $division->id, 'branch_id' => $branch->id, 'is_active' => true]);
        $this->picDevelop = User::factory()->create(['role_id' => $developRole->id, 'division_id' => $division->id, 'branch_id' => $branch->id, 'is_active' => true]);
        $this->supervisor = User::factory()->create(['role_id' => $supervisorRole->id, 'division_id' => $division->id, 'branch_id' => $branch->id, 'is_active' => true]);
        $this->requester = User::factory()->create(['role_id' => $requesterRole->id, 'division_id' => $division->id, 'branch_id' => $branch->id, 'is_active' => true]);
        $this->unassignedPic = User::factory()->create(['role_id' => $supportRole->id, 'division_id' => $division->id, 'branch_id' => $branch->id, 'is_active' => true]);

        $app = Application::query()->create(['code' => 'APP-01', 'name' => 'Core System', 'is_active' => true]);
        $category = TicketCategory::query()->create(['code' => 'CAT-01', 'name' => 'Bug System Internal', 'type' => 'incident', 'is_active' => true]);
        $priority = TicketPriority::query()->firstOrCreate(['key' => 'medium'], ['name' => 'Medium', 'level' => 2, 'color' => 'yellow']);

        $this->ticket = Ticket::factory()->create([
            'requester_id' => $this->requester->id,
            'current_division_id' => $division->id,
            'branch_id' => $branch->id,
            'application_id' => $app->id,
            'ticket_category_id' => $category->id,
            'final_priority_id' => $priority->id,
            'status' => TicketStatus::Assigned,
        ]);

        $this->assignmentService = app(DynamicAssignmentService::class);
        $this->assignmentService->assignPrimary($this->ticket, $this->supervisor, $this->picSupport, 'Initial primary assignment');
    }

    public function test_pic_can_view_dashboard_and_assigned_tickets(): void
    {
        $response = $this->actingAs($this->picSupport)->getJson('/api/v1/pic/dashboard');
        $response->assertStatus(200)
            ->assertJsonPath('data.summary_cards.new_assigned', 1);

        $listResponse = $this->actingAs($this->picSupport)->getJson('/api/v1/pic/tickets');
        $listResponse->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_pic_detail_exposes_workspace_actions_for_assignment_role_and_status(): void
    {
        $primaryResponse = $this->actingAs($this->picSupport)
            ->getJson("/api/v1/pic/tickets/{$this->ticket->id}");

        $primaryResponse->assertOk()
            ->assertJsonPath('data.user_assignment_role', 'primary')
            ->assertJsonFragment(['allowed_actions' => [
                'add_work_note',
                'update_progress',
                'upload_attachment',
                'mark_waiting_external',
                'internal_check',
                'request_assistance',
                'request_transfer',
                'request_info',
                'start',
                'submit_for_approval',
            ]]);

        $this->assignmentService->addSecondary($this->ticket, $this->supervisor, $this->picDevelop, 'Assigned secondary');

        $secondaryResponse = $this->actingAs($this->picDevelop)
            ->getJson("/api/v1/pic/tickets/{$this->ticket->id}");

        $secondaryResponse->assertOk()
            ->assertJsonPath('data.user_assignment_role', 'secondary');

        $secondaryActions = $secondaryResponse->json('data.allowed_actions');
        $this->assertContains('add_work_note', $secondaryActions);
        $this->assertNotContains('request_info', $secondaryActions);
        $this->assertNotContains('start', $secondaryActions);
        $this->assertNotContains('submit_for_approval', $secondaryActions);
    }

    public function test_pic_ticket_list_caps_per_page_at_one_hundred(): void
    {
        $this->actingAs($this->picSupport)
            ->getJson('/api/v1/pic/tickets?per_page=500')
            ->assertOk()
            ->assertJsonPath('meta.pagination.per_page', 100);
    }

    public function test_unassigned_pic_gets_empty_list_and_403_on_ticket_detail(): void
    {
        $listResponse = $this->actingAs($this->unassignedPic)->getJson('/api/v1/pic/tickets');
        $listResponse->assertStatus(200)->assertJsonCount(0, 'data');

        $detailResponse = $this->actingAs($this->unassignedPic)->getJson("/api/v1/pic/tickets/{$this->ticket->id}");
        $detailResponse->assertStatus(403);
    }

    public function test_primary_pic_can_start_work_add_notes_and_update_progress(): void
    {
        $startRes = $this->actingAs($this->picSupport)->postJson("/api/v1/pic/tickets/{$this->ticket->id}/start");
        $startRes->assertStatus(200)->assertJsonPath('data.status', 'in_progress');

        $noteRes = $this->actingAs($this->picSupport)->postJson("/api/v1/pic/tickets/{$this->ticket->id}/work-notes", [
            'content' => 'Menganalisis file log server.',
            'visibility' => 'internal',
        ]);
        $noteRes->assertStatus(201)->assertJsonPath('data.is_internal', true);

        $progressRes = $this->actingAs($this->picSupport)->postJson("/api/v1/pic/tickets/{$this->ticket->id}/progress", [
            'progress_percentage' => 50,
            'notes' => 'Perbaikan skrip query telah selesai.',
        ]);
        $progressRes->assertStatus(200)->assertJsonPath('data.progress_percentage', 50);
    }

    public function test_secondary_pic_can_add_notes_and_progress_but_cannot_submit_for_approval(): void
    {
        $this->assignmentService->addSecondary($this->ticket, $this->supervisor, $this->picDevelop, 'Assigned secondary');

        $noteRes = $this->actingAs($this->picDevelop)->postJson("/api/v1/pic/tickets/{$this->ticket->id}/work-notes", [
            'content' => 'Secondary PIC membantu pengujian integrasi.',
            'visibility' => 'internal',
        ]);
        $noteRes->assertStatus(201);

        $submitRes = $this->actingAs($this->picDevelop)->postJson("/api/v1/pic/tickets/{$this->ticket->id}/submit-for-approval", [
            'result_summary' => 'Mencoba submit approval sebagai secondary',
        ]);
        $submitRes->assertStatus(403);
    }

    public function test_pic_can_request_info_from_requester(): void
    {
        $res = $this->actingAs($this->picSupport)->postJson("/api/v1/pic/tickets/{$this->ticket->id}/request-info", [
            'question' => 'Mohon berikan screenshot error konsol browser Anda.',
        ]);

        $res->assertStatus(200)->assertJsonPath('data.status', 'need_info');
    }

    public function test_pic_can_mark_waiting_external_and_resume(): void
    {
        $waitRes = $this->actingAs($this->picSupport)->postJson("/api/v1/pic/tickets/{$this->ticket->id}/waiting-external", [
            'external_party_name' => 'Vendor Asuransi XYZ',
            'reference_number' => 'REF-9981',
            'notes' => 'Menunggu konfirmasi API key baru.',
        ]);
        $waitRes->assertStatus(200)->assertJsonPath('data.status', 'waiting_external');

        $resumeRes = $this->actingAs($this->picSupport)->postJson("/api/v1/pic/tickets/{$this->ticket->id}/resume");
        $resumeRes->assertStatus(200)->assertJsonPath('data.status', 'in_progress');
    }

    public function test_pic_can_upload_attachment(): void
    {
        Storage::fake('local');

        $file = UploadedFile::fake()->create('proof_fix.pdf', 500, 'application/pdf');

        $res = $this->actingAs($this->picSupport)->postJson("/api/v1/pic/tickets/{$this->ticket->id}/attachments", [
            'file' => $file,
            'visibility' => 'requester_visible',
        ]);

        $res->assertStatus(201)
            ->assertJsonPath('data.original_name', 'proof_fix.pdf')
            ->assertJsonPath('data.visibility', 'requester')
            ->assertJsonMissingPath('data.path');
        $attachment = TicketAttachment::query()->findOrFail($res->json('data.id'));
        $this->assertSame('local', $attachment->disk);
        $this->assertFalse(str_contains($attachment->stored_name, '.'));
        Storage::disk('local')->assertExists($attachment->path);
    }

    public function test_primary_pic_can_submit_for_approval(): void
    {
        $this->ticket->update(['status' => TicketStatus::InProgress]);

        // First do internal check
        $checkRes = $this->actingAs($this->picSupport)->postJson("/api/v1/pic/tickets/{$this->ticket->id}/internal-check", [
            'result' => 'passed',
            'notes' => 'Seluruh kriteria penerimaan telah teruji dengan sukses.',
        ]);
        $checkRes->assertStatus(200);

        // Submit for approval
        $submitRes = $this->actingAs($this->picSupport)->postJson("/api/v1/pic/tickets/{$this->ticket->id}/submit-for-approval", [
            'result_summary' => 'Masalah query database telah diperbaiki dan dioptimasi.',
            'requester_summary' => 'Perbaikan sistem telah selesai dilakukan dan diuji.',
        ]);

        $submitRes->assertStatus(200)->assertJsonPath('data.status', 'pending_approval');
    }

    public function test_pic_can_request_assistance_and_transfer(): void
    {
        $assistRes = $this->actingAs($this->picSupport)->postJson("/api/v1/pic/tickets/{$this->ticket->id}/request-assistance", [
            'reason' => 'Membutuhkan bantuan penyesuaian query PostgreSQL kompleks.',
            'required_expertise' => 'Database Specialist',
        ]);
        $assistRes->assertStatus(200);

        $transferRes = $this->actingAs($this->picSupport)->postJson("/api/v1/pic/tickets/{$this->ticket->id}/request-transfer", [
            'reason' => 'Tiket lebih sesuai ditangani oleh tim IT Develop.',
            'suggested_pic_id' => $this->picDevelop->id,
        ]);
        $transferRes->assertStatus(200);
    }

    public function test_supervisor_acting_as_pic_has_full_access(): void
    {
        $this->assignmentService->assignPrimary($this->ticket, $this->supervisor, $this->supervisor, 'Supervisor handle self');

        $res = $this->actingAs($this->supervisor)->getJson("/api/v1/pic/tickets/{$this->ticket->id}");
        $res->assertStatus(200)->assertJsonPath('data.user_assignment_role', 'primary');
    }

    public function test_requester_cannot_access_pic_endpoints(): void
    {
        $res = $this->actingAs($this->requester)->getJson('/api/v1/pic/dashboard');
        $res->assertStatus(403);
    }
}
