<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\OperationalAlertSummaryResource;
use App\Models\TicketSlaAlert;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ExecutiveAlertController extends Controller
{
    public function summary(Request $request)
    {
        Gate::authorize('alert.executive.view_summary');

        // Calculate aggregates
        $slaCriticalCount = TicketSlaAlert::where('alert_level', 'critical')->whereNull('resolved_at')->count();
        $slaBreachedCount = TicketSlaAlert::where('alert_level', 'breached')->whereNull('resolved_at')->count();

        // Returning the data via Resource
        $data = [
            'sla_critical_count' => $slaCriticalCount,
            'sla_breached_count' => $slaBreachedCount,
            'high_risk_application_count' => 0, // Placeholder
            'critical_deployment_failure_count' => 0, // Placeholder
            'post_release_incident_count' => 0, // Placeholder
            'alert_trend' => [],
        ];

        return new OperationalAlertSummaryResource($data);
    }
}
