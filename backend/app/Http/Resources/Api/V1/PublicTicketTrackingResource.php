<?php

namespace App\Http\Resources\Api\V1;

use App\Services\PublicTicketActionService;
use App\Services\PublicTicketStatusMapper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class PublicTicketTrackingResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $mapper = app(PublicTicketStatusMapper::class);
        $timeline = [];

        foreach ($this->histories as $history) {
            $milestone = $mapper->map($history->to_status);
            if (($timeline[array_key_last($timeline)]['code'] ?? null) === $milestone['code']) {
                continue;
            }
            $timeline[] = [...$milestone, 'occurred_at' => $history->created_at?->toISOString()];
        }
        if ($timeline !== []) {
            $timeline[array_key_last($timeline)]['is_current'] = true;
        }

        $updates = $this->comments->map(fn ($comment): array => [
            'message' => $this->plainText($comment->comment),
            'occurred_at' => $comment->created_at?->toISOString(),
        ])->values()->all();
        $lastUpdatedAt = collect([
            $this->updated_at,
            $this->histories->max('created_at'),
            $this->comments->max('created_at'),
        ])->filter()->max();

        return [
            'ticket_number' => $this->ticket_number,
            'title' => $this->title,
            'branch' => $this->branch?->name,
            'category' => $this->category?->name,
            'submitted_at' => $this->submitted_at?->toISOString(),
            'status' => $mapper->map($this->current_workflow_stage ?: $this->status),
            'timeline' => $timeline,
            'requester_updates' => $updates,
            'last_updated_at' => $lastUpdatedAt?->toISOString(),
            'action_available' => app(PublicTicketActionService::class)->available($this->resource)['type'] !== null,
        ];
    }

    private function plainText(string $value): string
    {
        $value = preg_replace('/<(script|style)\b[^>]*>.*?<\/\1\s*>/is', '', $value) ?? '';
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';
        $value = preg_replace('/\s+/u', ' ', $value) ?? '';

        return Str::limit(trim($value), (int) config('public_tracking.update_text_limit', 2000), '');
    }
}
