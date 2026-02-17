<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\CatalogAccess;
use App\Http\Requests\ImportKatalogRequest;
use App\Models\Biblio;
use App\Services\CatalogImportExportService;
use App\Services\ExportService;
use App\Services\ImportService;
use Illuminate\Http\Request;

class CatalogImportExportController extends Controller
{
    use CatalogAccess;

    public function __construct(private readonly CatalogImportExportService $catalogImportExportService)
    {
    }

    public function export(Request $request, ExportService $exportService)
    {
        $this->authorize('export', Biblio::class);

        $format = strtolower(trim((string) $request->query('format', 'csv')));
        $institutionId = $this->currentInstitutionId();

        return $this->catalogImportExportService->exportByFormat($format, $institutionId, $exportService);
    }

    public function import(ImportKatalogRequest $request, ImportService $importService)
    {
        $this->authorize('import', Biblio::class);

        $format = strtolower(trim((string) $request->input('format')));
        $file = $request->file('file');
        $institutionId = $this->currentInstitutionId();
        $userId = auth()->user()?->id;

        if (!$file) {
            return response()->json(['message' => 'File tidak ditemukan.'], 422);
        }

        return $this->catalogImportExportService->importByFormat(
            $format,
            $file,
            $institutionId,
            $userId,
            $importService
        );
    }
}
