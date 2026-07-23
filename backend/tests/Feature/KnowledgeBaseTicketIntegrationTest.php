<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\KnowledgeBaseTag;
use App\Models\Ticket;
use App\Models\TicketCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Feature\Concerns\InteractsWithKnowledgeBase;
use Tests\TestCase;

class KnowledgeBaseTicketIntegrationTest extends TestCase
{
    use InteractsWithKnowledgeBase;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpKnowledgeBase();
    }

    public function test_pic_can_link_idempotently_list_and_unlink_article(): void
    {
        $ticket = $this->accessibleTicket();
        $article = $this->createKbArticle(['status' => 'published']);
        $payload = ['article_id' => $article->id, 'relation_type' => 'used_as_solution'];

        $this->actingAs($this->kbUser('pic'))->postJson("/api/v1/tickets/{$ticket->id}/knowledge-base/link", $payload)->assertOk();
        $this->actingAs($this->kbUser('pic'))->postJson("/api/v1/tickets/{$ticket->id}/knowledge-base/link", $payload)->assertOk();
        $this->assertDatabaseCount('knowledge_base_article_ticket', 1);
        $this->actingAs($this->kbUser('pic'))->getJson("/api/v1/tickets/{$ticket->id}/knowledge-base")
            ->assertOk()->assertJsonFragment(['id' => $article->id]);
        $this->actingAs($this->kbUser('pic'))->deleteJson("/api/v1/tickets/{$ticket->id}/knowledge-base/{$article->id}")->assertOk();
        $this->assertDatabaseMissing('knowledge_base_article_ticket', ['ticket_id' => $ticket->id, 'article_id' => $article->id]);
    }

    public function test_ticket_links_obey_article_visibility(): void
    {
        $ticket = $this->accessibleTicket();
        $internal = $this->createKbArticle(['visibility' => 'it_internal', 'status' => 'published']);
        $this->actingAs($this->kbUser('pic'))->postJson("/api/v1/tickets/{$ticket->id}/knowledge-base/link", [
            'article_id' => $internal->id,
            'relation_type' => 'related',
        ])->assertOk();

        $requesterResponse = $this->actingAs($this->kbUser('requester'))
            ->getJson("/api/v1/tickets/{$ticket->id}/knowledge-base")->assertOk();
        $this->assertNotContains($internal->id, array_column($requesterResponse->json('data'), 'id'));
        $this->actingAs($this->kbUser('pic'))->getJson("/api/v1/tickets/{$ticket->id}/knowledge-base")
            ->assertOk()->assertJsonFragment(['id' => $internal->id]);
    }

    public function test_create_draft_requires_resolution(): void
    {
        $ticket = $this->accessibleTicket([
            'title' => 'Customer VPN Failure',
            'description' => 'Contact jane@example.com or +1 415 555 1234. password=supersecret',
        ]);
        $this->actingAs($this->kbUser('pic'))->postJson("/api/v1/tickets/{$ticket->id}/knowledge-base/create-draft")
            ->assertConflict();
    }

    public function test_create_draft_redacts_ticket_secrets_and_pii_and_links_source(): void
    {
        $ticket = $this->accessibleTicket([
            'title' => 'Customer VPN Failure',
            'description' => 'Contact jane@example.com or +1 415 555 1234. password=supersecret',
            'closed_at' => now(),
        ]);
        $data = $this->actingAs($this->kbUser('pic'))->postJson("/api/v1/tickets/{$ticket->id}/knowledge-base/create-draft", [
            'visibility' => 'it_internal',
        ])->assertCreated()->json('data');
        $this->assertSame('draft', $data['status']);
        $this->assertStringContainsString('[REDACTED_EMAIL]', $data['summary']);
        $this->assertStringContainsString('[REDACTED_PHONE]', $data['summary']);
        $this->assertStringContainsString('password=[REDACTED]', $data['summary']);
        $this->assertDatabaseHas('knowledge_base_article_ticket', [
            'article_id' => $data['id'], 'ticket_id' => $ticket->id, 'relation_type' => 'source',
        ]);
    }

    public function test_recommendations_are_relevant_visibility_safe_and_deterministic(): void
    {
        Carbon::setTestNow('2026-07-22 09:00:00');
        $category = TicketCategory::where('code', 'INCIDENT')->firstOrFail();
        $application = Application::where('code', 'TIC_HUB')->firstOrFail();
        $tag = KnowledgeBaseTag::create(['name' => 'vpn', 'slug' => 'vpn', 'is_active' => true]);
        $ticket = $this->accessibleTicket([
            'title' => 'VPN timeout failure',
            'ticket_category_id' => $category->id,
            'application_id' => $application->id,
        ]);
        $best = $this->createKbArticle([
            'title' => 'VPN timeout fix', 'summary' => 'Resolve VPN failure', 'category_id' => $category->id,
            'application_id' => $application->id, 'tags' => [$tag->id], 'status' => 'published',
        ]);
        $this->createKbArticle(['title' => 'Unrelated payroll', 'status' => 'published']);
        $internal = $this->createKbArticle([
            'title' => 'VPN internal timeout', 'visibility' => 'it_internal', 'category_id' => $category->id,
            'application_id' => $application->id, 'status' => 'published',
        ]);

        $url = "/api/v1/tickets/{$ticket->id}/knowledge-base/recommendations";
        $first = $this->actingAs($this->kbUser('requester'))->getJson($url)->assertOk()->json('data');
        $second = $this->actingAs($this->kbUser('requester'))->getJson($url)->assertOk()->json('data');
        $this->assertSame(array_column($first, 'id'), array_column($second, 'id'));
        $this->assertSame($best->id, $first[0]['id']);
        $this->assertNotContains($internal->id, array_column($first, 'id'));

        $picIds = array_column($this->actingAs($this->kbUser('pic'))->getJson($url)->assertOk()->json('data'), 'id');
        $this->assertContains($internal->id, $picIds);
    }

    public function test_ticket_knowledge_base_actions_require_ticket_access(): void
    {
        $ticket = Ticket::factory()->create();
        $article = $this->createKbArticle(['status' => 'published']);
        $pic = $this->kbUser('pic');

        $this->actingAs($pic)->getJson("/api/v1/tickets/{$ticket->id}/knowledge-base")->assertForbidden();
        $this->actingAs($pic)->getJson("/api/v1/tickets/{$ticket->id}/knowledge-base/recommendations")->assertForbidden();
        $this->actingAs($pic)->postJson("/api/v1/tickets/{$ticket->id}/knowledge-base/link", [
            'article_id' => $article->id,
            'relation_type' => 'related',
        ])->assertForbidden();
        $this->actingAs($pic)->deleteJson("/api/v1/tickets/{$ticket->id}/knowledge-base/{$article->id}")->assertForbidden();
        $this->actingAs($pic)->postJson("/api/v1/tickets/{$ticket->id}/knowledge-base/create-draft")->assertForbidden();
    }

    private function accessibleTicket(array $attributes = []): Ticket
    {
        return Ticket::factory()->create(array_merge([
            'requester_id' => $this->kbUser('requester')->id,
            'current_assignee_id' => $this->kbUser('pic')->id,
        ], $attributes));
    }
}
