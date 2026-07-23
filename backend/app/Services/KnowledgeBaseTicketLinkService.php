<?php

namespace App\Services;

use App\Exceptions\KnowledgeBaseConflict;
use App\Models\KnowledgeBaseActivityLog;
use App\Models\KnowledgeBaseArticle;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class KnowledgeBaseTicketLinkService
{
    public function __construct(
        private KnowledgeBaseRedactionService $redaction,
        private KnowledgeBaseArticleService $articleService
    ) {}

    public function link(KnowledgeBaseArticle $article, Ticket $ticket, string $relationType, User $user): void
    {
        DB::transaction(function () use ($article, $ticket, $relationType, $user) {
            $allowedTypes = ['source', 'related', 'used_as_solution', 'recommended'];
            if (! in_array($relationType, $allowedTypes, true)) {
                throw new \InvalidArgumentException("Invalid relation type: {$relationType}");
            }

            // Enforce constraints
            if ($ticket->kbArticles()->wherePivot('relation_type', $relationType)->where('article_id', $article->id)->exists()) {
                return; // Already linked
            }

            $ticket->kbArticles()->attach($article->id, [
                'relation_type' => $relationType,
                'linked_by' => $user->id,
                'created_at' => now(),
            ]);

            KnowledgeBaseActivityLog::create([
                'article_id' => $article->id,
                'actor_id' => $user->id,
                'action' => 'ticket_linked',
                'metadata' => [
                    'ticket_id' => $ticket->id,
                    'ticket_number' => $ticket->ticket_number,
                    'relation_type' => $relationType,
                ],
            ]);
        });
    }

    public function unlink(KnowledgeBaseArticle $article, Ticket $ticket, User $user): void
    {
        DB::transaction(function () use ($article, $ticket, $user) {
            $ticket->kbArticles()->detach($article->id);

            KnowledgeBaseActivityLog::create([
                'article_id' => $article->id,
                'actor_id' => $user->id,
                'action' => 'ticket_unlinked',
                'metadata' => [
                    'ticket_id' => $ticket->id,
                    'ticket_number' => $ticket->ticket_number,
                ],
            ]);
        });
    }

    public function createDraftFromTicket(Ticket $ticket, User $user, array $additionalData = []): KnowledgeBaseArticle
    {
        // Enforce eligibility
        // Ticket must have some resolution details
        $hasResolution = $ticket->closed_at !== null
            || $ticket->closures()->exists()
            || $ticket->currentAnalysis()->exists()
            || $ticket->currentSolutionPlan()->exists();
        if (! $hasResolution) {
            throw new KnowledgeBaseConflict('Ticket must have complete resolution details to create a knowledge base draft.', $ticket->status->value);
        }

        return DB::transaction(function () use ($ticket, $user, $additionalData) {
            // Read resolution plan and closed summary safely
            $rca = '';
            if ($ticket->currentAnalysis) {
                $rca = "Root Cause Analysis:\n".$ticket->currentAnalysis->root_cause."\n\n";
            }

            $sol = '';
            if ($ticket->currentSolutionPlan) {
                $sol = "Proposed Solution:\n".$ticket->currentSolutionPlan->solution_summary."\n\n";
            }

            $closure = '';
            $latestClosure = $ticket->closures()->first();
            if ($latestClosure) {
                $closure = "Resolution Summary:\n".$latestClosure->resolution_summary."\n".
                           "Business Outcome:\n".$latestClosure->business_outcome."\n\n";
            }

            $rawContent = $rca.$sol.$closure."System Context:\nApplication: ".($ticket->application?->name ?? 'N/A')."\n";

            // Sanitize raw text to prevent PII and credentials leak
            $title = $additionalData['title'] ?? ('Penyelesaian Masalah: '.$ticket->title);
            $summary = $this->redaction->sanitizeDraftText($ticket->description);
            $content = $this->redaction->sanitizeDraftText($rawContent);

            $article = $this->articleService->create($user, [
                'title' => $title,
                'summary' => $summary,
                'content' => $content,
                'status' => 'draft',
                'visibility' => $additionalData['visibility'] ?? 'all_authenticated',
                'category_id' => $ticket->ticket_category_id,
                'application_id' => $ticket->application_id,
            ]);

            // Link as source
            $this->link($article, $ticket, 'source', $user);

            return $article;
        });
    }
}
