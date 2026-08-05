<?php

namespace Tests\Feature;

use App\Enums\TicketStatus;
use App\Events\TicketSubmitted;
use App\Models\Application;
use App\Models\Branch;
use App\Models\Division;
use App\Models\PublicTicketTrackingToken;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use App\Services\PublicTicketStatusMapper;
use App\Services\PublicTicketTrackingService;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PublicTicketTrackingTest extends TestCase
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
        Event::fake([TicketSubmitted::class]);
        config()->set('public_tracking.key', 'stage-two-test-key-with-at-least-32-bytes-of-entropy');
        config()->set('public_tracking.frontend_url', 'https://frontend.example.test');

        $this->branch = Branch::query()->active()->firstOrFail();
        $this->division = Division::query()->active()->firstOrFail();
        $this->application = Application::query()->active()->firstOrFail();
        $this->application->update(['owner_division_id' => $this->division->id]);
        $this->category = TicketCategory::query()->active()->where('type', 'incident')->firstOrFail();
    }

    public function test_issue_persists_only_hash_and_returns_a_256_bit_token_with_security_headers(): void
    {
        [$token, $response] = $this->issue('secure-issue');
        $stored = PublicTicketTrackingToken::query()->sole();
        $decoded = base64_decode(strtr($token, '-_', '+/').'=', true);

        $this->assertIsString($decoded);
        $this->assertSame(32, strlen($decoded));
        $this->assertSame(43, strlen($token));
        $this->assertSame(hash('sha256', $token), $stored->token_hash);
        $this->assertNotSame($token, $stored->derivation_nonce);
        $this->assertStringNotContainsString($token, json_encode($stored->getAttributes(), JSON_THROW_ON_ERROR));
        $this->assertStringEndsWith('/track/'.$token, $response->json('data.tracking_url'));
        $this->assertTrackingHeaders($response);

        [$second] = $this->issue('secure-issue-2', ['reference' => 'second']);
        $this->assertNotSame($token, $second);
        $this->assertSame(2, PublicTicketTrackingToken::query()->distinct()->count('token_hash'));
    }

    public function test_valid_lookup_returns_only_public_dto_fields_and_no_sensitive_data(): void
    {
        [$token] = $this->issue('valid-lookup');
        $ticket = Ticket::query()->sole();
        $ticket->update(['requester_email' => 'secret@example.test', 'description' => 'private description']);

        $response = $this->getJson('/api/v1/public/tickets/track/'.$token)->assertOk();
        $this->assertSame([
            'ticket_number', 'title', 'branch', 'category', 'submitted_at', 'status', 'timeline', 'requester_updates', 'last_updated_at', 'action_available',
        ], array_keys($response->json('data')));
        $response->assertJsonStructure(['data' => ['status' => ['code', 'label', 'description']]]);
        $json = $response->getContent();
        foreach (['secret@example.test', 'private description', 'requester_id', 'actor_id', 'metadata', 'token_hash', 'derivation_nonce'] as $sensitive) {
            $this->assertStringNotContainsString($sensitive, $json);
        }
        $this->assertTrackingHeaders($response);
    }

    public function test_invalid_expired_and_revoked_tokens_have_the_same_safe_failure(): void
    {
        [$token] = $this->issue('failure-modes');
        $invalid = $this->getJson('/api/v1/public/tickets/track/'.str_repeat('A', 43))->assertNotFound();

        PublicTicketTrackingToken::query()->sole()->update(['expires_at' => now()->subSecond()]);
        $expired = $this->getJson('/api/v1/public/tickets/track/'.$token)->assertNotFound();

        PublicTicketTrackingToken::query()->sole()->update(['expires_at' => null, 'revoked_at' => now()]);
        $revoked = $this->getJson('/api/v1/public/tickets/track/'.$token)->assertNotFound();
        $number = $this->getJson('/api/v1/public/tickets/track/'.Ticket::query()->value('ticket_number'))->assertNotFound();

        foreach ([$invalid, $expired, $revoked, $number] as $response) {
            $response->assertJsonPath('message', 'Resource not found')->assertJsonPath('error.code', 'NOT_FOUND');
            $this->assertTrackingHeaders($response);
        }
    }

    public function test_rotation_revokes_old_token_and_revoke_returns_no_secret(): void
    {
        [$oldToken] = $this->issue('rotate');
        $ticket = Ticket::query()->sole();
        $supervisor = $this->user('supervisor_it');

        $rotation = $this->actingAs($supervisor)->withHeader('Idempotency-Key', $this->operationKey('rotate'))->postJson(
            "/api/v1/supervisor-it/tickets/{$ticket->id}/public-tracking/rotate",
            ['reason' => 'Requester reported a disclosed link.'],
        )->assertOk();
        $newToken = basename($rotation->json('data.tracking_url'));
        $this->assertNotSame($oldToken, $newToken);
        $this->getJson('/api/v1/public/tickets/track/'.$oldToken)->assertNotFound();
        $this->getJson('/api/v1/public/tickets/track/'.$newToken)->assertOk();
        $this->assertSame(1, PublicTicketTrackingToken::query()->whereNull('revoked_at')->count());
        $this->assertDatabaseHas('ticket_status_histories', ['ticket_id' => $ticket->id, 'action' => 'public_tracking_rotated']);
        $this->assertTrackingHeaders($rotation);

        $revoke = $this->actingAs($supervisor)->withHeader('Idempotency-Key', $this->operationKey('revoke'))->postJson(
            "/api/v1/supervisor-it/tickets/{$ticket->id}/public-tracking/revoke",
            ['reason' => 'Tracking access is no longer needed.'],
        )->assertOk();
        $this->assertArrayNotHasKey('tracking_token', $revoke->json('data'));
        $this->getJson('/api/v1/public/tickets/track/'.$newToken)->assertNotFound();
        $this->assertTrackingHeaders($revoke);
    }

    public function test_supervisor_can_view_allowlisted_active_revoked_and_expired_status(): void
    {
        [$token] = $this->issue('status');
        $ticket = Ticket::query()->sole();
        $supervisor = $this->user('supervisor_it');
        $url = "/api/v1/supervisor-it/tickets/{$ticket->id}/public-tracking";

        $active = $this->actingAs($supervisor)->getJson($url)->assertOk()
            ->assertJsonPath('data.state', 'active')
            ->assertJsonPath('data.tracking_url', 'https://frontend.example.test/track/'.$token)
            ->assertJsonPath('data.can_rotate', true);
        $this->assertSame([
            'submission_source', 'state', 'created_at', 'expires_at', 'last_used_at', 'revoked_at',
            'tracking_url', 'link_recoverable', 'can_issue', 'can_rotate', 'can_revoke',
        ], array_keys($active->json('data')));
        foreach (['token_hash', 'derivation_nonce', 'key_version', 'secret', 'otp', 'identity_ciphertext'] as $sensitive) {
            $this->assertStringNotContainsString($sensitive, $active->getContent());
        }

        PublicTicketTrackingToken::query()->sole()->update(['expires_at' => now()->subSecond()]);
        $this->actingAs($supervisor)->getJson($url)->assertOk()
            ->assertJsonPath('data.state', 'expired')->assertJsonPath('data.tracking_url', null)
            ->assertJsonPath('data.can_issue', true);

        PublicTicketTrackingToken::query()->sole()->update(['expires_at' => null, 'revoked_at' => now()]);
        $this->actingAs($supervisor)->getJson($url)->assertOk()
            ->assertJsonPath('data.state', 'revoked')->assertJsonPath('data.tracking_url', null);
    }

    public function test_issue_after_revoke_creates_working_link_and_safe_audit(): void
    {
        [$oldToken] = $this->issue('reissue');
        $ticket = Ticket::query()->sole();
        $supervisor = $this->user('supervisor_it');
        PublicTicketTrackingToken::query()->update(['revoked_at' => now()]);
        $reason = 'Requester lost access '.str_repeat('a', 64).' https://unsafe.example/'.str_repeat('B', 43);

        $response = $this->actingAs($supervisor)->withHeader('Idempotency-Key', $this->operationKey('issue'))
            ->postJson("/api/v1/supervisor-it/tickets/{$ticket->id}/public-tracking", ['reason' => $reason])->assertOk();
        $newToken = basename($response->json('data.tracking_url'));

        $this->assertNotSame($oldToken, $newToken);
        $this->getJson('/api/v1/public/tickets/track/'.$oldToken)->assertNotFound();
        $this->getJson('/api/v1/public/tickets/track/'.$newToken)->assertOk();
        $history = $ticket->histories()->where('action', 'public_tracking_created')->reorder()->latest('id')->firstOrFail();
        $this->assertSame($supervisor->id, $history->actor_id);
        $this->assertStringNotContainsString(str_repeat('a', 64), $history->notes);
        $this->assertStringNotContainsString(str_repeat('B', 43), $history->notes);
        $this->assertStringNotContainsString('unsafe.example', $history->notes);
        $this->assertStringNotContainsString($newToken, json_encode($history->getAttributes(), JSON_THROW_ON_ERROR));
    }

    public function test_rotate_retry_is_idempotent_and_revoke_retry_does_not_duplicate_audit(): void
    {
        $this->issue('idempotent-management');
        $ticket = Ticket::query()->sole();
        $supervisor = $this->user('supervisor_it');
        $rotateUrl = "/api/v1/supervisor-it/tickets/{$ticket->id}/public-tracking/rotate";
        $key = $this->operationKey('same-rotation');
        $first = $this->actingAs($supervisor)->withHeader('Idempotency-Key', $key)
            ->postJson($rotateUrl, ['reason' => 'Requester requested a replacement.'])->assertOk();
        $second = $this->actingAs($supervisor)->withHeader('Idempotency-Key', $key)
            ->postJson($rotateUrl, ['reason' => 'Requester requested a replacement.'])->assertOk();

        $this->assertSame($first->json('data.tracking_url'), $second->json('data.tracking_url'));
        $this->assertSame(2, PublicTicketTrackingToken::query()->count());
        $this->assertSame(1, PublicTicketTrackingToken::query()->whereNull('revoked_at')->count());
        $this->assertSame(1, $ticket->histories()->where('action', 'public_tracking_rotated')->count());

        $revokeUrl = "/api/v1/supervisor-it/tickets/{$ticket->id}/public-tracking/revoke";
        $revokeKey = $this->operationKey('same-revoke');
        for ($attempt = 0; $attempt < 2; $attempt++) {
            $this->actingAs($supervisor)->withHeader('Idempotency-Key', $revokeKey)
                ->postJson($revokeUrl, ['reason' => 'Requester no longer needs access.'])->assertOk();
        }
        $this->assertSame(1, $ticket->histories()->where('action', 'public_tracking_revoked')->count());
    }

    public function test_management_requires_auth_permission_reason_and_public_ticket(): void
    {
        $this->issue('authorization');
        $ticket = Ticket::query()->sole();
        $url = "/api/v1/supervisor-it/tickets/{$ticket->id}/public-tracking/revoke";

        $this->postJson($url, ['reason' => 'No session'])->assertUnauthorized();
        $this->actingAs($this->user('requester'))->withHeader('Idempotency-Key', $this->operationKey('denied'))
            ->postJson($url, ['reason' => 'Not allowed'])->assertForbidden();
        $this->actingAs($this->user('pic'))->getJson(
            "/api/v1/supervisor-it/tickets/{$ticket->id}/public-tracking",
        )->assertForbidden();
        $this->actingAs($this->user('supervisor_it'))->postJson($url, [])->assertUnprocessable();

        $ticket->update(['submission_source' => 'authenticated_requester']);
        $this->actingAs($this->user('supervisor_it'))->withHeader('Idempotency-Key', $this->operationKey('internal'))
            ->postJson($url, ['reason' => 'Wrong ticket type'])->assertForbidden();
    }

    public function test_timeline_is_coarse_chronological_and_deduplicated_and_updates_are_filtered(): void
    {
        [$token] = $this->issue('timeline');
        $ticket = Ticket::query()->sole();
        $actor = $this->user('supervisor_it');
        foreach ([TicketStatus::Validated, TicketStatus::Analysis, TicketStatus::DevelopmentInProgress, TicketStatus::QaInProgress] as $status) {
            $ticket->histories()->create([
                'from_status' => TicketStatus::Submitted->value,
                'to_status' => $status->value,
                'action' => 'test_transition',
                'actor_id' => $actor->id,
                'actor_role' => 'supervisor_it',
                'notes' => 'internal timeline note',
                'metadata' => ['secret' => 'never expose'],
            ]);
        }
        $ticket->comments()->create(['user_id' => $actor->id, 'type' => 'info_request', 'comment' => '<b>Safe &amp; clear</b><script>bad()</script>', 'is_internal' => false]);
        $ticket->comments()->create(['user_id' => $actor->id, 'type' => 'work_note', 'comment' => 'nonallowlisted', 'is_internal' => false]);
        $ticket->comments()->create(['user_id' => $actor->id, 'type' => 'requester_summary', 'comment' => 'internal note', 'is_internal' => true]);

        $response = $this->getJson('/api/v1/public/tickets/track/'.$token)->assertOk();
        $this->assertSame(['received', 'under_review', 'work_in_progress', 'testing'], array_column($response->json('data.timeline'), 'code'));
        $response->assertJsonCount(1, 'data.requester_updates')
            ->assertJsonPath('data.requester_updates.0.message', 'Safe & clear')
            ->assertJsonStructure(['data' => ['requester_updates' => [['message', 'occurred_at']]]]);
        foreach (['internal timeline note', 'never expose', 'nonallowlisted', 'internal note', 'supervisor_it'] as $sensitive) {
            $this->assertStringNotContainsString($sensitive, $response->getContent());
        }
    }

    public function test_mapper_covers_every_enum_and_dynamic_values_without_exposing_raw_codes(): void
    {
        $mapper = app(PublicTicketStatusMapper::class);
        foreach (TicketStatus::cases() as $status) {
            $mapped = $mapper->map($status);
            $this->assertSame(['code', 'label', 'description'], array_keys($mapped));
            $this->assertNotSame($status->value, $mapped['code']);
        }
        $this->assertSame('processing', $mapper->map('confidential_custom_stage')['code']);
    }

    public function test_key_ring_reconstructs_old_tokens_after_active_version_changes(): void
    {
        config()->set('public_tracking.key', null);
        config()->set('public_tracking.keys', json_encode([
            '1' => str_repeat('a', 32),
            '2' => str_repeat('b', 32),
        ], JSON_THROW_ON_ERROR));
        config()->set('public_tracking.key_version', 1);
        [$oldToken] = $this->issue('key-ring-v1');
        $record = PublicTicketTrackingToken::query()->sole();

        config()->set('public_tracking.key_version', 2);
        $receipt = app(PublicTicketTrackingService::class)->receipt($record);
        $this->assertSame($oldToken, $receipt['raw_token']);

        $ticket = Ticket::query()->sole();
        $supervisor = $this->user('supervisor_it');
        $rotation = $this->actingAs($supervisor)->withHeader('Idempotency-Key', $this->operationKey('key-ring-v2'))
            ->postJson("/api/v1/supervisor-it/tickets/{$ticket->id}/public-tracking/rotate", [
                'reason' => 'Rotate to the current key version.',
            ])->assertOk();
        $this->assertSame(2, PublicTicketTrackingToken::query()->latest('generation')->value('key_version'));
        $this->getJson('/api/v1/public/tickets/track/'.basename($rotation->json('data.tracking_url')))->assertOk();
    }

    public function test_missing_historical_key_does_not_break_hash_lookup_but_prevents_reconstruction(): void
    {
        [$token] = $this->issue('missing-old-key');
        $ticket = Ticket::query()->sole();
        config()->set('public_tracking.key', null);
        config()->set('public_tracking.keys', json_encode(['2' => str_repeat('b', 32)], JSON_THROW_ON_ERROR));
        config()->set('public_tracking.key_version', 2);

        $this->getJson('/api/v1/public/tickets/track/'.$token)->assertOk();
        $status = app(PublicTicketTrackingService::class)->status($ticket);
        $this->assertSame('active', $status['state']);
        $this->assertNull($status['tracking_url']);
    }

    public function test_tracking_endpoint_has_a_named_rate_limit(): void
    {
        [$token] = $this->issue('rate-limit');
        for ($attempt = 0; $attempt < 30; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.90'])->getJson('/api/v1/public/tickets/track/'.$token)->assertOk();
        }
        $response = $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.90'])->getJson('/api/v1/public/tickets/track/'.$token)
            ->assertTooManyRequests()->assertJsonPath('error.code', 'TOO_MANY_ATTEMPTS');
        $this->assertTrackingHeaders($response);
    }

    /** @return array{string, TestResponse} */
    private function issue(string $key, array $changes = []): array
    {
        $response = $this->withHeader('Idempotency-Key', hash('sha256', $key))
            ->postJson('/api/v1/public/tickets', [...$this->payload(), ...$changes])
            ->assertCreated();

        return [$response->json('data.tracking_token'), $response];
    }

    private function user(string $role): User
    {
        return User::factory()->create([
            'role_id' => Role::query()->where('key', $role)->value('id'),
            'division_id' => $this->division->id,
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);
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
            'title' => 'Public tracking request',
            'description' => 'A sufficiently detailed public ticket description.',
            'urgency' => 'medium',
        ];
    }

    private function operationKey(string $value): string
    {
        return hash('sha256', 'public-tracking-management-'.$value);
    }

    private function assertTrackingHeaders($response): void
    {
        $response->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('Pragma', 'no-cache')
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }
}
