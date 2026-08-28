<?php

namespace App\Services\Reports;

use App\Models\TicketDeployment;
use Carbon\Carbon;

class DeploymentReportService extends BaseReportService
{
    public function getSummary(array $filters, Carbon $dateFrom, Carbon $dateTo): array
    {
        $baseQuery = TicketDeployment::query()
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->whereHas('ticket', function ($q) use ($filters) {
                $this->applyFilters($q, $filters, 'created_at', null, null);
            });

        $driver = $baseQuery->getQuery()->getConnection()->getDriverName();

        $summary = (clone $baseQuery)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN status = 'scheduled' THEN 1 ELSE 0 END) as scheduled")
            ->selectRaw("SUM(CASE WHEN status IN ('in_progress', 'succeeded', 'failed', 'cancelled') THEN 1 ELSE 0 END) as started")
            ->selectRaw("SUM(CASE WHEN status = 'succeeded' THEN 1 ELSE 0 END) as succeeded")
            ->selectRaw("SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed")
            ->selectRaw("SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled")
            ->selectRaw($this->averageDurationExpression($driver))
            ->first();

        $durations = $this->extractDurations(clone $baseQuery, $driver);

        $started = (int) $summary->started;
        $succeeded = (int) $summary->succeeded;
        $failed = (int) $summary->failed;

        return [
            'deployments_scheduled' => (int) $summary->scheduled,
            'deployments_started' => $started,
            'deployments_succeeded' => $succeeded,
            'deployments_failed' => $failed,
            'deployments_cancelled' => (int) $summary->cancelled,
            'deployment_success_rate' => $started > 0 ? round(($succeeded / $started) * 100, 2) : null,
            'deployment_failure_rate' => $started > 0 ? round(($failed / $started) * 100, 2) : null,
            'average_deployment_duration_minutes' => $summary->avg_duration_minutes !== null ? round((float) $summary->avg_duration_minutes, 2) : null,
            'median_deployment_duration_minutes' => $this->calculateMedian($durations),
        ];
    }

    /**
     * Portable average duration in minutes using driver-aware SQL.
     * SQLite: average of (julianday(end) - julianday(start)) * 24 * 60
     * MySQL: AVG(TIMESTAMPDIFF(MINUTE, start, end)) with NULL handling
     */
    private function averageDurationExpression(string $driver): string
    {
        $start = 'ticket_deployments.actual_start_at';
        $end = 'ticket_deployments.actual_end_at';

        if ($driver === 'sqlite') {
            return "AVG(CASE WHEN {$start} IS NOT NULL AND {$end} IS NOT NULL THEN (julianday({$end}) - julianday({$start})) * 24 * 60 END) as avg_duration_minutes";
        }

        return "AVG(CASE WHEN {$start} IS NOT NULL AND {$end} IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, {$start}, {$end}) END) as avg_duration_minutes";
    }

    /**
     * Extract duration values from the query for PHP median calculation.
     * We use a raw SQL to get per-row durations, then calculate median in PHP.
     */
    private function extractDurations($query, string $driver): array
    {
        $start = 'ticket_deployments.actual_start_at';
        $end = 'ticket_deployments.actual_end_at';

        $expr = $driver === 'sqlite'
            ? "(julianday({$end}) - julianday({$start})) * 24 * 60"
            : "TIMESTAMPDIFF(MINUTE, {$start}, {$end})";

        return (clone $query)
            ->whereNotNull('actual_start_at')
            ->whereNotNull('actual_end_at')
            ->selectRaw("{$expr} as duration_min")
            ->pluck('duration_min')
            ->map(fn ($value) => round((float) $value, 2))
            ->all();
    }
}
