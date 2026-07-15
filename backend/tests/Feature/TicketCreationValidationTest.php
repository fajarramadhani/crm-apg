<?php

namespace Tests\Feature;

use App\Events\TicketSubmitted;
use App\Events\TicketValidated;
use App\Models\Application;
use App\Models\Division;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\TicketCategory;
use App\Models\TicketPriority;
use App\Models\User;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TicketCreationValidationTest extends TestCase
{
    use RefreshDatabase;

    private Division $division;

    private User $requester;

    private User $supervisor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, MasterDataSeeder::class]);
        $this->division = Division::where('code', 'IT')->firstOrFail();
        $this->requester = $this->user('requester', $this->division);
        $this->supervisor = $this->user('supervisor', $this->division);
    }

    public function test_requester_creates_submitted_ticket_with_session_identity_unique_number_history_and_event(): void
    {
        Event::fake([TicketSubmitted::class]);
        $first = $this->actingAs($this->requester)->postJson('/api/v1/tickets', $this->payload())->assertCreated()->assertJsonPath('data.status', 'pending_validation')->assertJsonPath('data.requester.id', $this->requester->id)->assertJsonStructure(['data' => ['ticket_number', 'allowed_actions'], 'meta' => ['request_id']]);
        $other = $this->user('requester', $this->division);
        $second = $this->actingAs($other)->postJson('/api/v1/tickets', [...$this->payload(), 'requester_id' => $this->requester->id, 'title' => 'Second ticket'])->assertCreated();
        $this->assertNotSame($first->json('data.ticket_number'), $second->json('data.ticket_number'));
        $this->assertMatchesRegularExpression('/^TIC-\d{6}-\d{6}$/', $first->json('data.ticket_number'));
        $this->assertDatabaseHas('tickets', ['id' => $second->json('data.id'), 'requester_id' => $other->id, 'final_priority_id' => null]);
        $this->assertDatabaseCount('ticket_number_sequences', 1);
        $this->assertDatabaseHas('ticket_status_histories', ['ticket_id' => $first->json('data.id'), 'action' => 'created']);
        $this->assertDatabaseHas('ticket_status_histories', ['ticket_id' => $first->json('data.id'), 'action' => 'submitted']);
        Event::assertDispatched(TicketSubmitted::class);
    }

    public function test_dynamic_validation_rejects_inactive_master_wrong_module_and_missing_type_field(): void
    {
        $application = Application::firstOrFail();
        $other = Application::whereKeyNot($application->id)->firstOrFail();
        $module = $other->modules()->create(['code' => 'WRONG', 'name' => 'Wrong', 'is_active' => true]);
        $request = TicketCategory::where('type', 'request')->firstOrFail();
        $this->actingAs($this->requester)->postJson('/api/v1/tickets', [...$this->payload(), 'ticket_category_id' => $request->id, 'application_id' => $application->id, 'application_module_id' => $module->id])->assertUnprocessable()->assertJsonValidationErrors(['application_module_id', 'request_purpose'])->assertJsonStructure(['meta' => ['request_id']]);
        $request->update(['is_active' => false]);
        $this->actingAs($this->requester)->postJson('/api/v1/tickets', [...$this->payload(), 'ticket_category_id' => $request->id])->assertUnprocessable()->assertJsonValidationErrors('ticket_category_id');
    }

    public function test_requester_list_filters_paginates_and_never_exposes_other_users_ticket(): void
    {
        $own = $this->createTicket($this->requester, ['title' => 'Searchable payroll issue']);
        $other = $this->user('requester', $this->division);
        $this->createTicket($other, ['title' => 'Private other issue']);
        $this->actingAs($this->requester)->getJson('/api/v1/tickets?search=payroll&status=pending_validation&per_page=1')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $own->id)->assertJsonPath('meta.pagination.per_page', 1);
        $this->actingAs($this->requester)->getJson('/api/v1/tickets/'.$other->tickets()->first()->id)->assertForbidden();
    }

    public function test_requester_can_update_revision_resubmit_and_cannot_set_status_or_update_validated(): void
    {
        $ticket = $this->createTicket($this->requester);
        $this->actingAs($this->supervisor)->postJson("/api/v1/supervisor/tickets/{$ticket->id}/request-revision", ['notes' => 'Please add business impact'])->assertOk();
        $payload = [...$this->payload(), 'title' => 'Revised title', 'status' => 'validated'];
        $this->actingAs($this->requester)->putJson("/api/v1/tickets/{$ticket->id}", $payload)->assertOk()->assertJsonPath('data.title', 'Revised title')->assertJsonPath('data.status', 'need_revision');
        $this->actingAs($this->requester)->postJson("/api/v1/tickets/{$ticket->id}/resubmit", ['notes' => 'Details added'])->assertOk()->assertJsonPath('data.status', 'pending_validation');
        $this->actingAs($this->supervisor)->postJson("/api/v1/supervisor/tickets/{$ticket->id}/validate", [])->assertOk();
        $this->actingAs($this->requester)->putJson("/api/v1/tickets/{$ticket->id}", $payload)->assertForbidden();
    }

    public function test_attachment_upload_download_delete_are_private_validated_and_audited(): void
    {
        Storage::fake('local');
        $ticket = $this->createTicket($this->requester);
        $upload = $this->actingAs($this->requester)->post("/api/v1/tickets/{$ticket->id}/attachments", ['file' => UploadedFile::fake()->create('proof.png', 10, 'image/png'), 'category' => 'screenshot'], ['Accept' => 'application/json'])->assertCreated()->assertJsonMissingPath('data.path');
        $attachment = TicketAttachment::findOrFail($upload->json('data.id'));
        Storage::disk('local')->assertExists($attachment->path);
        $other = $this->user('requester', $this->division);
        $this->actingAs($other)->get("/api/v1/tickets/{$ticket->id}/attachments/{$attachment->id}/download", ['Accept' => 'application/json'])->assertForbidden();
        $this->actingAs($this->requester)->get("/api/v1/tickets/{$ticket->id}/attachments/{$attachment->id}/download")->assertOk();
        $this->actingAs($this->requester)->post("/api/v1/tickets/{$ticket->id}/attachments", ['file' => UploadedFile::fake()->create('script.exe', 10, 'application/x-msdownload')], ['Accept' => 'application/json'])->assertUnprocessable();
        $this->actingAs($this->requester)->deleteJson("/api/v1/tickets/{$ticket->id}/attachments/{$attachment->id}")->assertOk();
        Storage::disk('local')->assertMissing($attachment->path);
        $this->assertDatabaseHas('ticket_status_histories', ['ticket_id' => $ticket->id, 'action' => 'attachment_removed']);
    }

    public function test_supervisor_queue_is_division_scoped_and_supports_actions_with_reasons(): void
    {
        Event::fake([TicketValidated::class]);
        $local = $this->createTicket($this->requester, ['title' => 'Local queue item']);
        $otherDivision = Division::where('code', 'HR')->firstOrFail();
        $foreign = $this->createTicket($this->user('requester', $otherDivision), ['title' => 'Foreign queue item']);
        $this->actingAs($this->supervisor)->getJson('/api/v1/supervisor/validation-queue?search=Local&per_page=10')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $local->id)->assertJsonMissing(['id' => $foreign->id]);
        $this->actingAs($this->supervisor)->getJson("/api/v1/supervisor/tickets/{$foreign->id}")->assertForbidden();
        $this->actingAs($this->supervisor)->postJson("/api/v1/supervisor/tickets/{$local->id}/request-revision", [])->assertUnprocessable()->assertJsonValidationErrors('notes');
        $this->actingAs($this->supervisor)->postJson("/api/v1/supervisor/tickets/{$local->id}/validate", ['notes' => 'Complete'])->assertOk()->assertJsonPath('data.status', 'validated');
        Event::assertDispatched(TicketValidated::class);
        $this->actingAs($this->supervisor)->postJson("/api/v1/supervisor/tickets/{$local->id}/reject", ['notes' => 'Too late'])->assertStatus(409)->assertJsonPath('error.code', 'INVALID_TRANSITION');
    }

    public function test_revision_reason_and_rejection_are_visible_and_history_is_preserved(): void
    {
        $ticket = $this->createTicket($this->requester);
        $this->actingAs($this->supervisor)->postJson("/api/v1/supervisor/tickets/{$ticket->id}/request-revision", ['notes' => 'Add screenshot'])->assertOk();
        $this->actingAs($this->requester)->getJson("/api/v1/tickets/{$ticket->id}")->assertOk()->assertJsonFragment(['type' => 'revision_request', 'comment' => 'Add screenshot'])->assertJsonFragment(['action' => 'revision_requested']);
        $rejected = $this->createTicket($this->requester, ['title' => 'Reject me']);
        $this->actingAs($this->supervisor)->postJson("/api/v1/supervisor/tickets/{$rejected->id}/reject", ['notes' => 'Not in scope'])->assertOk()->assertJsonPath('data.status', 'rejected');
        $this->assertNotNull($rejected->fresh()->rejected_at);
    }

    public function test_transfer_keeps_ticket_actionable_in_target_queue_and_rejects_same_or_inactive_division(): void
    {
        $ticket = $this->createTicket($this->requester);
        $target = Division::where('code', 'HR')->firstOrFail();
        $this->actingAs($this->supervisor)->postJson("/api/v1/supervisor/tickets/{$ticket->id}/transfer", ['target_division_id' => $this->division->id, 'notes' => 'Same'])->assertStatus(409);
        $this->actingAs($this->supervisor)->postJson("/api/v1/supervisor/tickets/{$ticket->id}/transfer", ['target_division_id' => $target->id, 'notes' => 'Wrong routing'])->assertOk()->assertJsonPath('data.status', 'pending_validation')->assertJsonPath('data.current_division.id', $target->id);
        $targetSupervisor = $this->user('supervisor', $target);
        $this->actingAs($targetSupervisor)->getJson('/api/v1/supervisor/validation-queue')->assertOk()->assertJsonFragment(['ticket_number' => $ticket->ticket_number]);
        $this->assertDatabaseHas('ticket_status_histories', ['ticket_id' => $ticket->id, 'action' => 'transferred', 'from_status' => 'pending_validation', 'to_status' => 'pending_validation']);
    }

    public function test_requester_can_cancel_before_validation_but_not_after(): void
    {
        $ticket = $this->createTicket($this->requester);
        $this->actingAs($this->requester)->postJson("/api/v1/tickets/{$ticket->id}/cancel", ['notes' => 'No longer needed'])->assertOk()->assertJsonPath('data.status', 'cancelled');
        $validated = $this->createTicket($this->requester, ['title' => 'Validated']);
        $this->actingAs($this->supervisor)->postJson("/api/v1/supervisor/tickets/{$validated->id}/validate", [])->assertOk();
        $this->actingAs($this->requester)->postJson("/api/v1/tickets/{$validated->id}/cancel", [])->assertForbidden();
    }

    public function test_internal_comments_are_hidden_from_requester(): void
    {
        $ticket = $this->createTicket($this->requester);
        $ticket->comments()->create(['user_id' => $this->supervisor->id, 'type' => 'general', 'comment' => 'Internal secret', 'is_internal' => true]);
        $ticket->comments()->create(['user_id' => $this->supervisor->id, 'type' => 'general', 'comment' => 'Public update', 'is_internal' => false]);
        $this->actingAs($this->requester)->getJson("/api/v1/tickets/{$ticket->id}")->assertOk()->assertJsonFragment(['comment' => 'Public update'])->assertJsonMissing(['comment' => 'Internal secret']);
    }

    private function payload(array $overrides = []): array
    {
        return [...['ticket_category_id' => TicketCategory::where('type', 'incident')->firstOrFail()->id, 'application_id' => Application::firstOrFail()->id, 'requested_priority_id' => TicketPriority::where('key', 'medium')->firstOrFail()->id, 'title' => 'Cannot generate policy PDF', 'description' => 'The operation consistently fails for a valid policy.', 'business_impact' => 'Operations cannot deliver documents.', 'urgency' => 'urgent', 'actual_result' => 'An error is shown.', 'expected_result' => 'PDF downloads.', 'reproduction_steps' => 'Open policy and click generate.'], ...$overrides];
    }

    private function user(string $role, Division $division): User
    {
        return User::factory()->create(['role_id' => Role::where('key', $role)->firstOrFail()->id, 'division_id' => $division->id, 'is_active' => true]);
    }

    private function createTicket(User $user,array $overrides = []): Ticket
    {
        $id = $this->actingAs($user)->postJson('/api/v1/tickets',$this->payload($overrides))->assertCreated()->json('data.id');

        return Ticket::findOrFail($id);
    }
}
