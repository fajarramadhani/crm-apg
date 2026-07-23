<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\KnowledgeBaseArticleDetailResource;
use App\Models\KnowledgeBaseArticle;
use App\Services\KnowledgeBaseReviewService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class KnowledgeBaseReviewController extends Controller
{
    public function __construct(private KnowledgeBaseReviewService $reviewService) {}

    public function publish(Request $request, string $id)
    {
        Gate::authorize('knowledge_base.publish');

        $article = KnowledgeBaseArticle::findOrFail($id);

        $validated = $request->validate([
            'change_summary' => 'nullable|string|max:500',
        ]);

        $this->reviewService->publish($article, $request->user(), $validated['change_summary'] ?? null);

        return new KnowledgeBaseArticleDetailResource($article->fresh());
    }

    public function reject(Request $request, string $id)
    {
        Gate::authorize('knowledge_base.review');

        $article = KnowledgeBaseArticle::findOrFail($id);

        $validated = $request->validate([
            'reason' => 'required|string|min:3|max:1000',
        ]);

        $this->reviewService->reject($article, $request->user(), $validated['reason']);

        return new KnowledgeBaseArticleDetailResource($article->fresh());
    }
}
