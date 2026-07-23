<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\KnowledgeBaseTag;
use App\Models\TicketCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\InteractsWithKnowledgeBase;
use Tests\TestCase;

class KnowledgeBaseSearchTagSecurityTest extends TestCase
{
    use InteractsWithKnowledgeBase;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpKnowledgeBase();
    }

    public function test_search_rejects_unbounded_pagination_and_invalid_sort(): void
    {
        $requester = $this->kbUser('requester');

        $this->actingAs($requester)->getJson('/api/v1/knowledge-base?per_page=101')
            ->assertUnprocessable()->assertJsonValidationErrors('per_page');
        $this->actingAs($requester)->getJson('/api/v1/knowledge-base?sort_by=raw_sql')
            ->assertUnprocessable()->assertJsonValidationErrors('sort_by');
        $this->actingAs($requester)->getJson('/api/v1/knowledge-base?published_from=not-a-date')
            ->assertUnprocessable()->assertJsonValidationErrors('published_from');
    }

    public function test_search_filters_sorting_and_pagination(): void
    {
        $category = TicketCategory::where('code', 'INCIDENT')->firstOrFail();
        $application = Application::where('code', 'TIC_HUB')->firstOrFail();
        $tag = KnowledgeBaseTag::create(['name' => 'Network', 'slug' => 'network', 'is_active' => true]);
        $match = $this->createKbArticle([
            'title' => 'VPN Timeout Recovery',
            'summary' => 'Unique needle for remote access.',
            'category_id' => $category->id,
            'application_id' => $application->id,
            'tags' => [$tag->id],
            'status' => 'published',
        ]);
        $this->createKbArticle(['title' => 'Printer Setup', 'status' => 'published']);
        $this->createKbArticle(['title' => 'Unpublished Needle', 'status' => 'draft']);

        $query = http_build_query([
            'search' => 'needle',
            'category_id' => $category->id,
            'application_id' => $application->id,
            'tag' => 'network',
            'visibility' => 'all_authenticated',
            'sort_by' => 'title',
            'sort_order' => 'asc',
            'per_page' => 1,
        ]);
        $this->actingAs($this->kbUser('requester'))->getJson('/api/v1/knowledge-base?'.$query)
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $match->id)
            ->assertJsonPath('meta.per_page', 1)->assertJsonPath('meta.total', 1);
    }

    public function test_status_filter_is_limited_to_owners_but_reviewers_can_filter_all(): void
    {
        $own = $this->createKbArticle(['title' => 'Own Draft']);
        $other = $this->createKbArticle(['title' => 'Other Draft'], $this->kbUser('other_pic'));

        $ownerResponse = $this->actingAs($this->kbUser('pic'))->getJson('/api/v1/knowledge-base?status=draft')->assertOk();
        $this->assertContains($own->id, array_column($ownerResponse->json('data'), 'id'));
        $this->assertNotContains($other->id, array_column($ownerResponse->json('data'), 'id'));
        $this->actingAs($this->kbUser('it_lead'))->getJson('/api/v1/knowledge-base?status=draft')
            ->assertOk()->assertJsonFragment(['id' => $own->id])->assertJsonFragment(['id' => $other->id]);
    }

    public function test_invalid_sort_and_oversized_page_are_rejected(): void
    {
        $this->createKbArticle(['status' => 'published']);
        $this->actingAs($this->kbUser('requester'))->getJson('/api/v1/knowledge-base?sort_by=author_id')
            ->assertUnprocessable();
        $this->actingAs($this->kbUser('requester'))->getJson('/api/v1/knowledge-base?per_page=999')
            ->assertUnprocessable()->assertJsonValidationErrors('per_page');
    }

    public function test_admin_manages_tags_and_deactivation_hides_them_without_deleting_relations(): void
    {
        $created = $this->actingAs($this->kbUser('admin'))->postJson('/api/v1/knowledge-base-tags', ['name' => 'Remote Access'])
            ->assertCreated()->assertJsonPath('data.slug', 'remote-access')->json('data');
        $this->actingAs($this->kbUser('admin'))->postJson('/api/v1/knowledge-base-tags', ['name' => 'Remote Access'])
            ->assertUnprocessable();
        $article = $this->createKbArticle(['tags' => [$created['id']], 'status' => 'published']);

        $this->actingAs($this->kbUser('admin'))->deleteJson("/api/v1/knowledge-base-tags/{$created['id']}")->assertNoContent();
        $this->assertDatabaseHas('knowledge_base_tags', ['id' => $created['id'], 'is_active' => false]);
        $this->assertDatabaseHas('knowledge_base_article_tag', ['article_id' => $article->id, 'tag_id' => $created['id']]);
        $this->actingAs($this->kbUser('requester'))->getJson('/api/v1/knowledge-base-tags')
            ->assertOk()->assertJsonMissing(['id' => $created['id']]);
        $this->actingAs($this->kbUser('admin'))->getJson('/api/v1/knowledge-base-tags')
            ->assertOk()->assertJsonFragment(['id' => $created['id']]);
    }

    public function test_stored_xss_and_dangerous_urls_are_sanitized(): void
    {
        $content = '<script>alert(1)</script><img src="data:text/html,bad" onerror="alert(2)">'
            .'<a href="javascript:alert(3)">click</a><p>Safe text</p>';
        $data = $this->actingAs($this->kbUser('pic'))->postJson('/api/v1/knowledge-base', $this->articlePayload([
            'title' => '<img src=x onerror=alert(1)>Secure title',
            'summary' => '<script>bad()</script>Safe summary',
            'content' => $content,
        ]))->assertCreated()->json('data');

        $stored = $data['content'];
        $this->assertStringNotContainsStringIgnoringCase('<script', $stored);
        $this->assertStringNotContainsStringIgnoringCase('onerror', $stored);
        $this->assertStringNotContainsStringIgnoringCase('javascript:', $stored);
        $this->assertStringNotContainsStringIgnoringCase('data:', $stored);
        $this->assertStringContainsString('Safe text', $stored);
        $this->assertSame('Secure title', $data['title']);
        $this->assertSame('bad()Safe summary', $data['summary']);
    }
}
