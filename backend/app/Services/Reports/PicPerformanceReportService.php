<?php

namespace App\Services\Reports;

use App\Models\Ticket;
use App\Models\TicketAssignment;
use App\Models\TicketQaDefect;
use App\Models\TicketUatFinding;
use App\Models\TicketWorklog;
use Carbon\Carbon;

class PicPerformanceReportService extends BaseReportService
{
    public function getSummary(int $picId, Carbon $dateFrom, Carbon $dateTo): array
    {
        $activeAssignments = TicketAssignment::query()
            ->where('assigned_to', $picId)
            ->where('is_current', true)
            ->whereNull('ended_at')
            ->count();

        $completedAssignments = TicketAssignment::query()
            ->where('assigned_to', $picId)
            ->whereNotNull('ended_at')
            ->whereBetween('ended_at', [$dateFrom, $dateTo])
            ->count();

        $closedTickets = Ticket::query()
            ->where('current_assignee_id', $picId)
            ->whereNotNull('closed_at')
            ->whereBetween('closed_at', [$dateFrom, $dateTo])
            ->count();

        $worklogs = TicketWorklog::query()
            ->where('user_id', $picId)
            ->whereBetween('work_date', [$dateFrom, $dateTo])
            ->sum('duration_minutes');

        $totalWorklogHours = round($worklogs / 60, 2);

        $qaDefects = TicketQaDefect::query()
            ->whereHas('ticket', function ($q) use ($picId) {
                $q->where('current_assignee_id', $picId);
            })
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->count();

        $uatFindings = TicketUatFinding::query()
            ->whereHas('ticket', function ($q) use ($picId) {
                $q->where('current_assignee_id', $picId);
            })
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->count();

        return [
            'active_assignments' => $activeAssignments,
            'completed_assignments' => $completedAssignments,
            'closed_tickets' => $closedTickets,
            'qa_defect_count' => $qaDefects,
            'uat_finding_count' => $uatFindings,
            'total_worklog_hours' => $totalWorklogHours,
        ];
    }
}
