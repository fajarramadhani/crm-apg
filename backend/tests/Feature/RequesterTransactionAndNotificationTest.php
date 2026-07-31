<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Division;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\User;
use App\Services\RequesterTicketService;
use Database\Seeders\ApplicationSystemSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RequesterTransactionAndNotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $requester;

    private Division $division;

    private Application $application;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(ApplicationSystemSeeder::class);
        $this->application = Application::query()->where('code', 'HRIS')->firstOrFail();
        Storage::fake(config('tickets.attachment_disk', 'local'));

        $this->division = Division::create(['code' => 'IT', 'name' => 'Information Technology', 'is_active' => true]);

        $role = Role::query()->where('key', 'requester')->firstOrFail();

        $this->requester = User::factory()->create([
            'role_id' => $role->id,
            'division_id' => $this->division->id,
            'is_active' => true,
        ]);
    }

    public function test_transaction_rolls_back_and_cleans_up_files_on_failure(): void
    {
        $file = UploadedFile::fake()->create('screen.png', 500, 'image/png');

        // Set invalid division_id to trigger DB foreign key exception inside transaction
        $this->requester->division_id = 999999;
        $disk = config('tickets.attachment_disk', 'local');

        $service = app(RequesterTicketService::class);

        try {
            $service->createTicket($this->requester, [
                'request_category' => 'error_bug',
                'application_id' => $this->application->id,
                'title' => 'Test Rollback',
                'description' => 'Description test',
                'affected_url' => 'https://example.com/err',
                'urgency' => 'medium',
            ], [$file]);

            $this->fail('Expected DB exception was not thrown.');
        } catch (\Throwable $e) {
            $this->assertNotNull($e);
        }

        $this->assertDatabaseCount('tickets', 0);
        $this->assertDatabaseCount('ticket_attachments', 0);
        $this->assertEmpty(Storage::disk($disk)->allFiles('tickets'));
    }

    public function test_notification_falls_back_to_it_lead_when_no_supervisor_exists(): void
    {
        $itLeadRole = Role::query()->where('key', 'it_lead')->firstOrFail();

        $itLead = User::factory()->create([
            'role_id' => $itLeadRole->id,
            'is_active' => true,
        ]);

        $file = UploadedFile::fake()->create('screenshot.png', 200, 'image/png');

        $response = $this->actingAs($this->requester)
            ->postJson('/api/v1/requester/tickets', [
                'request_category' => 'error_bug',
                'application_id' => $this->application->id,
                'title' => 'Tiket Baru dengan Fallback Notifikasi',
                'description' => 'Supervisor belum dimigrasikan sehingga notifikasi harus masuk ke IT Lead.',
                'affected_url' => 'https://example.com/system',
                'attachments' => [$file],
                'urgency' => 'high',
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $itLead->id,
            'notifiable_type' => User::class,
        ]);
    }

    public function test_legacy_tickets_and_routes_remain_functional(): void
    {
        $ticket = Ticket::query()->create([
            'ticket_number' => 'TCK-2026-999999',
            'requester_id' => $this->requester->id,
            'division_id' => $this->division->id,
            'current_division_id' => $this->division->id,
            'title' => 'Tiket Legacy Existing',
            'description' => 'Deskripsi tiket legacy',
            'affected_url' => 'https://example.com/legacy',
            'status' => 'pending_validation',
        ]);

        $response = $this->actingAs($this->requester)
            ->getJson("/api/v1/tickets/{$ticket->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.title', 'Tiket Legacy Existing');
    }
}
