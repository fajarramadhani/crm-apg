<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApplicationModuleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'application_id' => $this->application_id, 'code' => $this->code, 'name' => $this->name, 'description' => $this->description, 'is_active' => $this->is_active];
    }
}
