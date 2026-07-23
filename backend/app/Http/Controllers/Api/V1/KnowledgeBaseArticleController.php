<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\KnowledgeBaseArticleStatus;
use App\Enums\KnowledgeBaseVisibility;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\KnowledgeBaseActivityResource;
use App\Http\Resources\Api\V1\KnowledgeBaseArticleDetailResource;
use App\Http\Resources\Api\V1\KnowledgeBaseArticleResource;
use App\Http\Resources\Api\V1\KnowledgeBaseArticleVersionResource;
use App\Models\KnowledgeBaseArticle;
use App\Models\KnowledgeBaseArticleVersion;
use App\Services\KnowledgeBaseArticleService;
use App\Services\KnowledgeBaseReviewService;
use App\Services\KnowledgeBaseSearchService;
use App\Services\KnowledgeBaseVersionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rules\Enum;

class KnowledgeBaseArticleController extends Controller
{
    public function __construct(
        private KnowledgeBaseSearchService $searchService,
        private KnowledgeBaseArticleService $articleService,
        private KnowledgeBaseVersionService $versionService,
        private KnowledgeBaseReviewService $reviewService
    ) {}

    public function index(Request $request)
    {
        Gate::authorize('knowledge_base.view');

        $articles = $this->searchService->search($request->user(), $request->all());

        return KnowledgeBaseArticleResource::collection($articles);
    }

    public function show(Request $request, string $idOrSlug)
    {
        Gate::authorize('knowledge_base.view');

        $article = KnowledgeBaseArticle::where('id', $idOrSlug)
            ->orWhere('slug', $idOrSlug)
            ->firstOrFail();

        // Check visibility constraints
        if ($article->visibility === 'it_internal' && ! $request->user()->hasPermission('knowledge_base.view_it_internal')) {
            abort(403, 'You do not have permission to view IT internal articles.');
        }

        // Normal users can only see published articles
        if ($article->status !== 'published' && ! $request->user()->hasPermission('knowledge_base.review') && $article->author_id !== $request->user()->id) {
            abort(404, 'Article not found.');
        }

        // Increment view count
        $article->increment('view_count');

        return new KnowledgeBaseArticleDetailResource($article);
    }

    public function store(Request $request)
    {
        Gate::authorize('knowledge_base.create');

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'summary' => 'required|string|max:1000',
            'content' => 'required|string',
            'visibility' => ['required', new Enum(KnowledgeBaseVisibility::class)],
            'category_id' => 'nullable|exists:ticket_categories,id',
            'application_id' => 'nullable|exists:applications,id',
            'status' => ['sometimes', new Enum(KnowledgeBaseArticleStatus::class)],
            'tags' => 'array',
            'tags.*' => 'exists:knowledge_base_tags,id',
        ]);

        // Non-IT roles cannot select it_internal visibility
        if ($validated['visibility'] === 'it_internal' && ! $request->user()->hasPermission('knowledge_base.view_it_internal')) {
            abort(422, 'You cannot set visibility to IT internal.');
        }

        $submitForReview = ($validated['status'] ?? 'draft') === 'in_review';
        unset($validated['status']);
        $article = $this->articleService->create($request->user(), $validated);

        if ($submitForReview) {
            $this->reviewService->submitForReview($article, $request->user());
        }

        return (new KnowledgeBaseArticleDetailResource($article))->response()->setStatusCode(201);
    }

    public function update(Request $request, string $id)
    {
        $article = KnowledgeBaseArticle::findOrFail($id);

        if ($article->status === 'published') {
            Gate::authorize('knowledge_base.edit_any');
            // Update published article by creating a revision draft
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'summary' => 'required|string|max:1000',
                'content' => 'required|string',
                'visibility' => ['required', new Enum(KnowledgeBaseVisibility::class)],
                'category_id' => 'nullable|exists:ticket_categories,id',
                'application_id' => 'nullable|exists:applications,id',
                'change_summary' => 'required|string|max:500',
                'tags' => 'array',
                'tags.*' => 'exists:knowledge_base_tags,id',
            ]);

            if ($validated['visibility'] === 'it_internal' && ! $request->user()->hasPermission('knowledge_base.view_it_internal')) {
                abort(422, 'You cannot set visibility to IT internal.');
            }

            $article = $this->articleService->createRevisionDraft($article, $request->user(), $validated);
        } else {
            // Edit draft or rejected
            if ($article->author_id === $request->user()->id) {
                Gate::authorize('knowledge_base.edit_own');
            } else {
                Gate::authorize('knowledge_base.edit_any');
            }

            $validated = $request->validate([
                'title' => 'sometimes|required|string|max:255',
                'summary' => 'sometimes|required|string|max:1000',
                'content' => 'sometimes|required|string',
                'visibility' => ['sometimes', 'required', new Enum(KnowledgeBaseVisibility::class)],
                'category_id' => 'nullable|exists:ticket_categories,id',
                'application_id' => 'nullable|exists:applications,id',
                'status' => ['sometimes', new Enum(KnowledgeBaseArticleStatus::class)],
                'tags' => 'array',
                'tags.*' => 'exists:knowledge_base_tags,id',
            ]);

            if (isset($validated['visibility']) && $validated['visibility'] === 'it_internal' && ! $request->user()->hasPermission('knowledge_base.view_it_internal')) {
                abort(422, 'You cannot set visibility to IT internal.');
            }

            $submitForReview = ($validated['status'] ?? null) === 'in_review';
            unset($validated['status']);
            $article = $this->articleService->update($article, $request->user(), $validated);

            if ($submitForReview) {
                $this->reviewService->submitForReview($article, $request->user());
            }
        }

        return new KnowledgeBaseArticleDetailResource($article);
    }

    public function submitReview(Request $request, string $id)
    {
        $article = KnowledgeBaseArticle::findOrFail($id);

        if ($article->author_id === $request->user()->id) {
            Gate::authorize('knowledge_base.edit_own');
        } else {
            Gate::authorize('knowledge_base.edit_any');
        }

        $this->reviewService->submitForReview($article, $request->user());

        return new KnowledgeBaseArticleDetailResource($article->fresh());
    }

    public function archive(Request $request, string $id)
    {
        Gate::authorize('knowledge_base.archive');

        $article = KnowledgeBaseArticle::findOrFail($id);
        $this->reviewService->archive($article, $request->user());

        return new KnowledgeBaseArticleDetailResource($article->fresh());
    }

    public function restore(Request $request, string $id)
    {
        Gate::authorize('knowledge_base.archive');

        $article = KnowledgeBaseArticle::findOrFail($id);
        $this->reviewService->restore($article, $request->user());

        return new KnowledgeBaseArticleDetailResource($article->fresh());
    }

    public function versions(Request $request, string $id)
    {
        Gate::authorize('knowledge_base.view');

        $article = KnowledgeBaseArticle::findOrFail($id);

        if ($article->visibility === 'it_internal' && ! $request->user()->hasPermission('knowledge_base.view_it_internal')) {
            abort(403);
        }

        return KnowledgeBaseArticleVersionResource::collection($article->versions);
    }

    public function showVersion(Request $request, string $id, int $versionNumber)
    {
        Gate::authorize('knowledge_base.view');

        $article = KnowledgeBaseArticle::findOrFail($id);

        if ($article->visibility === 'it_internal' && ! $request->user()->hasPermission('knowledge_base.view_it_internal')) {
            abort(403);
        }

        $version = KnowledgeBaseArticleVersion::where('article_id', $article->id)
            ->where('version_number', $versionNumber)
            ->firstOrFail();

        return new KnowledgeBaseArticleVersionResource($version);
    }

    public function restoreVersion(Request $request, string $id, int $versionNumber)
    {
        Gate::authorize('knowledge_base.restore_version');

        $article = KnowledgeBaseArticle::findOrFail($id);
        $article = $this->versionService->restoreVersion($article, $versionNumber, $request->user());

        return new KnowledgeBaseArticleDetailResource($article);
    }

    public function activity(Request $request, string $id)
    {
        Gate::authorize('knowledge_base.view_activity');

        $article = KnowledgeBaseArticle::findOrFail($id);

        return KnowledgeBaseActivityResource::collection($article->activityLogs);
    }

    public function related(Request $request, string $id)
    {
        Gate::authorize('knowledge_base.view');

        $article = KnowledgeBaseArticle::findOrFail($id);

        // Fetch related articles based on category or application
        $query = KnowledgeBaseArticle::where('id', '!=', $article->id)
            ->where('status', 'published');

        if (! $request->user()->hasPermission('knowledge_base.view_it_internal')) {
            $query->whereIn('visibility', ['business_internal', 'all_authenticated']);
        } else {
            $query->whereIn('visibility', ['it_internal', 'business_internal', 'all_authenticated']);
        }

        if ($article->category_id) {
            $query->where('category_id', $article->category_id);
        }

        $related = $query->take(5)->get();

        return KnowledgeBaseArticleResource::collection($related);
    }
}
