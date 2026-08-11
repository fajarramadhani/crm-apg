<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'role' => $this->whenLoaded('role', fn () => $this->role ? [
                'id' => $this->role->id,
                'key' => $this->role->key,
                'name' => $this->role->name,
            ] : null),
            'division' => $this->whenLoaded('division', fn () => $this->division ? [
                'id' => $this->division->id,
                'code' => $this->division->code,
                'name' => $this->division->name,
            ] : null),
            'branch' => $this->whenLoaded('branch', fn () => $this->branch ? [
                'id' => $this->branch->id,
                'code' => $this->branch->code,
                'name' => $this->branch->name,
            ] : null),
            'office' => $this->whenLoaded('office', fn () => $this->office ? [
                'id' => $this->office->id,
                'name' => $this->office->name,
                'office_type' => $this->office->office_type,
            ] : null),
            'is_active' => $this->is_active,
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
