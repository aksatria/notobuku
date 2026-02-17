<?php

namespace App\Services;

use App\Services\ExportService;
use App\Services\ImportService;
use App\Jobs\AiCatalogingJob;
use Illuminate\Http\UploadedFile;

class CatalogImportExportService
{
    public function exportByFormat(string $format, int $institutionId, ExportService $exportService)
    {
        return match ($format) {
            'csv' => $exportService->exportCsvBiblios($institutionId),
            'dcxml' => $exportService->exportDublinCoreXml($institutionId),
            'marcxml' => $exportService->exportMarcXmlCore($institutionId),
            'jsonld' => $exportService->exportJsonLd($institutionId),
            default => response()->json(['message' => 'Format tidak dikenali.'], 422),
        };
    }

    public function importByFormat(
        string $format,
        UploadedFile $file,
        int $institutionId,
        ?int $userId,
        ImportService $importService
    ) {
        if ($importService->shouldQueue($file, $format)) {
            $jobId = $importService->queueImport($file, $format, $institutionId, $userId);

            return response()->json([
                'queued' => true,
                'job_id' => $jobId,
                'report' => [
                    'created' => 0,
                    'updated' => 0,
                    'skipped' => 0,
                    'errors' => [],
                ],
            ], 202);
        }

        $report = $importService->importByFormat($format, $file, $institutionId, $userId);

        if (($report['status'] ?? '') === 'invalid_format') {
            return response()->json($report, 422);
        }

        $ids = $report['biblio_ids'] ?? [];
        $total = (int) ($report['created'] ?? 0) + (int) ($report['updated'] ?? 0);
        if (!empty($ids) && $importService->shouldQueueAi($total)) {
            AiCatalogingJob::dispatch($ids, $institutionId);
        }

        return response()->json($report);
    }
}
