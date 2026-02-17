<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\CatalogAccess;
use App\Services\CatalogAuditService;
use Illuminate\Http\Request;

class CatalogAuditController extends Controller
{
    use CatalogAccess;

    public function __construct(private readonly CatalogAuditService $catalogAuditService)
    {
    }

    public function edit($id)
    {
        abort_unless(auth()->check() && $this->canManageCatalog(), 403);

        $data = $this->catalogAuditService->buildEditData($this->currentInstitutionId(), (int) $id);

        return view('katalog.edit', $data);
    }

    public function audit(Request $request, $id)
    {
        abort_unless(auth()->check() && $this->canManageCatalog(), 403);

        $filters = [
            'action' => (string) $request->query('action', ''),
            'status' => (string) $request->query('status', ''),
            'start_date' => (string) $request->query('start_date', ''),
            'end_date' => (string) $request->query('end_date', ''),
        ];

        $data = $this->catalogAuditService->buildAuditData($this->currentInstitutionId(), (int) $id, $filters);

        return view('katalog.audit', $data);
    }

    public function auditCsv(Request $request, $id)
    {
        abort_unless(auth()->check() && $this->canManageCatalog(), 403);

        $filters = [
            'action' => (string) $request->query('action', ''),
            'status' => (string) $request->query('status', ''),
            'start_date' => (string) $request->query('start_date', ''),
            'end_date' => (string) $request->query('end_date', ''),
        ];

        $data = $this->catalogAuditService->buildAuditCsvData($this->currentInstitutionId(), (int) $id, $filters);

        return response()->streamDownload(function () use ($data) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['created_at', 'action', 'format', 'status', 'user_id', 'user_name', 'meta']);
            foreach ($data['rows'] as $row) {
                $userName = $data['auditUsers'][$row->user_id]->name ?? '';
                fputcsv($out, [
                    $row->created_at?->format('Y-m-d H:i:s'),
                    $row->action,
                    $row->format,
                    $row->status,
                    $row->user_id,
                    $userName,
                    json_encode($row->meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ]);
            }
            fclose($out);
        }, (string) $data['fileName'], [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
