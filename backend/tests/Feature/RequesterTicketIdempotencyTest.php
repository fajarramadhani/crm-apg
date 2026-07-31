<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Division;
use App\Models\IdempotencyRecord;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\ApplicationSystemSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RequesterTicketIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    private User $requester;

    private Role $requesterRole;

    private Division $division;

    private Application $application;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('tickets.attachment_disk', 'local'));
        $this->seed(RoleSeeder::class);
        $this->seed(ApplicationSystemSeeder::class);
        $this->application = Application::query()->where('code', 'HRIS')->firstOrFail();

        $this->division = Division::query()->create([
            'code' => 'IT',
            'name' => 'Information Technology',
            'is_active' => true,
        ]);
        $this->requesterRole = Role::query()->where('key', 'requester')->firstOrFail();
        $this->requester = $this->newRequester();
    }

    public function test_same_key_and_logical_payload_replays_original_ticket_without_side_effects(): void
    {
        $first = $this->createTicket($this->requester, 'ticket-key-1');
        $counts = $this->sideEffectCounts();

        $replay = $this->createTicket($this->requester, 'ticket-key-1');

        $first->assertCreated();
        $replay->assertCreated()
            ->assertJsonPath('data.id', $first->json('data.id'));
        $this->assertSame($counts, $this->sideEffectCounts());

        $record = IdempotencyRecord::query()->sole();
        $this->assertSame(hash('sha256', 'ticket-key-1'), $record->key_hash);
        $this->assertSame($first->json('data.id'), $record->ticket_id);
        $this->assertSame(64, strlen($record->request_hash));
    }

    public function test_same_key_with_changed_payload_returns_conflict(): void
    {
        $this->createTicket($this->requester, 'ticket-key-2')->assertCreated();

        $response = $this->createTicket($this->requester, 'ticket-key-2', 'Changed title');

        $response->assertStatus(409)
            ->assertJsonPath('error.code', 'IDEMPOTENCY_CONFLICT')
            ->assertJsonPath('meta.reason', 'payload_mismatch');
        $this->assertDatabaseCount('tickets', 1);
    }

    public function test_same_key_is_independent_for_different_users(): void
    {
        $otherRequester = $this->newRequester();

        $first = $this->createTicket($this->requester, 'shared-key');
        $second = $this->createTicket($otherRequester, 'shared-key');

        $first->assertCreated();
        $second->assertCreated();
        $this->assertNotSame($first->json('data.id'), $second->json('data.id'));
        $this->assertDatabaseCount('tickets', 2);
        $this->assertDatabaseCount('idempotency_records', 2);
    }

    public function test_missing_key_remains_backward_compatible(): void
    {
        $this->createTicket($this->requester)->assertCreated();
        $this->createTicket($this->requester)->assertCreated();

        $this->assertDatabaseCount('tickets', 2);
        $this->assertDatabaseCount('idempotency_records', 0);
    }

    public function test_cleanup_command_deletes_only_expired_records(): void
    {
        IdempotencyRecord::query()->create([
            'user_id' => $this->requester->id,
            'endpoint' => 'POST /api/v1/requester/tickets',
            'action' => 'create_requester_ticket',
            'key_hash' => hash('sha256', 'expired'),
            'request_hash' => hash('sha256', 'request'),
            'expires_at' => now()->subSecond(),
        ]);
        IdempotencyRecord::query()->create([
            'user_id' => $this->requester->id,
            'endpoint' => 'POST /api/v1/requester/tickets',
            'action' => 'create_requester_ticket',
            'key_hash' => hash('sha256', 'active'),
            'request_hash' => hash('sha256', 'request'),
            'expires_at' => now()->addHour(),
        ]);

        $this->artisan('idempotency:cleanup')
            ->expectsOutput('Deleted 1 expired idempotency record(s).')
            ->assertSuccessful();

        $this->assertDatabaseMissing('idempotency_records', ['key_hash' => hash('sha256', 'expired')]);
        $this->assertDatabaseHas('idempotency_records', ['key_hash' => hash('sha256', 'active')]);
    }

    private function newRequester(): User
    {
        return User::factory()->create([
            'role_id' => $this->requesterRole->id,
            'division_id' => $this->division->id,
            'is_active' => true,
        ]);
    }

    private function createTicket(User $user, ?string $key = null, string $title = 'Idempotent ticket')
    {
        $headers = $key === null ? [] : ['Idempotency-Key' => $key];

        return $this->actingAs($user)->withHeaders($headers)->postJson('/api/v1/requester/tickets', [
            'request_category' => 'error_bug',
            'application_id' => $this->application->id,
            'title' => $title,
            'description' => 'The same logical request should create one ticket.',
            'affected_url' => 'https://example.com/idempotency',
            'reference' => 'REF-100',
            'attachments' => [UploadedFile::fake()->create('evidence.pdf', 10, 'application/pdf')],
            'urgency' => 'medium',
        ]);
    }

    /** @return array<string, int> */
    private function sideEffectCounts(): array
    {
        return [
            'tickets' => (int) \DB::table('tickets')->count(),
            'histories' => (int) \DB::table('ticket_status_histories')->count(),
            'attachments' => (int) \DB::table('ticket_attachments')->count(),
            'notifications' => (int) \DB::table('notifications')->count(),
        ];
    }
}
