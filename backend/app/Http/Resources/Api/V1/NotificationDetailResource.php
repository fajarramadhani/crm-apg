<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->data['type'] ?? '',
            'severity' => $this->data['severity'] ?? 'info',
            'title' => $this->data['title'] ?? '',
            'message' => $this->data['message'] ?? '',
            'ticket' => [
                'id' => $this->data['ticket_id'] ?? null,
                'number' => $this->data['ticket_number'] ?? null,
            ],
            'action_url' => $this->data['action_url'] ?? null,
            'is_read' => $this->read_at !== null,
            'is_archived' => false, // To be handled
            'metadata' => $this->data['metadata'] ?? [],
            'created_at' => $this->created_at?->toIso8601String(),
            'read_at' => $this->read_at?->toIso8601String(),
        ];
    }
}
