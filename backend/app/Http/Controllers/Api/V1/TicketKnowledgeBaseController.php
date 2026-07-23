<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\KnowledgeBaseVisibility;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\KnowledgeBaseArticleDetailResource;
use App\Http\Resources\Api\V1\KnowledgeBaseArticleResource;
use App\Models\KnowledgeBaseArticle;
use App\Models\Ticket;
use App\Services\KnowledgeBaseRecommendationService;
use App\Services\KnowledgeBaseTicketLinkService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rules\Enum;

class TicketKnowledgeBaseController extends Controller
{
    public function __construct(
        private KnowledgeBaseTicketLinkService $linkService,
        private KnowledgeBaseRecommendationService $recommendationService
    ) {}

    public function index(Request $request, string $ticketId)
    {
        Gate::authorize('knowledge_base.view');

        $ticket = Ticket::findOrFail($ticketId);

        $articles = $ticket->kbArticles()
            ->where(function ($q) use ($request) {
                if (! $request->user()->hasPermission('knowledge_base.view_it_internal')) {
                    $q->whereIn('visibility', ['business_internal', 'all_authenticated']);
                }
            })
            ->get();

        return KnowledgeBaseArticleResource::collection($articles);
    }

    public function recommendations(Request $request, string $ticketId)
    {
        Gate::authorize('knowledge_base.view');

        $ticket = Ticket::findOrFail($ticketId);

        $recommendations = $this->recommendationService->getRecommendations($ticket, $request->user());

        return KnowledgeBaseArticleResource::collection($recommendations);
    }

    public function link(Request $request, string $ticketId)
    {
        Gate::authorize('knowledge_base.link_ticket');

        $ticket = Ticket::findOrFail($ticketId);

        $validated = $request->validate([
            'article_id' => 'required|exists:knowledge_base_articles,id',
            'relation_type' => 'required|in:source,related,used_as_solution,recommended',
        ]);

        $article = KnowledgeBaseArticle::findOrFail($validated['article_id']);

        $this->linkService->link($article, $ticket, $validated['relation_type'], $request->user());

        return response()->json(['message' => 'Article linked to ticket successfully.']);
    }

    public function unlink(Request $request, string $ticketId, string $articleId)
    {
        Gate::authorize('knowledge_base.link_ticket');

        $ticket = Ticket::findOrFail($ticketId);

        $article = KnowledgeBaseArticle::findOrFail($articleId);

        $this->linkService->unlink($article, $ticket, $request->user());

        return response()->json(['message' => 'Article unlinked from ticket successfully.']);
    }

    public function createDraft(Request $request, string $ticketId)
    {
        Gate::authorize('knowledge_base.create_from_ticket');

        $ticket = Ticket::findOrFail($ticketId);

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'visibility' => ['sometimes', 'required', new Enum(KnowledgeBaseVisibility::class)],
        ]);

        if (isset($validated['visibility']) && $validated['visibility'] === 'it_internal' && ! $request->user()->hasPermission('knowledge_base.view_it_internal')) {
            abort(422, 'You cannot set visibility to IT internal.');
        }

        $article = $this->linkService->createDraftFromTicket($ticket, $request->user(), $validated);

        return new KnowledgeBaseArticleDetailResource($article);
    }
}
