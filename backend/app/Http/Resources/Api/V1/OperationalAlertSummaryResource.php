<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OperationalAlertSummaryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'sla_critical_count' => $this['sla_critical_count'] ?? 0,
            'sla_breached_count' => $this['sla_breached_count'] ?? 0,
            'high_risk_application_count' => $this['high_risk_application_count'] ?? 0,
            'critical_deployment_failure_count' => $this['critical_deployment_failure_count'] ?? 0,
            'post_release_incident_count' => $this['post_release_incident_count'] ?? 0,
            'alert_trend' => $this['alert_trend'] ?? [], // Month-by-month trends
        ];
    }
}
