<?php

namespace App\Services\Reports;

use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TicketReportExportService
{
    private const MAX_ROWS = 50000;

    public function exportCsv(string $filename, Collection $data): StreamedResponse
    {
        if ($data->count() > self::MAX_ROWS) {
            abort(422, 'Data exceeds maximum allowed rows for export (50,000).');
        }

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$this->sanitizeFilename($filename).'"',
        ];

        return response()->stream(function () use ($data) {
            $handle = fopen('php://output', 'w');

            if ($data->isEmpty()) {
                fclose($handle);

                return;
            }

            // Write Header
            $firstRow = (array) $data->first();
            fputcsv($handle, array_keys($firstRow));

            // Write Data
            foreach ($data as $row) {
                $sanitizedRow = array_map([$this, 'sanitizeCell'], (array) $row);
                fputcsv($handle, $sanitizedRow);
            }

            fclose($handle);
        }, 200, $headers);
    }

    private function sanitizeFilename(string $filename): string
    {
        $filename = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $filename);
        if (! str_ends_with($filename, '.csv')) {
            $filename .= '.csv';
        }

        return $filename;
    }

    private function sanitizeCell($value): string
    {
        $value = (string) $value;
        if (in_array(substr($value, 0, 1), ['=', '+', '-', '@'], true)) {
            $value = "'".$value;
        }

        return $value;
    }
}
