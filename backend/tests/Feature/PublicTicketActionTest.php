<?php

namespace Tests\Feature;

use App\Enums\TicketStatus;
use App\Events\PublicRequesterActionCompleted;
use App\Events\TicketSubmitted;
use App\Models\Application;
use App\Models\Branch;
use App\Models\Division;
use App\Models\PublicTicketActionAccessToken;
use App\Models\PublicTicketActionChallenge;
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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicTicketActionTest extends TestCase
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
        $this->withoutMiddleware(ThrottleRequests::class);
        Event::fake([TicketSubmitted::class, PublicRequesterActionCompleted::class]);
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

    public function test_available_action_matrix_is_allowlisted_and_tracking_resource_has_indicator(): void
    {
        [$ticket, $token] = $this->ticket(TicketStatus::ReadyForUat);

        $response = $this->getJson($this->url($token, '/actions'))->assertOk()->assertJsonPath('data.type', 'uat');
        $this->assertSame(['type', 'label', 'current_public_status', 'verification_required', 'notes_required', 'attachment_constraints'], array_keys($response->json('data')));
        $this->getJson($this->url($token))->assertOk()->assertJsonPath('data.action_available', true);

        $ticket->update(['status' => TicketStatus::AwaitingRequesterConfirmation]);
        $this->getJson($this->url($token, '/actions'))->assertJsonPath('data.type', 'confirmation')->assertJsonPath('data.attachment_constraints', null);
        $ticket->update(['status' => TicketStatus::DevelopmentInProgress]);
        $this->getJson($this->url($token, '/actions'))->assertJsonPath('data.type', null);
        $ticket->update(['submission_source' => 'authenticated_requester', 'requester_id' => $this->user()->id]);
        $this->getJson($this->url($token, '/actions'))->assertJsonPath('data.type', null);
    }

    public function test_challenge_is_generic_and_only_exact_normalized_requester_receives_otp(): void
    {
        [, $token] = $this->ticket(TicketStatus::ReadyForUat);
        $known = $this->challengeRequest($token, 'uat', ' PUBLIC@EXAMPLE.COM ')->assertAccepted();
        $unknown = $this->challengeRequest($token, 'uat', 'other@example.com')->assertAccepted();

        $this->assertSame($known->json('message'), $unknown->json('message'));
        $this->assertSame(array_keys($known->json('data')), array_keys($unknown->json('data')));
        $this->assertCount(1, $this->delivery->deliveries());
        $stored = PublicTicketActionChallenge::query()->firstOrFail();
        $serialized = json_encode($stored->getAttributes(), JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString($known->json('data.challenge_token'), $serialized);
        $this->assertStringNotContainsString($this->delivery->deliveries()[0]['code'], $serialized);
    }

    public function test_verify_failures_are_generic_and_access_is_hash_only_and_scoped(): void
    {
        [$ticket, $token] = $this->ticket(TicketStatus::ReadyForUat);
        [$challenge, $code] = $this->challenge($token, 'uat');
        $this->postJson($this->url($token, '/actions/verify'), ['action' => 'uat', 'challenge_token' => $challenge, 'code' => '000000'])
            ->assertUnprocessable()->assertJsonPath('error.code', 'VERIFICATION_FAILED');
        $access = $this->verify($token, 'uat', $challenge, $code);
        $record = PublicTicketActionAccessToken::query()->sole();
        $this->assertSame(hash('sha256', $access), $record->token_hash);
        $this->assertStringNotContainsString($access, json_encode($record->getAttributes(), JSON_THROW_ON_ERROR));

        [$otherTicket, $otherToken] = $this->ticket(TicketStatus::ReadyForUat, 'other@example.com');
        $this->withHeader('X-Public-Action-Token', $access)->withHeader('Idempotency-Key', hash('sha256', 'cross-ticket'))
            ->postJson($this->url($otherToken, '/uat'), ['outcome' => 'accepted'])->assertUnauthorized();
        $this->assertSame(TicketStatus::ReadyForUat, $otherTicket->fresh()->status);
        $this->assertSame(TicketStatus::ReadyForUat, $ticket->fresh()->status);
    }

    public function test_uat_rejection_sanitizes_feedback_caps_progress_and_replay_is_idempotent(): void
    {
        Storage::fake('local');
        [$ticket, $token] = $this->ticket(TicketStatus::UatRetest);
        $ticket->update(['progress_percentage' => 100]);
        $access = $this->access($token, 'uat');
        $key = hash('sha256', 'uat-rejection');
        $payload = ['outcome' => 'rejected', 'notes' => '<script>alert(1)</script><b>Needs correction</b>'];

        $first = $this->withHeader('X-Public-Action-Token', $access)->withHeader('Idempotency-Key', $key)
            ->postJson($this->url($token, '/uat'), $payload)->assertOk();
        $second = $this->withHeader('X-Public-Action-Token', $access)->withHeader('Idempotency-Key', $key)
            ->postJson($this->url($token, '/uat'), $payload)->assertOk();

        $this->assertSame($first->json('data'), $second->json('data'));
        $this->assertSame(TicketStatus::DevelopmentInProgress, $ticket->fresh()->status);
        $this->assertSame(90, $ticket->fresh()->progress_percentage);
        $this->assertDatabaseHas('ticket_comments', ['ticket_id' => $ticket->id, 'user_id' => null, 'type' => 'uat_feedback', 'comment' => 'Needs correction', 'is_internal' => false]);
        $this->assertSame(1, $ticket->comments()->where('type', 'uat_feedback')->count());
        $this->assertSame(1, $ticket->histories()->where('action', 'uat_failed')->count());
        $history = $ticket->histories()->where('action', 'uat_failed')->firstOrFail();
        $this->assertNull($history->actor_id);
        $this->assertSame('public_requester', $history->actor_role);
        Event::assertDispatchedTimes(PublicRequesterActionCompleted::class, 1);

        $this->getJson($this->url($token))->assertOk()->assertJsonFragment(['message' => 'Needs correction']);
    }

    public function test_uat_acceptance_stores_private_evidence_once_and_payload_conflict_is_safe(): void
    {
        Storage::fake('local');
        [$ticket, $token] = $this->ticket(TicketStatus::ReadyForUat);
        $access = $this->access($token, 'uat');
        $key = hash('sha256', 'uat-accepted-file');

        $response = $this->withHeader('X-Public-Action-Token', $access)->withHeader('Idempotency-Key', $key)->post($this->url($token, '/uat'), [
            'outcome' => 'accepted', 'notes' => 'Verified successfully',
            'files' => [UploadedFile::fake()->create('proof.txt', 10, 'text/plain')],
        ], ['Accept' => 'application/json'])->assertOk();
        $this->assertSame('finalizing', $response->json('data.status.code'));
        $this->assertSame(TicketStatus::UatApproved, $ticket->fresh()->status);
        $attachment = $ticket->attachments()->sole();
        $this->assertNull($attachment->uploaded_by);
        $this->assertSame('uat_evidence', $attachment->category);
        $this->assertSame('requester', $attachment->visibility);
        $this->assertNotSame('proof.txt', $attachment->stored_name);
        Storage::disk('local')->assertExists($attachment->path);

        $this->withHeader('X-Public-Action-Token', $access)->withHeader('Idempotency-Key', $key)
            ->postJson($this->url($token, '/uat'), ['outcome' => 'rejected', 'notes' => 'different'])
            ->assertConflict()->assertJsonPath('error.code', 'IDEMPOTENCY_CONFLICT');
        $this->assertSame(1, $ticket->attachments()->count());
    }

    public function test_confirmation_acceptance_and_rejection_use_domain_semantics(): void
    {
        [$accepted, $acceptedToken] = $this->confirmationTicket();
        $acceptedAccess = $this->access($acceptedToken, 'confirmation');
        $this->mutate($acceptedToken, $acceptedAccess, 'confirmation', ['outcome' => 'accepted', 'notes' => 'Looks good'], 'confirm-accepted')->assertOk();
        $this->assertSame(TicketStatus::AwaitingRequesterConfirmation, $accepted->fresh()->status);
        $this->assertDatabaseHas('ticket_requester_confirmations', ['ticket_id' => $accepted->id, 'requester_id' => null, 'status' => 'accepted']);
        $this->getJson($this->url($acceptedToken, '/actions'))->assertOk()->assertJsonPath('data.type', null);
        $deliveryCount = count($this->delivery->deliveries());
        $this->challengeRequest($acceptedToken, 'confirmation', 'public@example.com')->assertAccepted();
        $this->assertCount($deliveryCount, $this->delivery->deliveries());

        [$rejected, $rejectedToken] = $this->confirmationTicket('rejected@example.com');
        $rejectedAccess = $this->access($rejectedToken, 'confirmation', 'rejected@example.com');
        $this->mutate($rejectedToken, $rejectedAccess, 'confirmation', ['outcome' => 'rejected', 'notes' => '<b>Issue remains</b>'], 'confirm-rejected')->assertOk();
        $this->assertSame(TicketStatus::DevelopmentInProgress, $rejected->fresh()->status);
        $this->assertDatabaseHas('ticket_comments', ['ticket_id' => $rejected->id, 'type' => 'confirmation_feedback', 'comment' => 'Issue remains']);
        $this->assertDatabaseHas('ticket_status_histories', ['ticket_id' => $rejected->id, 'action' => 'requester_rejected', 'actor_id' => null, 'actor_role' => 'public_requester']);
    }

    public function test_status_change_rotation_revoke_expiry_and_history_token_prevent_mutation(): void
    {
        [$ticket, $token] = $this->ticket(TicketStatus::ReadyForUat);
        $access = $this->access($token, 'uat');
        $ticket->update(['status' => TicketStatus::DevelopmentInProgress]);
        $this->mutate($token, $access, 'uat', ['outcome' => 'accepted'], 'status-change')->assertUnauthorized();

        $ticket->update(['status' => TicketStatus::ReadyForUat]);
        PublicTicketActionAccessToken::query()->where('token_hash', hash('sha256', $access))->update(['expires_at' => now()->subSecond()]);
        $this->mutate($token, $access, 'uat', ['outcome' => 'accepted'], 'expired')->assertUnauthorized();

        $access = $this->access($token, 'uat');
        PublicTicketTrackingToken::query()->where('token_hash', hash('sha256', $token))->update(['revoked_at' => now()]);
        $this->mutate($token, $access, 'uat', ['outcome' => 'accepted'], 'revoked')->assertNotFound();

        $historyToken = str_repeat('H', 43);
        PublicTicketTrackingToken::query()->where('token_hash', hash('sha256', $token))->update(['revoked_at' => null]);
        $this->withHeader('X-Public-Action-Token', '')->withToken($historyToken)->withHeader('Idempotency-Key', hash('sha256', 'history-token'))
            ->postJson($this->url($token, '/uat'), ['outcome' => 'accepted'])->assertUnauthorized();
    }

    public function test_validation_cleanup_and_security_headers(): void
    {
        [, $token] = $this->ticket(TicketStatus::ReadyForUat);
        $access = $this->access($token, 'uat');
        $this->withHeader('X-Public-Action-Token', $access)->postJson($this->url($token, '/uat'), ['outcome' => 'rejected'])
            ->assertUnprocessable()->assertHeader('Cache-Control', 'no-store, private');

        PublicTicketActionChallenge::query()->update(['expires_at' => now()->subSecond()]);
        PublicTicketActionAccessToken::query()->update(['expires_at' => now()->subSecond()]);
        $this->artisan('public-actions:cleanup')->expectsOutput('Expired public ticket action credentials were cleaned up.')->assertSuccessful();
        $this->assertDatabaseCount('public_ticket_action_challenges', 0);
        $this->assertDatabaseCount('public_ticket_action_access_tokens', 0);
    }

    public function test_cleanup_is_idempotent_and_preserves_active_credentials(): void
    {
        [, $token] = $this->ticket(TicketStatus::ReadyForUat);
        $this->access($token, 'uat');
        $activeChallenge = PublicTicketActionChallenge::query()->latest('id')->firstOrFail();
        $activeAccess = PublicTicketActionAccessToken::query()->latest('id')->firstOrFail();
        PublicTicketActionChallenge::query()->create([
            ...$activeChallenge->only([
                'ticket_id', 'public_tracking_token_id', 'branch_id', 'identity_hash', 'identity_ciphertext',
                'action', 'status_hash', 'otp_hash', 'attempts_remaining', 'resend_available_at', 'sent_at',
            ]),
            'derivation_nonce' => str_repeat('c', 64),
            'challenge_token_hash' => str_repeat('d', 64),
            'expires_at' => now()->subSecond(),
        ]);
        PublicTicketActionAccessToken::query()->create([
            ...$activeAccess->only(['ticket_id', 'public_tracking_token_id', 'branch_id', 'identity_hash', 'action', 'status_hash']),
            'token_hash' => str_repeat('e', 64),
            'expires_at' => now()->subSecond(),
        ]);

        $this->artisan('public-actions:cleanup')->assertSuccessful();
        $this->artisan('public-actions:cleanup')->assertSuccessful();
        $this->assertDatabaseHas('public_ticket_action_challenges', ['id' => $activeChallenge->id]);
        $this->assertDatabaseHas('public_ticket_action_access_tokens', ['id' => $activeAccess->id]);
        $this->assertDatabaseCount('public_ticket_action_challenges', 1);
        $this->assertDatabaseCount('public_ticket_action_access_tokens', 1);
    }

    public function test_executable_uat_evidence_is_rejected_without_storage_or_database_rows(): void
    {
        Storage::fake('local');
        [$ticket, $token] = $this->ticket(TicketStatus::ReadyForUat);
        $access = $this->access($token, 'uat');

        $this->withHeader('X-Public-Action-Token', $access)
            ->withHeader('Idempotency-Key', hash('sha256', 'executable-evidence'))
            ->post($this->url($token, '/uat'), [
                'outcome' => 'accepted',
                'files' => [UploadedFile::fake()->create('payload.php', 5, 'application/x-php')],
            ], ['Accept' => 'application/json'])->assertUnprocessable();
        $this->assertSame(TicketStatus::ReadyForUat, $ticket->fresh()->status);
        $this->assertDatabaseCount('ticket_attachments', 0);
        Storage::disk('local')->assertDirectoryEmpty('/');
    }

    private function ticket(TicketStatus $status, string $email = 'public@example.com'): array
    {
        $response = $this->withHeader('Idempotency-Key', hash('sha256', $email.'-'.$status->value.microtime(true)))
            ->postJson('/api/v1/public/tickets', [
                'requester_name' => 'Public Requester', 'requester_email' => $email,
                'branch_id' => $this->branch->id, 'division_id' => $this->division->id,
                'ticket_category_id' => $this->category->id, 'application_id' => $this->application->id,
                'title' => 'Public action request', 'description' => 'A sufficiently detailed public action request.', 'urgency' => 'medium',
            ])->assertCreated();
        $ticket = Ticket::query()->where('ticket_number', $response->json('data.ticket_number'))->firstOrFail();
        $ticket->update(['status' => $status]);

        return [$ticket, $response->json('data.tracking_token')];
    }

    private function confirmationTicket(string $email = 'public@example.com'): array
    {
        [$ticket, $token] = $this->ticket(TicketStatus::AwaitingRequesterConfirmation, $email);
        $actor = $this->user();
        $ticket->update(['release_owner_id' => $actor->id]);

        return [$ticket, $token];
    }

    private function user(): User
    {
        return User::factory()->create([
            'role_id' => Role::query()->where('key', 'supervisor_it')->value('id'),
            'division_id' => $this->division->id, 'branch_id' => $this->branch->id, 'is_active' => true,
        ]);
    }

    private function challengeRequest(string $token, string $action, string $email)
    {
        return $this->postJson($this->url($token, '/actions/challenge'), ['action' => $action, 'email' => $email]);
    }

    private function challenge(string $token, string $action, string $email = 'public@example.com'): array
    {
        $response = $this->challengeRequest($token, $action, $email)->assertAccepted();
        $delivery = $this->delivery->deliveries();

        return [$response->json('data.challenge_token'), end($delivery)['code']];
    }

    private function verify(string $token, string $action, string $challenge, string $code): string
    {
        return $this->postJson($this->url($token, '/actions/verify'), compact('action', 'challenge') + ['challenge_token' => $challenge, 'code' => $code])
            ->assertOk()->json('data.access_token');
    }

    private function access(string $token, string $action, string $email = 'public@example.com'): string
    {
        [$challenge, $code] = $this->challenge($token, $action, $email);

        return $this->verify($token, $action, $challenge, $code);
    }

    private function mutate(string $token, string $access, string $action, array $payload, string $key)
    {
        return $this->withHeader('X-Public-Action-Token', $access)->withHeader('Idempotency-Key', hash('sha256', $key))
            ->postJson($this->url($token, '/'.$action), $payload);
    }

    private function url(string $token, string $suffix = ''): string
    {
        return '/api/v1/public/tickets/track/'.$token.$suffix;
    }
}
