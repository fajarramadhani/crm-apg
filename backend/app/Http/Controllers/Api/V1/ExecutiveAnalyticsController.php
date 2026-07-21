<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ReportFilterRequest;
use App\Services\Reports\DeploymentReportService;
use App\Services\Reports\TicketQualityReportService;
use App\Services\Reports\TicketReportExportService;
use App\Services\Reports\TicketSlaReportService;
use App\Services\Reports\TicketVolumeReportService;
use Illuminate\Http\JsonResponse;

class ExecutiveAnalyticsController extends Controller
{
    public function __construct(
        private TicketVolumeReportService $volumeService,
        private TicketSlaReportService $slaService,
        private TicketQualityReportService $qualityService,
        private DeploymentReportService $deploymentService,
        private TicketReportExportService $exportService,
    ) {}

    public function summary(ReportFilterRequest $request): JsonResponse
    {
        $this->authorize('report.executive.view');

        $filters = $request->validated();
        $dateFrom = $request->getValidDateFrom();
        $dateTo = $request->getValidDateTo();

        return response()->json([
            'data' => [
                'volume' => $this->volumeService->getSummary($filters, $dateFrom, $dateTo),
                'sla' => $this->slaService->getSummary($filters, $dateFrom, $dateTo),
                'quality' => $this->qualityService->getSummary($filters, $dateFrom, $dateTo),
                'deployment' => $this->deploymentService->getSummary($filters, $dateFrom, $dateTo),
            ],
            'filters' => $filters,
            'period' => [
                'date_from' => $dateFrom->toIso8601String(),
                'date_to' => $dateTo->toIso8601String(),
            ],
        ]);
    }

    public function export(ReportFilterRequest $request)
    {
        $this->authorize('report.executive.export');

        $format = request('format', 'csv');
        if ($format !== 'csv') {
            return response()->json(['message' => 'Export format not supported. Only csv is supported.'], 422);
        }

        return $this->exportService->exportCsv('executive_summary_report.csv', collect([['status' => 'success', 'format' => 'csv']]));
    }
}
