<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApplicationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'code' => $this->code, 'name' => $this->name, 'description' => $this->description, 'owner_division_id' => $this->owner_division_id, 'owner_division' => $this->whenLoaded('ownerDivision', fn () => ['id' => $this->ownerDivision->id, 'code' => $this->ownerDivision->code, 'name' => $this->ownerDivision->name]), 'modules' => ApplicationModuleResource::collection($this->whenLoaded('modules')), 'is_active' => $this->is_active];
    }
}
