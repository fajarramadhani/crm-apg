<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Feature\Concerns\InteractsWithKnowledgeBase;
use Tests\TestCase;

class KnowledgeBaseArticleWorkflowTest extends TestCase
{
    use InteractsWithKnowledgeBase;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpKnowledgeBase();
    }

    public function test_role_visibility_is_enforced_for_lists_and_details(): void
    {
        $public = $this->createKbArticle(['title' => 'Public Guide', 'status' => 'published']);
        $internal = $this->createKbArticle(['title' => 'Internal Runbook', 'visibility' => 'it_internal', 'status' => 'published']);

        foreach (['requester', 'manager', 'executive'] as $role) {
            $response = $this->actingAs($this->kbUser($role))->getJson('/api/v1/knowledge-base')->assertOk();
            $this->assertContains($public->id, array_column($response->json('data'), 'id'));
            $this->assertNotContains($internal->id, array_column($response->json('data'), 'id'));
            $this->actingAs($this->kbUser($role))->getJson("/api/v1/knowledge-base/{$internal->id}")->assertForbidden();
        }

        $this->actingAs($this->kbUser('pic'))->getJson('/api/v1/knowledge-base')
            ->assertOk()->assertJsonFragment(['id' => $public->id])->assertJsonFragment(['id' => $internal->id]);
        $this->actingAs($this->kbUser('pic'))->getJson("/api/v1/knowledge-base/{$internal->slug}")->assertOk();
    }

    public function test_draft_is_visible_only_to_owner_and_reviewer(): void
    {
        $draft = $this->createKbArticle();

        $this->actingAs($this->kbUser('pic'))->getJson("/api/v1/knowledge-base/{$draft->id}")->assertOk();
        $this->actingAs($this->kbUser('other_pic'))->getJson("/api/v1/knowledge-base/{$draft->id}")->assertNotFound();
        $this->actingAs($this->kbUser('it_lead'))->getJson("/api/v1/knowledge-base/{$draft->id}")->assertOk();
    }

    public function test_creation_validates_input_and_generates_sequential_numbers_and_unique_slugs(): void
    {
        Carbon::setTestNow('2026-07-22 10:00:00');
        $this->actingAs($this->kbUser('pic'))->postJson('/api/v1/knowledge-base', [])->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'summary', 'content', 'visibility']);

        $first = $this->actingAs($this->kbUser('pic'))->postJson('/api/v1/knowledge-base', $this->articlePayload())
            ->assertCreated()->json('data');
        $second = $this->actingAs($this->kbUser('pic'))->postJson('/api/v1/knowledge-base', $this->articlePayload())
            ->assertCreated()->json('data');

        $this->assertSame('KB-2026-000001', $first['article_number']);
        $this->assertSame('KB-2026-000002', $second['article_number']);
        $this->assertSame('configure-secure-vpn', $first['slug']);
        $this->assertSame('configure-secure-vpn-1', $second['slug']);
        $this->assertSame($this->kbUser('pic')->id, $first['author']['id']);
        $this->assertDatabaseCount('knowledge_base_article_versions', 2);
    }

    public function test_creation_and_editing_permissions_and_ownership_are_enforced(): void
    {
        $this->actingAs($this->kbUser('requester'))->postJson('/api/v1/knowledge-base', $this->articlePayload())->assertForbidden();
        $this->actingAs($this->kbUser('pic'))->postJson('/api/v1/knowledge-base', $this->articlePayload([
            'visibility' => 'business_internal',
        ]))->assertCreated();
        $this->actingAs($this->kbUser('pic'))->postJson('/api/v1/knowledge-base', $this->articlePayload([
            'visibility' => 'it_internal',
        ]))->assertCreated();

        $article = $this->createKbArticle();
        $this->actingAs($this->kbUser('other_pic'))->putJson("/api/v1/knowledge-base/{$article->id}", [
            'title' => 'Unauthorized title',
        ])->assertForbidden();
        $this->actingAs($this->kbUser('pic'))->putJson("/api/v1/knowledge-base/{$article->id}", [
            'title' => 'Authorized New Title',
        ])->assertOk()->assertJsonPath('data.slug', 'authorized-new-title');
    }

    public function test_submit_publish_reject_archive_and_restore_workflow(): void
    {
        $article = $this->createKbArticle();
        $this->actingAs($this->kbUser('pic'))->postJson("/api/v1/knowledge-base/{$article->id}/submit-review")
            ->assertOk()->assertJsonPath('data.status', 'in_review');
        $this->actingAs($this->kbUser('it_lead'))->postJson("/api/v1/knowledge-base/{$article->id}/reject", [
            'reason' => 'Needs clearer recovery steps.',
        ])->assertOk()->assertJsonPath('data.status', 'rejected');
        $this->actingAs($this->kbUser('pic'))->putJson("/api/v1/knowledge-base/{$article->id}", [
            'content' => '<p>Expanded recovery steps.</p>',
            'status' => 'in_review',
        ])->assertOk()->assertJsonPath('data.status', 'in_review');
        $this->actingAs($this->kbUser('it_lead'))->postJson("/api/v1/knowledge-base/{$article->id}/publish", [
            'change_summary' => 'Approved expanded instructions.',
        ])->assertOk()->assertJsonPath('data.status', 'published');
        $this->actingAs($this->kbUser('it_lead'))->postJson("/api/v1/knowledge-base/{$article->id}/archive")
            ->assertOk()->assertJsonPath('data.status', 'archived');
        $this->actingAs($this->kbUser('it_lead'))->postJson("/api/v1/knowledge-base/{$article->id}/restore")
            ->assertOk()->assertJsonPath('data.status', 'draft');
    }

    public function test_self_approval_returns_conflict(): void
    {
        $article = $this->createKbArticle(['status' => 'in_review'], $this->kbUser('it_lead'));
        $this->actingAs($this->kbUser('it_lead'))->postJson("/api/v1/knowledge-base/{$article->id}/publish")
            ->assertConflict();
    }

    public function test_self_rejection_returns_conflict(): void
    {
        $article = $this->createKbArticle(['status' => 'in_review'], $this->kbUser('it_lead'));
        $this->actingAs($this->kbUser('it_lead'))->postJson("/api/v1/knowledge-base/{$article->id}/reject", [
            'reason' => 'Self rejection is forbidden.',
        ])->assertConflict();
    }

    public function test_archiving_a_draft_returns_conflict(): void
    {
        $draft = $this->createKbArticle();
        $this->actingAs($this->kbUser('it_lead'))->postJson("/api/v1/knowledge-base/{$draft->id}/archive")
            ->assertConflict();
    }

    public function test_duplicate_review_submission_returns_conflict(): void
    {
        $draft = $this->createKbArticle();
        $this->actingAs($this->kbUser('pic'))->postJson("/api/v1/knowledge-base/{$draft->id}/submit-review")->assertOk();
        $this->actingAs($this->kbUser('pic'))->postJson("/api/v1/knowledge-base/{$draft->id}/submit-review")
            ->assertConflict();
    }

    public function test_published_edit_creates_revision_and_old_version_can_be_restored(): void
    {
        $article = $this->createKbArticle(['status' => 'published']);
        $this->actingAs($this->kbUser('it_lead'))->putJson("/api/v1/knowledge-base/{$article->id}", $this->articlePayload([
            'title' => 'Revised VPN Guide',
            'change_summary' => 'Updated client instructions.',
        ]))->assertOk()->assertJsonPath('data.status', 'draft')->assertJsonPath('data.current_version', 2);

        $this->actingAs($this->kbUser('it_lead'))->getJson("/api/v1/knowledge-base/{$article->id}/versions")
            ->assertOk()->assertJsonCount(2, 'data');
        $this->actingAs($this->kbUser('it_lead'))->postJson("/api/v1/knowledge-base/{$article->id}/restore-version/1")
            ->assertOk()->assertJsonPath('data.title', 'Reset Corporate Password')->assertJsonPath('data.current_version', 3);
        $this->assertDatabaseHas('knowledge_base_article_versions', [
            'article_id' => $article->id,
            'version_number' => 3,
            'change_summary' => 'Restored from version 1',
        ]);
    }
}
