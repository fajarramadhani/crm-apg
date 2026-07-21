<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;

class ExecutiveAnalyticsResource extends BaseReportResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return parent::toArray($request);
    }
}
