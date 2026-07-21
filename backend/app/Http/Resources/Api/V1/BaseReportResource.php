<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class BaseReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->resource['data'] ?? [],
            'filters' => $this->resource['filters'] ?? [],
            'period' => [
                'date_from' => $this->resource['period']['date_from'] ?? null,
                'date_to' => $this->resource['period']['date_to'] ?? null,
                'timezone' => config('app.timezone'),
            ],
            'scope' => $this->resource['scope'] ?? [],
            'generated_at' => now()->toIso8601String(),
            'request_id' => $request->header('X-Request-Id', (string) Str::uuid()),
        ];
    }
}
