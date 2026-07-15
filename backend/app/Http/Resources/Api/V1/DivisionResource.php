<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DivisionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'code' => $this->code, 'name' => $this->name, 'description' => $this->description, 'parent_id' => $this->parent_id, 'parent' => $this->whenLoaded('parent', fn () => ['id' => $this->parent->id, 'code' => $this->parent->code, 'name' => $this->parent->name]), 'is_active' => $this->is_active];
    }
}
