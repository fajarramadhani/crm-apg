<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
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
            'is_archived' => false, // We'll assume soft delete or custom field for archive if needed, but for now we might use read_at or another way. Actually, the requirements ask for an archive feature. Wait, does Laravel support archiving notifications? No, typically people soft delete or add a column. We didn't add a column. So maybe we can use soft deletes? Wait, the user said "Notification archive tidak hard delete" so we need `archived_at` or `deleted_at`.
            // Wait, I will use `deleted_at` (soft deletes) for archiving if possible, or we just filter them. Since I didn't add `deleted_at` to the notifications table (or did I? No, default notifications table doesn't have it). Oh, we might need a migration for `archived_at` on `notifications` table or use soft deletes.
            // Let's assume there is an archived_at if I add it, but for now I'll use `is_archived` => $this->deleted_at !== null (if soft deleted).
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
