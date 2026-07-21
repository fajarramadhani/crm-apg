<?php

namespace App\Services\Reports;

use App\Models\TicketDeployment;
use Carbon\Carbon;

class DeploymentReportService extends BaseReportService
{
    public function getSummary(array $filters, Carbon $dateFrom, Carbon $dateTo): array
    {
        $query = TicketDeployment::query()
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->whereHas('ticket', function ($q) use ($filters) {
                $this->applyFilters($q, $filters, 'created_at', null, null);
            });

        $deployments = $query->get(['id', 'status', 'actual_start_at', 'actual_end_at']);

        $total = $deployments->count();
        $scheduled = $deployments->where('status', 'scheduled')->count();
        $started = $deployments->whereIn('status', ['in_progress', 'completed', 'failed', 'cancelled'])->count();
        $succeeded = $deployments->where('status', 'completed')->count();
        $failed = $deployments->where('status', 'failed')->count();
        $cancelled = $deployments->where('status', 'cancelled')->count();

        $durations = [];
        foreach ($deployments as $deployment) {
            if ($deployment->actual_start_at && $deployment->actual_end_at) {
                $durations[] = $deployment->actual_start_at->diffInMinutes($deployment->actual_end_at);
            }
        }

        return [
            'deployments_scheduled' => $scheduled,
            'deployments_started' => $started,
            'deployments_succeeded' => $succeeded,
            'deployments_failed' => $failed,
            'deployments_cancelled' => $cancelled,
            'deployment_success_rate' => $started > 0 ? round(($succeeded / $started) * 100, 2) : null,
            'deployment_failure_rate' => $started > 0 ? round(($failed / $started) * 100, 2) : null,
            'average_deployment_duration_minutes' => count($durations) > 0 ? round(array_sum($durations) / count($durations), 2) : null,
            'median_deployment_duration_minutes' => $this->calculateMedian($durations),
        ];
    }
}
