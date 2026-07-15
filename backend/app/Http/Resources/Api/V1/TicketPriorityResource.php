<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketPriorityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'key' => $this->key, 'name' => $this->name, 'level' => $this->level, 'description' => $this->description, 'is_active' => $this->is_active];
    }
}
