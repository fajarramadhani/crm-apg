<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class KnowledgeBaseArticleVersionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'article_id' => $this->article_id,
            'version_number' => $this->version_number,
            'title' => $this->title,
            'summary' => $this->summary,
            'content' => $this->content,
            'visibility' => $this->visibility,
            'category' => $this->category ? [
                'id' => $this->category->id,
                'name' => $this->category->name,
            ] : null,
            'application' => $this->application ? [
                'id' => $this->application->id,
                'name' => $this->application->name,
            ] : null,
            'change_summary' => $this->change_summary,
            'creator' => [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ],
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
