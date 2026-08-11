<?php

namespace App\Http\Resources\Api\V1;

use App\Models\PublicTicketTrackingToken;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicTrackingAccessResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var PublicTicketTrackingToken|null $record */
        $record = $this->resource['record'];
        $state = $this->resource['state'];

        return [
            'submission_source' => 'public_form',
            'state' => $state,
            'created_at' => $record?->created_at?->toISOString(),
            'expires_at' => $record?->expires_at?->toISOString(),
            'last_used_at' => $record?->last_used_at?->toISOString(),
            'revoked_at' => $record?->revoked_at?->toISOString(),
            'tracking_url' => $state === 'active' ? $this->resource['tracking_url'] : null,
            'link_recoverable' => $state === 'active' && is_string($this->resource['tracking_url']),
            'can_issue' => $state !== 'active',
            'can_rotate' => $state === 'active',
            'can_revoke' => $state === 'active',
        ];
    }
}
