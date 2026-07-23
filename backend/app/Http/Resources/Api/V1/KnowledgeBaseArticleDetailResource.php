<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class KnowledgeBaseArticleDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();

        // Check if user has permission to see technical/internal ticket relation detail
        $canSeeInternalDetails = $user && $user->hasPermission('knowledge_base.view_it_internal');

        return [
            'id' => $this->id,
            'article_number' => $this->article_number,
            'slug' => $this->slug,
            'title' => $this->title,
            'summary' => $this->summary,
            'content' => $this->content, // sanitized server-side
            'status' => $this->status,
            'visibility' => $this->visibility,
            'category' => $this->category ? [
                'id' => $this->category->id,
                'name' => $this->category->name,
            ] : null,
            'application' => $this->application ? [
                'id' => $this->application->id,
                'name' => $this->application->name,
            ] : null,
            'author' => [
                'id' => $this->author->id,
                'name' => $this->author->name,
            ],
            'reviewer' => $this->reviewer ? [
                'id' => $this->reviewer->id,
                'name' => $this->reviewer->name,
            ] : null,
            'rejection_reason' => $this->when($user && ($articleAuthor = $this->author_id === $user->id || $user->hasPermission('knowledge_base.review')), $this->rejection_reason),
            'current_version' => $this->current_version,
            'view_count' => $this->view_count,
            'helpful_count' => $this->helpful_count,
            'not_helpful_count' => $this->not_helpful_count,
            'helpful_ratio' => ($this->helpful_count + $this->not_helpful_count) > 0
                ? round($this->helpful_count / ($this->helpful_count + $this->not_helpful_count), 2)
                : null,
            'published_at' => $this->published_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'tags' => KnowledgeBaseTagResource::collection($this->tags),
            'tickets' => $this->when($canSeeInternalDetails, function () {
                return $this->tickets->map(fn ($ticket) => [
                    'id' => $ticket->id,
                    'ticket_number' => $ticket->ticket_number,
                    'title' => $ticket->title,
                    'relation_type' => $ticket->pivot->relation_type,
                ]);
            }),
        ];
    }
}
