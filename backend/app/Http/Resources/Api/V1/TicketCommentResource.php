<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketCommentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'type' => $this->type, 'comment' => $this->comment, 'is_internal' => $this->is_internal, 'user' => $this->whenLoaded('user', fn () => ['id' => $this->user->id, 'name' => $this->user->name]), 'created_at' => $this->created_at?->toISOString()];
    }
}
