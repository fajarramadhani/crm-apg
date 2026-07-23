<?php

namespace App\Services;

use App\Exceptions\KnowledgeBaseConflict;
use App\Models\KnowledgeBaseActivityLog;
use App\Models\KnowledgeBaseArticle;
use App\Models\KnowledgeBaseFeedback;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class KnowledgeBaseFeedbackService
{
    public function submitFeedback(KnowledgeBaseArticle $article, User $user, bool $isHelpful, ?string $comment = null): KnowledgeBaseFeedback
    {
        return DB::transaction(function () use ($article, $user, $isHelpful, $comment) {
            if ($article->status !== 'published') {
                throw new KnowledgeBaseConflict('Only published articles can receive feedback.', $article->status);
            }

            // Limit comment length
            $comment = $comment ? substr(trim(strip_tags($comment)), 0, 500) : null;

            // Enforce single active feedback per user & article
            $feedback = KnowledgeBaseFeedback::updateOrCreate(
                ['article_id' => $article->id, 'user_id' => $user->id],
                ['is_helpful' => $isHelpful, 'comment' => $comment]
            );

            // Update article feedback counts
            $helpfulCount = KnowledgeBaseFeedback::where('article_id', $article->id)->where('is_helpful', true)->count();
            $notHelpfulCount = KnowledgeBaseFeedback::where('article_id', $article->id)->where('is_helpful', false)->count();

            $article->update([
                'helpful_count' => $helpfulCount,
                'not_helpful_count' => $notHelpfulCount,
            ]);

            KnowledgeBaseActivityLog::create([
                'article_id' => $article->id,
                'actor_id' => $user->id,
                'action' => 'feedback_submitted',
                'metadata' => ['is_helpful' => $isHelpful],
            ]);

            return $feedback;
        });
    }

    public function getHelpfulRatio(KnowledgeBaseArticle $article): ?float
    {
        $total = $article->helpful_count + $article->not_helpful_count;
        if ($total === 0) {
            return null; // Zero denominator returns null
        }

        return round($article->helpful_count / $total, 2);
    }
}
