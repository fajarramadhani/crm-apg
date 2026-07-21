<?php

namespace App\Services\Reports;

use App\Models\TicketDeployment;
use App\Models\TicketPostReleaseIncident;
use App\Models\TicketQaDefect;
use App\Models\TicketRollbackExecution;
use App\Models\TicketUatFinding;
use Carbon\Carbon;

class TicketQualityReportService extends BaseReportService
{
    public function getSummary(array $filters, Carbon $dateFrom, Carbon $dateTo): array
    {
        $qaDefects = TicketQaDefect::query()
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->whereHas('ticket', function ($q) use ($filters) {
                $this->applyFilters($q, $filters, 'created_at', null, null);
            })->count();

        $uatFindings = TicketUatFinding::query()
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->whereHas('ticket', function ($q) use ($filters) {
                $this->applyFilters($q, $filters, 'created_at', null, null);
            })->count();

        $deployments = TicketDeployment::query()
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->whereHas('ticket', function ($q) use ($filters) {
                $this->applyFilters($q, $filters, 'created_at', null, null);
            });

        $totalDeployments = $deployments->count();
        $successDeployments = (clone $deployments)->where('status', 'completed')->count();

        $rollbacks = TicketRollbackExecution::query()
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->whereHas('ticket', function ($q) use ($filters) {
                $this->applyFilters($q, $filters, 'created_at', null, null);
            })->count();

        $incidents = TicketPostReleaseIncident::query()
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->whereHas('ticket', function ($q) use ($filters) {
                $this->applyFilters($q, $filters, 'created_at', null, null);
            })->count();

        return [
            'qa_defect_count' => $qaDefects,
            'uat_finding_count' => $uatFindings,
            'total_deployments' => $totalDeployments,
            'success_deployments' => $successDeployments,
            'rollback_count' => $rollbacks,
            'post_release_incident_count' => $incidents,
            'rollback_rate' => $totalDeployments > 0 ? round(($rollbacks / $totalDeployments) * 100, 2) : null,
            'defect_leakage_rate' => $successDeployments > 0 ? round(($incidents / $successDeployments) * 100, 2) : null,
        ];
    }
}
