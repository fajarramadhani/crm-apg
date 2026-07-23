<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class KnowledgeBaseActivityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $isItRole = $user && $user->hasPermission('knowledge_base.view_activity');

        return [
            'id' => $this->id,
            'article_id' => $this->article_id,
            'actor' => [
                'id' => $this->actor->id,
                'name' => $this->actor->name,
            ],
            'action' => $this->action,
            'from_status' => $this->from_status,
            'to_status' => $this->to_status,
            'metadata' => $this->when($isItRole, $this->metadata),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
