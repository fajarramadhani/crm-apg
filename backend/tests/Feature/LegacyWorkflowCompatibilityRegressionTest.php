<?php

namespace Tests\Feature;

use App\Enums\TicketStatus;
use App\Models\Application;
use App\Models\Division;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\User;
use App\Services\LegacyTransitionHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Regression test: ensures existing legacy tickets and flows are unaffected
 * by Tahap 7 dynamic workflow additions.
 *
 * All existing tests from Tahap 2–6 must continue to pass.
 * This class adds specific "dynamic-does-not-break-legacy" assertions.
 */
class LegacyWorkflowCompatibilityRegressionTest extends TestCase
{
    use RefreshDatabase;

    private User $supervisorIt;

    private User $requester;

    private User $pic;

    private Division $division;

    protected function setUp(): void
    {
        parent::setUp();

        $supervisorRole = Role::factory()->create(['key' => 'supervisor_it']);
        $requesterRole = Role::factory()->create(['key' => 'requester']);
        $picRole = Role::factory()->create(['key' => 'pic_it_support']);

        $this->division = Division::create([
            'code' => 'IT',
            'name' => 'Information Technology',
            'is_active' => true,
        ]);
        $this->supervisorIt = User::factory()->create(['role_id' => $supervisorRole->id, 'is_active' => true, 'division_id' => $this->division->id]);
        $this->requester = User::factory()->create(['role_id' => $requesterRole->id, 'is_active' => true, 'division_id' => $this->division->id]);
        $this->pic = User::factory()->create(['role_id' => $picRole->id, 'is_active' => true, 'division_id' => $this->division->id]);
    }

    // =========================================================================
    // Legacy ticket model assertions
    // =========================================================================

    public function test_legacy_ticket_has_null_workflow_fields(): void
    {
        $ticket = Ticket::factory()->create([
            'workflow_mode' => null,
            'current_workflow_stage' => null,
            'workflow_id' => null,
            'workflow_snapshot' => null,
        ]);

        $this->assertNull($ticket->workflow_mode);
        $this->assertNull($ticket->current_workflow_stage);
        $this->assertNull($ticket->workflow_id);
        $this->assertNull($ticket->workflow_snapshot);
    }

    public function test_legacy_ticket_status_enum_is_unchanged(): void
    {
        $ticket = Ticket::factory()->create([
            'status' => TicketStatus::PendingValidation,
            'workflow_mode' => null,
        ]);

        $this->assertEquals(TicketStatus::PendingValidation, $ticket->status);
        $this->assertSame('pending_validation', $ticket->status->value);
    }

    public function test_all_existing_ticket_statuses_remain_accessible(): void
    {
        $expectedStatuses = [
            'pending_validation', 'draft', 'submitted', 'validated',
            'rejected', 'under_analysis', 'triage', 'analysis',
            'assigned', 'in_progress', 'need_info', 'need_revision',
            'revision', 'waiting_external', 'on_hold',
            'development_in_progress', 'ready_for_development',
            'internal_testing', 'pending_approval',
            'awaiting_requester_confirmation', 'done', 'closed',
            'cancelled', 'reopened',
        ];

        foreach ($expectedStatuses as $value) {
            $this->assertNotNull(
                TicketStatus::tryFrom($value),
                "TicketStatus enum value '{$value}' is missing."
            );
        }
    }

    // =========================================================================
    // Feature flag OFF = legacy path
    // =========================================================================

    public function test_creating_ticket_with_flag_off_uses_legacy_mode(): void
    {
        config(['crm.dynamic_workflow_enabled' => false]);
        $application = Application::create([
            'code' => 'LEGACY-APP',
            'name' => 'Legacy Application',
            'owner_division_id' => $this->division->id,
            'is_active' => true,
        ]);
        Storage::fake(config('tickets.attachment_disk', 'local'));

        // Requester creates ticket via API (no workflow should be attached)
        $this->actingAs($this->requester)
            ->postJson('/api/v1/requester/tickets', [
                'request_category' => 'error_bug',
                'application_id' => $application->id,
                'title' => 'Legacy ticket test',
                'description' => 'Testing legacy mode',
                'affected_url' => 'https://example.com',
                'attachments' => [UploadedFile::fake()->create('legacy.png', 10, 'image/png')],
                'urgency' => 'medium',
            ])
            ->assertStatus(201);

        $ticket = Ticket::latest()->first();

        $this->assertNull($ticket->workflow_id);
        $this->assertNull($ticket->workflow_mode);
        $this->assertNull($ticket->current_workflow_stage);
        $this->assertEquals(TicketStatus::PendingValidation, $ticket->status);
    }

    // =========================================================================
    // LegacyTransitionHandler assertions
    // =========================================================================

    public function test_legacy_handler_identifies_null_mode_as_legacy(): void
    {
        $handler = app(LegacyTransitionHandler::class);

        $legacyTicket = Ticket::factory()->create(['workflow_mode' => null]);
        $this->assertTrue($handler->isLegacyTicket($legacyTicket));

        $legacyTicket2 = Ticket::factory()->create(['workflow_mode' => 'legacy']);
        $this->assertTrue($handler->isLegacyTicket($legacyTicket2));

        $dynamicTicket = Ticket::factory()->create(['workflow_mode' => 'dynamic']);
        $this->assertFalse($handler->isLegacyTicket($dynamicTicket));
    }

    public function test_legacy_handler_throws_when_called_on_dynamic_ticket(): void
    {
        $handler = app(LegacyTransitionHandler::class);
        $dynamicTicket = Ticket::factory()->create(['workflow_mode' => 'dynamic']);

        $this->expectException(\LogicException::class);
        $handler->assertLegacyTicket($dynamicTicket);
    }

    // =========================================================================
    // Dynamic workflow status endpoint does not break for legacy tickets
    // =========================================================================

    public function test_workflow_status_endpoint_returns_legacy_badge_for_legacy_ticket(): void
    {
        $ticket = Ticket::factory()->create(['workflow_mode' => null]);

        $this->actingAs($this->supervisorIt)
            ->getJson("/api/v1/tickets/{$ticket->id}/workflow-status")
            ->assertOk()
            ->assertJsonPath('data.workflow_type', 'legacy')
            ->assertJsonPath('data.available_actions', []);
    }

    public function test_dynamic_transition_endpoint_returns_422_for_legacy_ticket(): void
    {
        $ticket = Ticket::factory()->create(['workflow_mode' => null]);

        $this->actingAs($this->supervisorIt)
            ->postJson("/api/v1/tickets/{$ticket->id}/workflow-transition", [
                'action_key' => 'start_analysis',
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'NOT_DYNAMIC_TICKET');
    }
}
