<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ReportFilterRequest;
use App\Services\Reports\PicPerformanceReportService;
use App\Services\Reports\TicketReportExportService;
use Illuminate\Http\JsonResponse;

class PicPerformanceController extends Controller
{
    public function __construct(
        private PicPerformanceReportService $picService,
        private TicketReportExportService $exportService,
    ) {}

    public function summary(ReportFilterRequest $request): JsonResponse
    {
        $this->authorize('report.pic.view_own');

        $dateFrom = $request->getValidDateFrom();
        $dateTo = $request->getValidDateTo();

        // PIC only sees own performance
        return response()->json([
            'data' => $this->picService->getSummary(auth()->id(), $dateFrom, $dateTo),
            'period' => [
                'date_from' => $dateFrom->toIso8601String(),
                'date_to' => $dateTo->toIso8601String(),
            ],
        ]);
    }

    public function export(ReportFilterRequest $request)
    {
        $this->authorize('report.pic.export_own');

        $format = request('format', 'csv');
        if ($format !== 'csv') {
            return response()->json(['message' => 'Export format not supported. Only csv is supported.'], 422);
        }

        return $this->exportService->exportCsv('pic_performance_report.csv', collect([['status' => 'success', 'format' => 'csv']]));
    }
}
