<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketCategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'code' => $this->code, 'name' => $this->name, 'type' => $this->type, 'description' => $this->description, 'is_active' => $this->is_active];
    }
}
