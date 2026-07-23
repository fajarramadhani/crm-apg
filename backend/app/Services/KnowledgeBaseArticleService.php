<?php

namespace App\Services;

use App\Models\KnowledgeBaseActivityLog;
use App\Models\KnowledgeBaseArticle;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class KnowledgeBaseArticleService
{
    public function __construct(
        private KnowledgeBaseRedactionService $redaction,
        private KnowledgeBaseVersionService $versionService
    ) {}

    public function create(User $author, array $data): KnowledgeBaseArticle
    {
        return DB::transaction(function () use ($author, $data) {
            // Generate article number
            $year = date('Y');
            DB::table('knowledge_base_number_sequences')->insertOrIgnore([
                'year' => $year,
                'last_number' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $sequenceRow = DB::table('knowledge_base_number_sequences')->where('year', $year)->lockForUpdate()->first();
            $sequence = $sequenceRow->last_number + 1;
            DB::table('knowledge_base_number_sequences')->where('year', $year)->update([
                'last_number' => $sequence,
                'updated_at' => now(),
            ]);
            $articleNumber = "KB-{$year}-".str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);

            // Clean inputs
            $title = trim(strip_tags($data['title'] ?? ''));
            $summary = trim(strip_tags($data['summary'] ?? ''));
            $content = $this->redaction->sanitize($data['content'] ?? '');
            $slug = Str::slug($title);

            // Ensure unique slug
            $originalSlug = $slug;
            $counter = 1;
            while (KnowledgeBaseArticle::where('slug', $slug)->exists()) {
                $slug = "{$originalSlug}-{$counter}";
                $counter++;
            }

            $article = KnowledgeBaseArticle::create([
                'article_number' => $articleNumber,
                'slug' => $slug,
                'title' => $title,
                'summary' => $summary,
                'content' => $content,
                'status' => 'draft',
                'visibility' => $data['visibility'] ?? 'all_authenticated',
                'category_id' => $data['category_id'] ?? null,
                'application_id' => $data['application_id'] ?? null,
                'author_id' => $author->id,
                'current_version' => 1,
            ]);

            // Sync tags
            if (isset($data['tags'])) {
                $article->tags()->sync($data['tags']);
            }

            // Create initial version
            $this->versionService->createVersion($article, $author, 1, 'Initial version');

            // Log activity
            KnowledgeBaseActivityLog::create([
                'article_id' => $article->id,
                'actor_id' => $author->id,
                'action' => 'article_created',
                'to_status' => $article->status,
            ]);

            return $article;
        });
    }

    public function update(KnowledgeBaseArticle $article, User $editor, array $data): KnowledgeBaseArticle
    {
        return DB::transaction(function () use ($article, $editor, $data) {
            // Cannot edit published article directly, must create revision draft or update draft/in_review
            if ($article->status === 'published' && ($data['status'] ?? '') !== 'archived') {
                throw new \InvalidArgumentException('Published articles cannot be directly updated. Create a revision draft instead.');
            }

            if ($article->status === 'in_review' && ! $editor->hasPermission('knowledge_base.review') && $article->author_id !== $editor->id) {
                throw new \InvalidArgumentException('You do not have permission to edit an article currently in review.');
            }

            // Clean inputs
            $title = trim(strip_tags($data['title'] ?? $article->title));
            $summary = trim(strip_tags($data['summary'] ?? $article->summary));
            $content = isset($data['content']) ? $this->redaction->sanitize($data['content']) : $article->content;

            $slug = $article->slug;
            if ($title !== $article->title && $article->status === 'draft') {
                $slug = Str::slug($title);
                $originalSlug = $slug;
                $counter = 1;
                while (KnowledgeBaseArticle::where('slug', $slug)->where('id', '!=', $article->id)->exists()) {
                    $slug = "{$originalSlug}-{$counter}";
                    $counter++;
                }
            }

            $oldStatus = $article->status;

            $article->update([
                'slug' => $slug,
                'title' => $title,
                'summary' => $summary,
                'content' => $content,
                'visibility' => $data['visibility'] ?? $article->visibility,
                'category_id' => $data['category_id'] ?? $article->category_id,
                'application_id' => $data['application_id'] ?? $article->application_id,
            ]);

            if (isset($data['tags'])) {
                $article->tags()->sync($data['tags']);
            }

            KnowledgeBaseActivityLog::create([
                'article_id' => $article->id,
                'actor_id' => $editor->id,
                'action' => 'article_updated',
                'from_status' => $oldStatus,
                'to_status' => $oldStatus,
            ]);

            return $article->fresh();
        });
    }

    public function createRevisionDraft(KnowledgeBaseArticle $article, User $editor, array $data): KnowledgeBaseArticle
    {
        return DB::transaction(function () use ($article, $editor, $data) {
            if ($article->status !== 'published') {
                throw new \InvalidArgumentException('Only published articles can have a revision draft.');
            }

            // Create revision draft means increment version and save new fields as draft
            $newVersion = $article->current_version + 1;

            $article->update([
                'title' => trim(strip_tags($data['title'] ?? $article->title)),
                'summary' => trim(strip_tags($data['summary'] ?? $article->summary)),
                'content' => isset($data['content']) ? $this->redaction->sanitize($data['content']) : $article->content,
                'visibility' => $data['visibility'] ?? $article->visibility,
                'category_id' => $data['category_id'] ?? $article->category_id,
                'application_id' => $data['application_id'] ?? $article->application_id,
                'status' => 'draft',
                'current_version' => $newVersion,
            ]);

            if (isset($data['tags'])) {
                $article->tags()->sync($data['tags']);
            }

            $this->versionService->createVersion($article, $editor, $newVersion, $data['change_summary'] ?? 'Revision draft');

            KnowledgeBaseActivityLog::create([
                'article_id' => $article->id,
                'actor_id' => $editor->id,
                'action' => 'article_revision_created',
                'metadata' => ['version' => $newVersion],
            ]);

            return $article;
        });
    }
}
