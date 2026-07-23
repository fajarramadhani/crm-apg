<?php

namespace Tests\Feature;

use App\Services\KnowledgeBaseNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\InteractsWithKnowledgeBase;
use Tests\TestCase;

class KnowledgeBaseFeedbackNotificationAuditTest extends TestCase
{
    use InteractsWithKnowledgeBase;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpKnowledgeBase();
    }

    public function test_feedback_is_upserted_and_counts_and_ratio_remain_accurate(): void
    {
        $article = $this->createKbArticle(['status' => 'published']);
        $this->actingAs($this->kbUser('requester'))->postJson("/api/v1/knowledge-base/{$article->id}/feedback", [
            'is_helpful' => true,
            'comment' => '<b>Useful</b>',
        ])->assertCreated()->assertJsonPath('data.comment', 'Useful');
        $this->actingAs($this->kbUser('manager'))->postJson("/api/v1/knowledge-base/{$article->id}/feedback", [
            'is_helpful' => false,
        ])->assertCreated();
        $this->actingAs($this->kbUser('requester'))->postJson("/api/v1/knowledge-base/{$article->id}/feedback", [
            'is_helpful' => false,
            'comment' => 'Changed vote',
        ])->assertOk();

        $this->assertDatabaseCount('knowledge_base_feedback', 2);
        $this->actingAs($this->kbUser('requester'))->getJson("/api/v1/knowledge-base/{$article->id}")
            ->assertOk()->assertJsonPath('data.helpful_count', 0)->assertJsonPath('data.not_helpful_count', 2)
            ->assertJsonPath('data.helpful_ratio', 0);
    }

    public function test_zero_feedback_ratio_is_null(): void
    {
        $published = $this->createKbArticle(['status' => 'published']);
        $this->actingAs($this->kbUser('requester'))->getJson("/api/v1/knowledge-base/{$published->id}")
            ->assertOk()->assertJsonPath('data.helpful_ratio', null);
    }

    public function test_archived_article_rejects_feedback(): void
    {
        $archived = $this->createKbArticle(['status' => 'archived']);
        $this->actingAs($this->kbUser('requester'))->postJson("/api/v1/knowledge-base/{$archived->id}/feedback", [
            'is_helpful' => true,
        ])->assertConflict();
        $this->assertDatabaseMissing('knowledge_base_feedback', ['article_id' => $archived->id]);
    }

    public function test_workflow_notifications_reach_expected_recipients_and_are_deduplicated(): void
    {
        $article = $this->createKbArticle();
        $this->actingAs($this->kbUser('pic'))->postJson("/api/v1/knowledge-base/{$article->id}/submit-review")->assertOk();
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $this->kbUser('it_lead')->id,
        ]);

        $this->actingAs($this->kbUser('it_lead'))->postJson("/api/v1/knowledge-base/{$article->id}/publish")->assertOk();
        $authorNotifications = $this->kbUser('pic')->notifications()->get();
        $this->assertCount(1, $authorNotifications);
        $this->assertSame('knowledge_article_published', $authorNotifications->first()->data['type']);

        $service = app(KnowledgeBaseNotificationService::class);
        $service->dispatch($article->fresh(), 'knowledge_article_published', 'success', 'Published', 'Published once');
        $this->assertCount(1, $this->kbUser('pic')->notifications()->get());
        $this->assertDatabaseHas('notification_delivery_logs', [
            'user_id' => $this->kbUser('pic')->id,
            'notification_type' => 'knowledge_article_published',
            'status' => 'skipped',
        ]);
    }

    public function test_activity_records_actor_transitions_metadata_and_is_authorized(): void
    {
        $article = $this->createKbArticle();
        $this->actingAs($this->kbUser('pic'))->postJson("/api/v1/knowledge-base/{$article->id}/submit-review")->assertOk();
        $this->actingAs($this->kbUser('it_lead'))->postJson("/api/v1/knowledge-base/{$article->id}/reject", [
            'reason' => 'Add rollback details.',
        ])->assertOk();

        $this->actingAs($this->kbUser('requester'))->getJson("/api/v1/knowledge-base/{$article->id}/activity")->assertForbidden();
        $response = $this->actingAs($this->kbUser('it_lead'))->getJson("/api/v1/knowledge-base/{$article->id}/activity")
            ->assertOk()->assertJsonFragment([
                'action' => 'article_rejected',
                'from_status' => 'in_review',
                'to_status' => 'rejected',
            ])->assertJsonFragment(['reason' => 'Add rollback details.']);
        $this->assertNotEmpty($response->json('data.0.actor.id'));
    }

    public function test_request_id_is_echoed_for_success_validation_and_authorization_responses(): void
    {
        foreach ([
            ['GET', '/api/v1/knowledge-base', [], $this->kbUser('requester')],
            ['POST', '/api/v1/knowledge-base', [], $this->kbUser('pic')],
            ['POST', '/api/v1/knowledge-base', $this->articlePayload(), $this->kbUser('requester')],
        ] as $index => [$method, $url, $payload, $user]) {
            $requestId = "00000000-0000-4000-8000-00000000000{$index}";
            $response = $this->actingAs($user)->withHeader('X-Request-ID', $requestId)->json($method, $url, $payload);
            $response->assertHeader('X-Request-ID', $requestId);
            if ($response->isClientError()) {
                $response->assertJsonPath('meta.request_id', $requestId);
            }
        }
    }
}
