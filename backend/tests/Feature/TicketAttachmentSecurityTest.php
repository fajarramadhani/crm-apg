<?php

namespace Tests\Feature;

use App\Console\Commands\SeedStage7PublicUatFixtureCommand;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Services\TicketAttachmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

final class TicketAttachmentSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_attachment_service_rejects_a_public_disk(): void
    {
        Storage::fake('public');
        config(['tickets.attachment_disk' => 'public']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('private filesystem disk');

        app(TicketAttachmentService::class)->store(
            Ticket::factory()->create(),
            UploadedFile::fake()->create('proof.pdf', 10, 'application/pdf'),
            null,
            'attachment',
            'requester',
        );
    }

    public function test_attachment_metadata_failure_restores_the_quarantined_file(): void
    {
        Storage::fake('local');
        config(['tickets.attachment_disk' => 'local']);
        $service = app(TicketAttachmentService::class);
        $attachment = $service->store(
            Ticket::factory()->create(),
            UploadedFile::fake()->create('..\\proof.final.pdf', 10, 'application/pdf'),
            null,
            'attachment',
            'requester_visible',
        );

        $this->assertSame('proof.final.pdf', $attachment->original_name);
        $this->assertSame('requester', $attachment->visibility);
        $this->assertFalse(str_contains($attachment->stored_name, '.'));

        try {
            $service->delete($attachment, fn () => throw new RuntimeException('metadata failure'));
            $this->fail('Expected metadata deletion to fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame('metadata failure', $exception->getMessage());
        }

        Storage::disk('local')->assertExists($attachment->path);
        $this->assertDatabaseHas('ticket_attachments', ['id' => $attachment->id]);
    }

    public function test_attachment_service_rejects_dangerous_double_extensions_before_storage(): void
    {
        Storage::fake('local');
        config(['tickets.attachment_disk' => 'local']);

        try {
            app(TicketAttachmentService::class)->store(
                Ticket::factory()->create(),
                UploadedFile::fake()->createWithContent('payload.exe.pdf', '%PDF-1.7'),
                null,
                'attachment',
                'requester',
            );
            $this->fail('Expected the dangerous filename to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('file', $exception->errors());
        }

        $this->assertDatabaseCount('ticket_attachments', 0);
        Storage::disk('local')->assertDirectoryEmpty('/');
    }

    public function test_public_attachment_migration_supports_dry_run_resume_and_rollback(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        config(['tickets.attachment_disk' => 'local']);
        $ticket = Ticket::factory()->create();
        $path = "tickets/{$ticket->id}/pic/legacy.pdf";
        Storage::disk('public')->put($path, 'legacy attachment');
        $attachment = TicketAttachment::query()->create([
            'ticket_id' => $ticket->id,
            'uploaded_by' => $ticket->requester_id,
            'original_name' => 'legacy.pdf',
            'stored_name' => 'legacy.pdf',
            'disk' => 'public',
            'path' => $path,
            'mime_type' => 'application/pdf',
            'size' => 17,
            'category' => 'result',
            'visibility' => 'internal',
        ]);

        $this->artisan('tickets:migrate-public-attachments', ['--dry-run' => true, '--ticket' => $ticket->id])
            ->expectsOutputToContain("Would migrate attachment {$attachment->id}")
            ->assertSuccessful();
        $this->assertSame('public', $attachment->fresh()->disk);

        $this->artisan('tickets:migrate-public-attachments', ['--ticket' => $ticket->id])->assertSuccessful();
        $this->assertSame('local', $attachment->fresh()->disk);
        Storage::disk('public')->assertMissing($path);
        Storage::disk('local')->assertExists($path);
        Storage::disk('local')->assertExists("attachment-migration-backups/public/{$attachment->id}");

        $this->artisan('tickets:migrate-public-attachments', ['--ticket' => $ticket->id])->assertSuccessful();
        $this->assertSame('local', $attachment->fresh()->disk);

        $this->artisan('tickets:migrate-public-attachments', ['--rollback' => true, '--ticket' => $ticket->id])->assertSuccessful();
        $this->assertSame('public', $attachment->fresh()->disk);
        Storage::disk('public')->assertExists($path);
        Storage::disk('local')->assertMissing($path);
    }

    public function test_stage7_public_uat_fixture_is_guarded_and_repeatable(): void
    {
        $this->assertNotNull(SeedStage7PublicUatFixtureCommand::safetyRefusal('production', 'stage7_test', SeedStage7PublicUatFixtureCommand::CONFIRMATION));
        $this->assertNotNull(SeedStage7PublicUatFixtureCommand::safetyRefusal('testing', 'apg_crm', SeedStage7PublicUatFixtureCommand::CONFIRMATION));
        $this->assertNull(SeedStage7PublicUatFixtureCommand::safetyRefusal('local', 'crm_local', SeedStage7PublicUatFixtureCommand::CONFIRMATION));
        config([
            'public_tracking.key' => str_repeat('k', 32),
            'public_tracking.keys' => null,
            'public_tracking.key_version' => 1,
            'public_tracking.frontend_url' => 'https://fixture.test',
        ]);

        $arguments = ['--confirm' => SeedStage7PublicUatFixtureCommand::CONFIRMATION, '--json' => true];
        $this->artisan('stage7:seed-public-uat', $arguments)->assertSuccessful();
        $this->artisan('stage7:seed-public-uat', $arguments)->assertSuccessful();

        $this->assertDatabaseCount('tickets', 1);
        $this->assertDatabaseCount('public_ticket_tracking_tokens', 1);
        $this->assertDatabaseHas('tickets', [
            'ticket_number' => 'STAGE7-PUBLIC-UAT-001',
            'submission_source' => 'public_form',
            'status' => TicketStatus::ReadyForUat->value,
        ]);
    }
}
