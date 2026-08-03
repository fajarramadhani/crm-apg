<?php

namespace Tests\Feature;

use App\Events\TicketSubmitted;
use App\Models\Application;
use App\Models\Branch;
use App\Models\Division;
use App\Models\PublicTicketSubmission;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Middleware\ThrottleRequests;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicTicketSubmissionTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private Division $division;

    private Application $application;

    private TicketCategory $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, MasterDataSeeder::class]);
        Storage::fake(config('tickets.attachment_disk', 'local'));
        Event::fake([TicketSubmitted::class]);

        $this->branch = Branch::query()->active()->firstOrFail();
        $this->division = Division::query()->active()->firstOrFail();
        $this->application = Application::query()->active()->firstOrFail();
        $this->application->update(['owner_division_id' => $this->division->id]);
        $this->category = TicketCategory::query()->active()->where('type', 'incident')->firstOrFail();
    }

    public function test_anonymous_options_only_return_active_safe_master_fields(): void
    {
        $inactive = Branch::query()->create(['code' => 'OFF', 'name' => 'Inactive', 'is_active' => false]);

        $response = $this->getJson('/api/v1/public/ticket-form-options')->assertOk();

        $response->assertJsonStructure(['data' => [
            'branches' => [['id', 'code', 'name']],
            'divisions' => [['id', 'code', 'name']],
            'categories' => [['id', 'code', 'name']],
            'applications' => [['id', 'code', 'name']],
        ]]);
        $this->assertNotContains($inactive->id, collect($response->json('data.branches'))->pluck('id'));
        $this->assertArrayNotHasKey('is_active', $response->json('data.branches.0'));
    }

    public function test_anonymous_create_normalizes_contacts_sanitizes_description_and_creates_no_user(): void
    {
        $usersBefore = User::query()->count();
        $payload = [...$this->payload(),
            'requester_email' => ' PERSON@Example.COM ',
            'requester_phone' => '0812-3456-7890',
            'description' => '<p>Hello <strong>team</strong></p><script>alert(1)</script>',
            'attachments' => [UploadedFile::fake()->create('evidence.pdf', 20, 'application/pdf')],
        ];

        $response = $this->withHeader('Idempotency-Key', $this->key('public-create-1'))
            ->postJson('/api/v1/public/tickets', $payload)
            ->assertCreated()
            ->assertJsonStructure(['data' => ['ticket_number', 'requester_name', 'branch_name', 'title', 'submitted_at', 'tracking_token', 'tracking_url', 'tracking_expires_at']]);

        $this->assertSame(['ticket_number', 'requester_name', 'branch_name', 'title', 'submitted_at', 'tracking_token', 'tracking_url', 'tracking_expires_at'], array_keys($response->json('data')));
        $this->assertSame($usersBefore, User::query()->count());
        $ticket = Ticket::query()->sole();
        $this->assertNull($ticket->requester_id);
        $this->assertSame('person@example.com', $ticket->requester_email);
        $this->assertSame('6281234567890', $ticket->requester_phone);
        $this->assertSame('error_bug', $ticket->request_category);
        $this->assertSame('public_form', $ticket->submission_source);
        $this->assertStringNotContainsString('script', $ticket->description);
        $this->assertDatabaseHas('ticket_attachments', ['ticket_id' => $ticket->id, 'uploaded_by' => null]);
        $this->assertDatabaseHas('ticket_status_histories', ['ticket_id' => $ticket->id, 'actor_id' => null, 'actor_role' => 'public_requester']);
        Event::assertDispatched(TicketSubmitted::class);
    }

    public function test_exact_replay_returns_same_receipt_and_changed_payload_conflicts(): void
    {
        $first = $this->withHeader('Idempotency-Key', $this->key('same-key'))->postJson('/api/v1/public/tickets', $this->payload())->assertCreated();
        $replay = $this->withHeader('Idempotency-Key', $this->key('same-key'))->postJson('/api/v1/public/tickets', $this->payload())->assertCreated();
        $this->assertSame($first->json('data.ticket_number'), $replay->json('data.ticket_number'));
        $this->assertSame($first->json('data.tracking_token'), $replay->json('data.tracking_token'));
        $this->assertSame($first->json('data.tracking_url'), $replay->json('data.tracking_url'));
        $this->assertDatabaseCount('tickets', 1);
        $this->assertDatabaseCount('public_ticket_tracking_tokens', 1);

        $this->withHeader('Idempotency-Key', $this->key('same-key'))
            ->postJson('/api/v1/public/tickets', [...$this->payload(), 'title' => 'Changed'])
            ->assertConflict()->assertJsonPath('error.code', 'IDEMPOTENCY_CONFLICT');
        $this->assertDatabaseCount('tickets', 1);
    }

    public function test_identity_content_and_active_master_validation_rejects_untrusted_input(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $inactiveBranch = Branch::query()->create(['code' => 'NOPE', 'name' => 'Inactive', 'is_active' => false]);
        $inactiveCategory = TicketCategory::query()->create([
            'code' => 'INACTIVE', 'name' => 'Inactive', 'type' => 'incident', 'is_active' => false,
        ]);
        $cases = [
            [['requester_name' => ''], 'requester_name'],
            [['requester_email' => null, 'requester_phone' => null], 'requester_email'],
            [['requester_email' => 'invalid'], 'requester_email'],
            [['requester_phone' => '12345'], 'requester_phone'],
            [['branch_id' => null], 'branch_id'],
            [['branch_id' => $inactiveBranch->id], 'branch_id'],
            [['division_id' => 999999], 'division_id'],
            [['ticket_category_id' => $inactiveCategory->id], 'ticket_category_id'],
            [['title' => ''], 'title'],
            [['title' => '<b>Injected</b>'], 'title'],
            [['description' => '<script>alert(1)</script>'], 'description'],
            [['website' => 'bot'], 'website'],
            [['status' => 'closed'], 'status'],
        ];

        foreach ($cases as $index => [$changes, $field]) {
            $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.'.($index + 1)])
                ->withHeader('Idempotency-Key', $this->key('invalid-'.$index))
                ->postJson('/api/v1/public/tickets', [...$this->payload(), ...$changes])
                ->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        $this->assertDatabaseCount('tickets', 0);
    }

    public function test_description_sanitizer_removes_all_disallowed_public_html(): void
    {
        $payloads = [
            '<script>alert("xss")</script><p>Safe content</p>',
            '<img src="x" onerror="alert(\'xss\')"><p>Safe content</p>',
            '<a href="javascript:alert(\'xss\')">Safe content</a>',
            '<iframe src="https://example.com"></iframe><p>Safe content</p>',
        ];

        foreach ($payloads as $index => $description) {
            $this->withHeader('Idempotency-Key', $this->key('xss-'.$index))
                ->postJson('/api/v1/public/tickets', [...$this->payload(), 'description' => $description])
                ->assertCreated();
        }

        foreach (Ticket::query()->pluck('description') as $description) {
            $this->assertStringNotContainsString('<script', $description);
            $this->assertStringNotContainsString('<img', $description);
            $this->assertStringNotContainsString('javascript:', $description);
            $this->assertStringNotContainsString('<iframe', $description);
        }
    }

    public function test_public_endpoint_rejects_unsupported_methods(): void
    {
        $this->putJson('/api/v1/public/tickets', $this->payload())->assertMethodNotAllowed();
        $this->postJson('/api/v1/public/ticket-form-options')->assertMethodNotAllowed();
    }

    public function test_attachment_and_header_validation_are_safe(): void
    {
        $this->postJson('/api/v1/public/tickets', $this->payload())
            ->assertUnprocessable()->assertJsonValidationErrors('idempotency_key');

        $this->withHeader('Idempotency-Key', $this->key('bad-file'))->postJson('/api/v1/public/tickets', [
            ...$this->payload(), 'attachments' => [UploadedFile::fake()->create('payload.exe', 10, 'application/octet-stream')],
        ])->assertUnprocessable()->assertJsonValidationErrors('attachments.0');
    }

    public function test_public_submission_ip_limiter_returns_safe_api_envelope(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->withHeader('Idempotency-Key', $this->key('rate-'.$attempt))
                ->postJson('/api/v1/public/tickets', [...$this->payload(), 'reference' => (string) $attempt])
                ->assertCreated();
        }

        $this->withHeader('Idempotency-Key', $this->key('rate-6'))
            ->postJson('/api/v1/public/tickets', [...$this->payload(), 'reference' => '6'])
            ->assertTooManyRequests()->assertJsonPath('error.code', 'TOO_MANY_ATTEMPTS');
    }

    public function test_authenticated_create_endpoint_remains_protected(): void
    {
        $this->postJson('/api/v1/requester/tickets', [])->assertUnauthorized();
    }

    public function test_expired_public_idempotency_records_use_existing_cleanup_command(): void
    {
        PublicTicketSubmission::query()->create([
            'key_hash' => hash('sha256', 'expired-public'),
            'request_hash' => hash('sha256', 'request'),
            'expires_at' => now()->subSecond(),
        ]);

        $this->artisan('idempotency:cleanup')
            ->expectsOutput('Deleted 1 expired idempotency record(s).')
            ->assertSuccessful();

        $this->assertDatabaseMissing('public_ticket_submissions', [
            'key_hash' => hash('sha256', 'expired-public'),
        ]);
    }

    public function test_public_ticket_is_visible_in_its_division_supervisor_queue_with_snapshot_requester(): void
    {
        $this->withHeader('Idempotency-Key', $this->key('queue-visible'))
            ->postJson('/api/v1/public/tickets', $this->payload())->assertCreated();
        $supervisor = User::factory()->create([
            'role_id' => Role::query()->where('key', 'supervisor')->value('id'),
            'division_id' => $this->division->id,
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);

        $this->actingAs($supervisor)->getJson('/api/v1/supervisor/validation-queue?search=Public%20Requester')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.requester.id', null)
            ->assertJsonPath('data.0.requester.name', 'Public Requester')
            ->assertJsonPath('data.0.submission_source', 'public_form');
    }

    private function payload(): array
    {
        return [
            'requester_name' => 'Public Requester',
            'requester_email' => 'public@example.com',
            'branch_id' => $this->branch->id,
            'division_id' => $this->division->id,
            'ticket_category_id' => $this->category->id,
            'application_id' => $this->application->id,
            'title' => 'Public ticket request',
            'description' => 'The application fails during a documented business operation.',
            'affected_url' => 'https://example.com/problem',
            'urgency' => 'medium',
        ];
    }

    private function key(string $value): string
    {
        return hash('sha256', $value);
    }
}
