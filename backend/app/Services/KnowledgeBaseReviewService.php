<?php

namespace App\Services;

use App\Events\KnowledgeArticleArchived;
use App\Events\KnowledgeArticlePublished;
use App\Events\KnowledgeArticleRejected;
use App\Events\KnowledgeArticleSubmitted;
use App\Exceptions\KnowledgeBaseConflict;
use App\Models\KnowledgeBaseActivityLog;
use App\Models\KnowledgeBaseArticle;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class KnowledgeBaseReviewService
{
    public function __construct(private KnowledgeBaseVersionService $versionService) {}

    public function submitForReview(KnowledgeBaseArticle $article, User $actor): void
    {
        DB::transaction(function () use ($article, $actor) {
            if (! in_array($article->status, ['draft', 'rejected'])) {
                throw new KnowledgeBaseConflict("Cannot submit for review from status: {$article->status}", $article->status);
            }

            $oldStatus = $article->status;

            $latestVersion = $article->versions()->first();
            $contentChanged = ! $latestVersion
                || $latestVersion->title !== $article->title
                || $latestVersion->summary !== $article->summary
                || $latestVersion->content !== $article->content
                || $latestVersion->visibility !== $article->visibility
                || $latestVersion->category_id !== $article->category_id
                || $latestVersion->application_id !== $article->application_id;

            if ($contentChanged) {
                $nextVersion = $article->current_version + 1;
                $article->update(['current_version' => $nextVersion]);
                $this->versionService->createVersion($article, $actor, $nextVersion, 'Submitted for review');
            }

            $article->update(['status' => 'in_review']);

            KnowledgeBaseActivityLog::create([
                'article_id' => $article->id,
                'actor_id' => $actor->id,
                'action' => 'submitted_for_review',
                'from_status' => $oldStatus,
                'to_status' => 'in_review',
            ]);

            // Dispatch domain event (will handle notification trigger)
            event(new KnowledgeArticleSubmitted($article, $actor));
        });
    }

    public function publish(KnowledgeBaseArticle $article, User $reviewer, ?string $changeSummary = null): void
    {
        DB::transaction(function () use ($article, $reviewer, $changeSummary) {
            if ($article->status !== 'in_review') {
                throw new KnowledgeBaseConflict('Only articles in review can be published.', $article->status);
            }

            // Self-approval check
            if ($article->author_id === $reviewer->id) {
                throw new KnowledgeBaseConflict('Authors cannot publish/approve their own articles.', $article->status);
            }

            $article->update([
                'status' => 'published',
                'reviewer_id' => $reviewer->id,
                'published_by' => $reviewer->id,
                'published_at' => now(),
            ]);

            KnowledgeBaseActivityLog::create([
                'article_id' => $article->id,
                'actor_id' => $reviewer->id,
                'action' => 'article_published',
                'from_status' => 'in_review',
                'to_status' => 'published',
                'metadata' => ['change_summary' => $changeSummary],
            ]);

            event(new KnowledgeArticlePublished($article, $reviewer));
        });
    }

    public function reject(KnowledgeBaseArticle $article, User $reviewer, string $reason): void
    {
        if (empty(trim($reason))) {
            throw new KnowledgeBaseConflict('Rejection reason is required.', $article->status);
        }

        DB::transaction(function () use ($article, $reviewer, $reason) {
            if ($article->status !== 'in_review') {
                throw new KnowledgeBaseConflict('Only articles in review can be rejected.', $article->status);
            }

            // Self-approval check
            if ($article->author_id === $reviewer->id) {
                throw new KnowledgeBaseConflict('Authors cannot reject their own articles.', $article->status);
            }

            $article->update([
                'status' => 'rejected',
                'rejected_by' => $reviewer->id,
                'rejected_at' => now(),
                'rejection_reason' => $reason,
            ]);

            KnowledgeBaseActivityLog::create([
                'article_id' => $article->id,
                'actor_id' => $reviewer->id,
                'action' => 'article_rejected',
                'from_status' => 'in_review',
                'to_status' => 'rejected',
                'metadata' => ['reason' => $reason],
            ]);

            event(new KnowledgeArticleRejected($article, $reviewer));
        });
    }

    public function archive(KnowledgeBaseArticle $article, User $actor): void
    {
        DB::transaction(function () use ($article, $actor) {
            if ($article->status !== 'published') {
                throw new KnowledgeBaseConflict('Only published articles can be archived.', $article->status);
            }

            $article->update([
                'status' => 'archived',
                'archived_by' => $actor->id,
                'archived_at' => now(),
            ]);

            KnowledgeBaseActivityLog::create([
                'article_id' => $article->id,
                'actor_id' => $actor->id,
                'action' => 'article_archived',
                'from_status' => 'published',
                'to_status' => 'archived',
            ]);

            event(new KnowledgeArticleArchived($article, $actor));
        });
    }

    public function restore(KnowledgeBaseArticle $article, User $actor): void
    {
        DB::transaction(function () use ($article, $actor) {
            if ($article->status !== 'archived') {
                throw new KnowledgeBaseConflict('Only archived articles can be restored.', $article->status);
            }

            $article->update([
                'status' => 'draft',
                'archived_by' => null,
                'archived_at' => null,
            ]);

            KnowledgeBaseActivityLog::create([
                'article_id' => $article->id,
                'actor_id' => $actor->id,
                'action' => 'article_restored',
                'from_status' => 'archived',
                'to_status' => 'draft',
            ]);
        });
    }
}
