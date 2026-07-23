<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\KnowledgeBaseFeedbackResource;
use App\Models\KnowledgeBaseArticle;
use App\Services\KnowledgeBaseFeedbackService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class KnowledgeBaseFeedbackController extends Controller
{
    public function __construct(private KnowledgeBaseFeedbackService $feedbackService) {}

    public function store(Request $request, string $articleId)
    {
        Gate::authorize('knowledge_base.feedback');

        $article = KnowledgeBaseArticle::findOrFail($articleId);

        $validated = $request->validate([
            'is_helpful' => 'required|boolean',
            'comment' => 'nullable|string|max:500',
        ]);

        $feedback = $this->feedbackService->submitFeedback(
            $article,
            $request->user(),
            $validated['is_helpful'],
            $validated['comment'] ?? null
        );

        return new KnowledgeBaseFeedbackResource($feedback);
    }
}
