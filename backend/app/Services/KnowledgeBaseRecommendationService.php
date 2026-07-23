<?php

namespace App\Services;

use App\Models\KnowledgeBaseArticle;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Collection;

class KnowledgeBaseRecommendationService
{
    public function getRecommendations(Ticket $ticket, User $user): Collection
    {
        // Enforce user visibility constraints
        $query = KnowledgeBaseArticle::query()
            ->where('status', 'published')
            ->with(['tags', 'category', 'application']);

        if (! $user->hasPermission('knowledge_base.view_it_internal')) {
            $query->whereIn('visibility', ['business_internal', 'all_authenticated']);
        } else {
            $query->whereIn('visibility', ['it_internal', 'business_internal', 'all_authenticated']);
        }

        $candidates = $query->get();

        // Scoring
        $scored = $candidates->map(function ($article) use ($ticket) {
            $score = 0;

            // 1. Same application
            if ($ticket->application_id && $article->application_id === $ticket->application_id) {
                $score += 30;
            }

            // 2. Same category
            if ($ticket->ticket_category_id && $article->category_id === $ticket->ticket_category_id) {
                $score += 25;
            }

            // 3. Tag overlap
            // We can compare ticket tags or category/application words
            // Assuming there are tags on the ticket, or we can check tag overlap with the article tags.
            // Let's get ticket keywords from title
            $ticketWords = array_filter(explode(' ', strtolower(preg_replace('/[^\w\s]/', '', $ticket->title))));

            foreach ($article->tags as $tag) {
                if (in_array(strtolower($tag->name), $ticketWords, true)) {
                    $score += 10;
                }
            }

            // 4. Title keyword match
            $articleTitleWords = array_filter(explode(' ', strtolower(preg_replace('/[^\w\s]/', '', $article->title))));
            $intersectTitle = array_intersect($ticketWords, $articleTitleWords);
            $score += count($intersectTitle) * 5;

            // 5. Summary keyword match
            $articleSummaryWords = array_filter(explode(' ', strtolower(preg_replace('/[^\w\s]/', '', $article->summary))));
            $intersectSummary = array_intersect($ticketWords, $articleSummaryWords);
            $score += count($intersectSummary) * 2;

            // 6. Helpful ratio
            // helpful ratio tinggi (helpful / (helpful + not_helpful))
            $totalFeedback = $article->helpful_count + $article->not_helpful_count;
            if ($totalFeedback > 0) {
                $helpfulRatio = $article->helpful_count / $totalFeedback;
                $score += (int) ($helpfulRatio * 5);
            }

            return [
                'article' => $article,
                'score' => $score,
            ];
        });

        // Filter out articles with score <= 0 if appropriate, but let's just sort and return top 10
        return $scored->sortByDesc(fn ($item) => $item['score'].'_'.$item['article']->published_at)
            ->take(10)
            ->map(function ($item) {
                $article = $item['article'];
                $article->recommendation_score = $item['score']; // append score for reference

                return $article;
            })
            ->values();
    }
}
