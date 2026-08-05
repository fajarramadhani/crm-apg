<?php

namespace Tests\Feature;

use App\Events\TicketSubmitted;
use App\Models\Application;
use App\Models\Branch;
use App\Models\Division;
use App\Models\PublicRequestHistoryAccessToken;
use App\Models\PublicRequestHistoryChallenge;
use App\Models\PublicTicketTrackingToken;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use App\Services\InMemoryPublicHistoryOtpDelivery;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class PublicRequestHistoryTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private Division $division;

    private Application $application;

    private TicketCategory $category;

    private InMemoryPublicHistoryOtpDelivery $delivery;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, MasterDataSeeder::class]);
        Event::fake([TicketSubmitted::class]);
        config()->set('public_history.driver', 'fake');
        config()->set('public_history.identity_key', str_repeat('i', 32));
        config()->set('public_history.otp_pepper', str_repeat('p', 32));
        config()->set('public_tracking.key', str_repeat('t', 32));
        config()->set('public_tracking.frontend_url', 'https://frontend.example.test');
        $this->delivery = app(InMemoryPublicHistoryOtpDelivery::class);
        $this->delivery->clear();

        $this->branch = Branch::query()->active()->firstOrFail();
        $this->division = Division::query()->active()->firstOrFail();
        $this->application = Application::query()->active()->firstOrFail();
        $this->application->update(['owner_division_id' => $this->division->id]);
        $this->category = TicketCategory::query()->active()->where('type', 'incident')->firstOrFail();
    }

    public function test_existing_and_nonexisting_requests_have_identical_contract_and_both_deliver_generated_codes(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $this->issueTicket('known@example.com', 'known-ticket');

        $known = $this->requestCode(' KNOWN@EXAMPLE.COM ')->assertAccepted();
        $unknown = $this->requestCode('unknown@example.com')->assertAccepted();

        $this->assertSame($known->json('message'), $unknown->json('message'));
        $this->assertSame(array_keys($known->json('data')), array_keys($unknown->json('data')));
        $this->assertCount(2, $this->delivery->deliveries());
        foreach ($this->delivery->deliveries() as $delivery) {
            $this->assertSame('email', $delivery['channel']);
            $this->assertMatchesRegularExpression('/\A\d{6}\z/', $delivery['code']);
        }
    }

    public function test_challenge_database_contains_no_plaintext_code_or_token_and_cooldown_is_generic(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $first = $this->requestCode('private@example.com')->assertAccepted();
        $token = $first->json('data.challenge_token');
        $code = $this->delivery->deliveries()[0]['code'];
        $stored = PublicRequestHistoryChallenge::query()->sole();
        $serialized = json_encode($stored->getAttributes(), JSON_THROW_ON_ERROR);

        $this->assertSame(hash('sha256', $token), $stored->challenge_token_hash);
        $this->assertStringNotContainsString($token, $serialized);
        $this->assertStringNotContainsString($code, $serialized);
        $second = $this->requestCode('private@example.com')->assertAccepted();
        $this->assertSame($first->json('message'), $second->json('message'));
        $this->assertSame($token, $second->json('data.challenge_token'));
        $this->assertSame($first->json('data.expires_at'), $second->json('data.expires_at'));
        $this->assertCount(1, $this->delivery->deliveries());
        $this->assertDatabaseCount('public_request_history_challenges', 1);
    }

    public function test_correct_code_consumes_once_and_access_token_is_hash_only_with_absolute_expiry(): void
    {
        [$challenge, $code] = $this->challenge('person@example.com');
        $response = $this->verifyCode($challenge, $code)->assertOk();
        $accessToken = $response->json('data.access_token');
        $access = PublicRequestHistoryAccessToken::query()->sole();

        $this->assertSame(hash('sha256', $accessToken), $access->token_hash);
        $this->assertStringNotContainsString($accessToken, json_encode($access->getAttributes(), JSON_THROW_ON_ERROR));
        $this->assertNotNull(PublicRequestHistoryChallenge::query()->sole()->consumed_at);
        $this->verifyCode($challenge, $code)
            ->assertUnprocessable()->assertJsonPath('error.code', 'VERIFICATION_FAILED');
        $this->assertDatabaseCount('public_request_history_access_tokens', 1);
    }

    public function test_wrong_expired_unknown_and_exhausted_codes_share_safe_failure_and_attempts_are_authoritative(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        config()->set('public_history.max_attempts', 2);
        [$challenge] = $this->challenge('attempts@example.com');
        foreach (['111111', '222222', '333333'] as $code) {
            $this->verifyCode($challenge, $code)
                ->assertUnprocessable()->assertJsonPath('message', 'The verification code is invalid or no longer available.');
        }
        $this->assertSame(0, PublicRequestHistoryChallenge::query()->sole()->attempts_remaining);

        PublicRequestHistoryChallenge::query()->sole()->update(['expires_at' => now()->subSecond(), 'attempts_remaining' => 2]);
        $this->verifyCode($challenge, '000000')->assertUnprocessable();
        $this->verifyCode(str_repeat('A', 43), '000000')->assertUnprocessable();
    }

    public function test_history_is_exact_scoped_paginated_dto_and_empty_state_has_no_leaks(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $this->issueTicket('owner@example.com', 'owner-1');
        $this->issueTicket('other@example.com', 'other-1');
        Ticket::query()->where('requester_email', 'owner@example.com')->firstOrFail()->replicate()->fill([
            'ticket_number' => 'AUTH-SOURCE-1', 'submission_source' => 'authenticated_requester', 'requester_id' => null,
        ])->save();

        $access = $this->accessToken('owner@example.com');
        $response = $this->withToken($access)->getJson('/api/v1/public/ticket-history')->assertOk()->assertJsonCount(1, 'data');
        $this->assertSame(['ticket_number', 'title', 'branch', 'category', 'submitted_at', 'status', 'last_updated_at'], array_keys($response->json('data.0')));
        foreach (['requester_email', 'requester_id', 'tracking_token', 'actor', 'metadata'] as $field) {
            $this->assertStringNotContainsString('"'.$field.'"', $response->getContent());
        }
        $this->assertNull(Ticket::query()->where('requester_email', 'owner@example.com')->where('submission_source', 'public_form')->value('requester_id'));
        $this->withToken($access)->getJson('/api/v1/public/ticket-history?branch_id=999')->assertUnprocessable();

        $empty = $this->accessToken('empty@example.com');
        $this->withToken($empty)->getJson('/api/v1/public/ticket-history')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_access_expiry_cross_requester_and_cross_branch_are_isolated(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $this->issueTicket('owner@example.com', 'isolated');
        $other = $this->accessToken('other@example.com');
        $this->withToken($other)->getJson('/api/v1/public/ticket-history')->assertOk()->assertJsonCount(0, 'data');

        PublicRequestHistoryAccessToken::query()->latest('id')->firstOrFail()->update(['expires_at' => now()->subSecond()]);
        $this->withToken($other)->getJson('/api/v1/public/ticket-history')->assertUnauthorized()->assertJsonPath('error.code', 'INVALID_ACCESS');

        $otherBranch = Branch::query()->active()->whereKeyNot($this->branch->id)->first();
        if ($otherBranch) {
            $branchAccess = $this->accessToken('owner@example.com', $otherBranch->id);
            $this->withToken($branchAccess)->getJson('/api/v1/public/ticket-history')->assertOk()->assertJsonCount(0, 'data');
        }
    }

    public function test_history_access_can_be_revoked_explicitly(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $access = $this->accessToken('revoke@example.com');

        $this->withToken($access)->postJson('/api/v1/public/ticket-history/revoke')->assertOk();
        $this->withToken($access)->getJson('/api/v1/public/ticket-history')
            ->assertUnauthorized()->assertJsonPath('error.code', 'INVALID_ACCESS');
    }

    public function test_history_access_token_cannot_manage_tracking(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $issued = $this->issueTicket('read-only@example.com', 'history-read-only');
        $ticket = Ticket::query()->where('ticket_number', $issued->json('data.ticket_number'))->firstOrFail();
        $access = $this->accessToken('read-only@example.com');

        $this->withToken($access)
            ->withHeader('Idempotency-Key', hash('sha256', 'history-cannot-rotate'))
            ->postJson("/api/v1/supervisor-it/tickets/{$ticket->id}/public-tracking/rotate", [
                'reason' => 'This session is read-only.',
            ])->assertUnauthorized();
    }

    public function test_tracking_link_requires_ownership_and_active_stage_two_token(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $issued = $this->issueTicket('owner@example.com', 'tracking-owner');
        $number = $issued->json('data.ticket_number');
        $trackingToken = $issued->json('data.tracking_token');
        $owner = $this->accessToken('owner@example.com');

        $this->withToken($owner)->postJson("/api/v1/public/ticket-history/tickets/{$number}/tracking-link")
            ->assertOk()->assertJsonPath('data.tracking_path', '/track/'.$trackingToken)
            ->assertJsonMissingPath('data.tracking_token');
        $other = $this->accessToken('other@example.com');
        $this->withToken($other)->postJson("/api/v1/public/ticket-history/tickets/{$number}/tracking-link")->assertNotFound();

        PublicTicketTrackingToken::query()->where('ticket_id', Ticket::query()->where('ticket_number', $number)->value('id'))->update(['revoked_at' => now()]);
        $this->withToken($owner)->postJson("/api/v1/public/ticket-history/tickets/{$number}/tracking-link")->assertNotFound();

        PublicTicketTrackingToken::query()->update(['revoked_at' => null, 'expires_at' => now()->subSecond()]);
        $this->withToken($owner)->postJson("/api/v1/public/ticket-history/tickets/{$number}/tracking-link")->assertNotFound();
    }

    public function test_history_returns_only_the_latest_active_link_after_rotation(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $issued = $this->issueTicket('rotate-owner@example.com', 'history-rotation');
        $oldToken = $issued->json('data.tracking_token');
        $ticket = Ticket::query()->where('ticket_number', $issued->json('data.ticket_number'))->firstOrFail();
        $supervisor = User::factory()->create([
            'role_id' => Role::query()->where('key', 'supervisor_it')->value('id'),
            'division_id' => $this->division->id,
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);
        $rotation = $this->actingAs($supervisor)
            ->withHeader('Idempotency-Key', hash('sha256', 'history-stage-four-rotation'))
            ->postJson("/api/v1/supervisor-it/tickets/{$ticket->id}/public-tracking/rotate", [
                'reason' => 'Requester needs a replacement link.',
            ])->assertOk();
        $newPath = parse_url($rotation->json('data.tracking_url'), PHP_URL_PATH);
        $access = $this->accessToken('rotate-owner@example.com');

        $this->withToken($access)
            ->postJson("/api/v1/public/ticket-history/tickets/{$ticket->ticket_number}/tracking-link")
            ->assertOk()->assertJsonPath('data.tracking_path', $newPath);
        $this->getJson('/api/v1/public/tickets/track/'.$oldToken)->assertNotFound();
    }

    public function test_challenge_endpoint_has_layered_generic_rate_limiting(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->requestCode('limited@example.com')->assertAccepted();
        }

        $this->requestCode('limited@example.com')->assertTooManyRequests()
            ->assertJsonPath('message', 'Too many requests. Please try again later.')
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_all_endpoints_apply_no_store_headers_and_cleanup_has_no_sensitive_output(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        [$challenge, $code] = $this->challenge('headers@example.com');
        $verify = $this->verifyCode($challenge, $code)->assertOk();
        $access = $verify->json('data.access_token');
        $history = $this->withToken($access)->getJson('/api/v1/public/ticket-history')->assertOk();
        foreach ([$history, $verify] as $response) {
            $response->assertHeader('Cache-Control', 'no-store, private')->assertHeader('Pragma', 'no-cache')->assertHeader('Referrer-Policy', 'no-referrer');
        }
        PublicRequestHistoryChallenge::query()->update(['expires_at' => now()->subSecond()]);
        PublicRequestHistoryAccessToken::query()->update(['expires_at' => now()->subSecond()]);
        $this->artisan('public-history:cleanup')->expectsOutput('Expired public request history credentials were cleaned up.')->assertSuccessful();
        $this->assertDatabaseCount('public_request_history_challenges', 0);
        $this->assertDatabaseCount('public_request_history_access_tokens', 0);
    }

    private function requestCode(string $email, ?int $branchId = null)
    {
        return $this->postJson('/api/v1/public/ticket-history/challenges', [
            'identity_type' => 'email', 'email' => $email, 'branch_id' => $branchId ?? $this->branch->id,
        ]);
    }

    private function challenge(string $email, ?int $branchId = null): array
    {
        $response = $this->requestCode($email, $branchId)->assertAccepted();
        $deliveries = $this->delivery->deliveries();
        $delivery = end($deliveries);

        return [$response->json('data.challenge_token'), $delivery['code']];
    }

    private function accessToken(string $email, ?int $branchId = null): string
    {
        [$challenge, $code] = $this->challenge($email, $branchId);

        return $this->verifyCode($challenge, $code)
            ->assertOk()->json('data.access_token');
    }

    private function verifyCode(string $challenge, string $code)
    {
        return $this->postJson('/api/v1/public/ticket-history/verify', [
            'challenge_token' => $challenge,
            'code' => $code,
        ]);
    }

    private function issueTicket(string $email, string $key)
    {
        return $this->withHeader('Idempotency-Key', hash('sha256', $key))->postJson('/api/v1/public/tickets', [
            'requester_name' => 'Public Requester', 'requester_email' => $email,
            'branch_id' => $this->branch->id, 'division_id' => $this->division->id,
            'ticket_category_id' => $this->category->id, 'application_id' => $this->application->id,
            'title' => 'Public request '.$key, 'description' => 'A detailed request suitable for public submission.', 'urgency' => 'medium',
        ])->assertCreated();
    }
}
