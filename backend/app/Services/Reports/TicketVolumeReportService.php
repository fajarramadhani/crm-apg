<?php

namespace App\Services\Reports;

use App\Models\Ticket;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class TicketVolumeReportService extends BaseReportService
{
    public function getSummary(array $filters, Carbon $dateFrom, Carbon $dateTo): array
    {
        $createdQuery = Ticket::query();
        $createdQuery = $this->applyFilters($createdQuery, $filters, 'submitted_at', $dateFrom, $dateTo);
        $createdCount = $createdQuery->count();

        $closedQuery = Ticket::query()->whereNotNull('closed_at');
        $closedQuery = $this->applyFilters($closedQuery, $filters, 'closed_at', $dateFrom, $dateTo);
        $closedCount = $closedQuery->count();

        $completedQuery = Ticket::query()->whereNotNull('approved_for_release_at'); // As completed proxy
        $completedQuery = $this->applyFilters($completedQuery, $filters, 'approved_for_release_at', $dateFrom, $dateTo);
        $completedCount = $completedQuery->count();

        $reopenedCount = DB::table('ticket_status_histories')
            ->where('to_status', 'reopened')
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->whereIn('ticket_id', function ($q) use ($filters) {
                $q->select('id')->from('tickets');
                if (isset($filters['division_id'])) {
                    $q->where('division_id', $filters['division_id']);
                }
                if (isset($filters['application_id'])) {
                    $q->where('application_id', $filters['application_id']);
                }
            })
            ->count();

        $backlogStartCount = Ticket::query()
            ->whereNotNull('submitted_at')
            ->where('submitted_at', '<', $dateFrom)
            ->where(function ($q) use ($dateFrom) {
                $q->whereNull('closed_at')->orWhere('closed_at', '>=', $dateFrom);
            });
        $backlogStartCount = $this->applyFilters($backlogStartCount, $filters, 'submitted_at', null, null)->count();

        $backlogEndCount = Ticket::query()
            ->whereNotNull('submitted_at')
            ->where('submitted_at', '<=', $dateTo)
            ->where(function ($q) use ($dateTo) {
                $q->whereNull('closed_at')->orWhere('closed_at', '>', $dateTo);
            });
        $backlogEndCount = $this->applyFilters($backlogEndCount, $filters, 'submitted_at', null, null)->count();

        return [
            'tickets_created' => $createdCount,
            'tickets_completed' => $completedCount,
            'tickets_closed' => $closedCount,
            'tickets_reopened' => $reopenedCount,
            'backlog_start' => $backlogStartCount,
            'backlog_end' => $backlogEndCount,
            'inflow' => $createdCount,
            'outflow' => $closedCount,
            'net_backlog_change' => $createdCount - $closedCount,
        ];
    }

    public function getAging(array $filters): array
    {
        $query = Ticket::query()->whereNull('closed_at')->whereNotNull('submitted_at');
        $query = $this->applyFilters($query, $filters, 'submitted_at');

        $tickets = $query->get(['id', 'submitted_at']);
        $ages = [];
        $oldest = null;

        foreach ($tickets as $ticket) {
            $age = $ticket->submitted_at->diffInDays(now());
            $ages[] = $age;
            if ($oldest === null || $age > $oldest) {
                $oldest = $age;
            }
        }

        return [
            'average_age_days' => count($ages) > 0 ? round(array_sum($ages) / count($ages), 1) : null,
            'oldest_ticket_days' => $oldest,
        ];
    }
}
