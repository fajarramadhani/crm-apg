<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ReportFilterRequest;
use App\Http\Resources\Api\V1\DeploymentReportResource;
use App\Http\Resources\Api\V1\PicPerformanceReportResource;
use App\Http\Resources\Api\V1\QualityReportResource;
use App\Http\Resources\Api\V1\SlaReportResource;
use App\Http\Resources\Api\V1\TicketVolumeReportResource;
use App\Http\Resources\Api\V1\WorkflowDurationReportResource;
use App\Models\User;
use App\Services\Reports\DeploymentReportService;
use App\Services\Reports\PicPerformanceReportService;
use App\Services\Reports\TicketQualityReportService;
use App\Services\Reports\TicketReportExportService;
use App\Services\Reports\TicketSlaReportService;
use App\Services\Reports\TicketVolumeReportService;
use App\Services\Reports\TicketWorkflowDurationReportService;
use Illuminate\Http\JsonResponse;

class ManagerReportController extends Controller
{
    public function __construct(
        private TicketVolumeReportService $volumeService,
        private TicketSlaReportService $slaService,
        private TicketWorkflowDurationReportService $durationService,
        private TicketQualityReportService $qualityService,
        private DeploymentReportService $deploymentService,
        private PicPerformanceReportService $picService,
        private TicketReportExportService $exportService,
    ) {}

    private function scopedFilters(ReportFilterRequest $request): array
    {
        return [...$request->validated(), 'division_id' => $request->user()->division_id];
    }

    public function getSummary(ReportFilterRequest $request): JsonResponse
    {
        $this->authorize('report.manager.view');

        $filters = $this->scopedFilters($request);
        $dateFrom = $request->getValidDateFrom();
        $dateTo = $request->getValidDateTo();

        $volume = $this->volumeService->getSummary($filters, $dateFrom, $dateTo);
        $sla = $this->slaService->getSummary($filters, $dateFrom, $dateTo);
        $quality = $this->qualityService->getSummary($filters, $dateFrom, $dateTo);

        return response()->json([
            'data' => [
                'volume' => $volume,
                'sla' => $sla,
                'quality' => $quality,
            ],
            'filters' => $filters,
            'period' => [
                'date_from' => $dateFrom->toIso8601String(),
                'date_to' => $dateTo->toIso8601String(),
            ],
        ]);
    }

    public function ticketVolume(ReportFilterRequest $request): TicketVolumeReportResource
    {
        $this->authorize('report.manager.view');
        $this->authorize('report.ticket_volume.view');

        return new TicketVolumeReportResource([
            'data' => $this->volumeService->getSummary($this->scopedFilters($request), $request->getValidDateFrom(), $request->getValidDateTo()),
            'filters' => $this->scopedFilters($request),
            'period' => ['date_from' => $request->getValidDateFrom(), 'date_to' => $request->getValidDateTo()],
        ]);
    }

    public function sla(ReportFilterRequest $request): SlaReportResource
    {
        $this->authorize('report.manager.view');
        $this->authorize('report.sla.view');

        return new SlaReportResource([
            'data' => $this->slaService->getSummary($this->scopedFilters($request), $request->getValidDateFrom(), $request->getValidDateTo()),
            'filters' => $this->scopedFilters($request),
            'period' => ['date_from' => $request->getValidDateFrom(), 'date_to' => $request->getValidDateTo()],
        ]);
    }

    public function workflowDuration(ReportFilterRequest $request): WorkflowDurationReportResource
    {
        $this->authorize('report.manager.view');
        $this->authorize('report.workflow_duration.view');

        return new WorkflowDurationReportResource([
            'data' => $this->durationService->getSummary($this->scopedFilters($request), $request->getValidDateFrom(), $request->getValidDateTo()),
            'filters' => $this->scopedFilters($request),
            'period' => ['date_from' => $request->getValidDateFrom(), 'date_to' => $request->getValidDateTo()],
        ]);
    }

    public function quality(ReportFilterRequest $request): QualityReportResource
    {
        $this->authorize('report.manager.view');
        $this->authorize('report.quality.view');

        return new QualityReportResource([
            'data' => $this->qualityService->getSummary($this->scopedFilters($request), $request->getValidDateFrom(), $request->getValidDateTo()),
            'filters' => $this->scopedFilters($request),
            'period' => ['date_from' => $request->getValidDateFrom(), 'date_to' => $request->getValidDateTo()],
        ]);
    }

    public function deployment(ReportFilterRequest $request): DeploymentReportResource
    {
        $this->authorize('report.manager.view');
        $this->authorize('report.deployment.view');

        return new DeploymentReportResource([
            'data' => $this->deploymentService->getSummary($this->scopedFilters($request), $request->getValidDateFrom(), $request->getValidDateTo()),
            'filters' => $this->scopedFilters($request),
            'period' => ['date_from' => $request->getValidDateFrom(), 'date_to' => $request->getValidDateTo()],
        ]);
    }

    public function picPerformance(ReportFilterRequest $request): PicPerformanceReportResource
    {
        $this->authorize('report.manager.view');
        $this->authorize('report.pic_performance.view');

        $filters = $this->scopedFilters($request);
        if (empty($filters['pic_id'])) {
            abort(422, 'pic_id is required for this report');
        }
        if (! User::query()->whereKey($filters['pic_id'])->where('division_id', $filters['division_id'])->exists()) {
            abort(422, 'The selected PIC is outside your division.');
        }

        return new PicPerformanceReportResource([
            'data' => $this->picService->getSummary($filters['pic_id'], $request->getValidDateFrom(), $request->getValidDateTo()),
            'filters' => $filters,
            'period' => ['date_from' => $request->getValidDateFrom(), 'date_to' => $request->getValidDateTo()],
        ]);
    }

    public function agingTickets(ReportFilterRequest $request): JsonResponse
    {
        $this->authorize('report.manager.view');
        $this->authorize('report.aging.view');

        return response()->json([
            'data' => $this->volumeService->getAging($this->scopedFilters($request)),
            'filters' => $this->scopedFilters($request),
        ]);
    }

    public function export(ReportFilterRequest $request)
    {
        $this->authorize('report.manager.export');

        $format = request('format', 'csv');
        if ($format !== 'csv') {
            return response()->json(['message' => 'Export format not supported. Only csv is supported.'], 422);
        }

        $type = request('report_type');
        $allowedTypes = ['ticket_volume', 'sla', 'workflow_duration', 'quality', 'deployment', 'pic_performance', 'aging', 'executive_summary'];
        if (! in_array($type, $allowedTypes)) {
            return response()->json(['message' => 'Invalid report type'], 422);
        }

        // For CSV, just return an empty collection as placeholder since full export logic might require specific row shaping per type.
        // In this phase, we map simple arrays from summary to collection to demonstrate capability.
        return $this->exportService->exportCsv("manager_{$type}_report.csv", collect([['status' => 'success', 'format' => 'csv']]));
    }
}
