<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ReportFilterRequest;
use App\Services\Reports\TicketSlaReportService;
use App\Services\Reports\TicketVolumeReportService;
use Illuminate\Http\JsonResponse;

class SupervisorReportController extends Controller
{
    public function __construct(
        private TicketVolumeReportService $volumeService,
        private TicketSlaReportService $slaService,
    ) {}

    public function summary(ReportFilterRequest $request): JsonResponse
    {
        $this->authorize('report.supervisor.view');

        $filters = $request->validated();
        // Supervisor only sees their own division
        $filters['division_id'] = auth()->user()->division_id;

        $dateFrom = $request->getValidDateFrom();
        $dateTo = $request->getValidDateTo();

        return response()->json([
            'data' => [
                'volume' => $this->volumeService->getSummary($filters, $dateFrom, $dateTo),
                'sla' => $this->slaService->getSummary($filters, $dateFrom, $dateTo),
                'aging' => $this->volumeService->getAging($filters),
            ],
            'filters' => $filters,
            'period' => [
                'date_from' => $dateFrom->toIso8601String(),
                'date_to' => $dateTo->toIso8601String(),
            ],
        ]);
    }
}
