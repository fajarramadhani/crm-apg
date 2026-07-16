<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketAttachmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'original_name' => $this->original_name, 'mime_type' => $this->mime_type, 'size' => $this->size, 'category' => $this->category, 'visibility' => $this->visibility ?? 'internal', 'uploaded_by' => $this->uploaded_by, 'created_at' => $this->created_at?->toISOString()];
    }
}
