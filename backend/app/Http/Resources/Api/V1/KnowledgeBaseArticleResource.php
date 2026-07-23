<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class KnowledgeBaseArticleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'article_number' => $this->article_number,
            'slug' => $this->slug,
            'title' => $this->title,
            'summary' => $this->summary,
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
            'tags' => KnowledgeBaseTagResource::collection($this->whenLoaded('tags')),
        ];
    }
}
