<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\CatalogAccess;
use App\Services\BiblioAutofixService;
use App\Services\CatalogMaintenanceService;
use App\Services\MetadataMappingService;

class CatalogMaintenanceController extends Controller
{
    use CatalogAccess;

    public function __construct(private readonly CatalogMaintenanceService $catalogMaintenanceService)
    {
    }

    public function autofix($id, MetadataMappingService $metadataService, BiblioAutofixService $autofixService)
    {
        abort_unless(auth()->check() && $this->canManageCatalog(), 403);

        $result = $this->catalogMaintenanceService->autofix(
            $this->currentInstitutionId(),
            (int) $id,
            $metadataService,
            $autofixService
        );

        return redirect()
            ->route('katalog.edit', ['id' => $result['id']])
            ->with('status', $result['changed'] ? 'Auto-fix diterapkan.' : 'Auto-fix: tidak ada perubahan.');
    }

    public function destroy($id)
    {
        abort_unless(auth()->check() && $this->canManageCatalog(), 403);

        $result = $this->catalogMaintenanceService->destroy($this->currentInstitutionId(), (int) $id);

        if (!$result['ok']) {
            return redirect()->back()->with('error', $result['error']);
        }

        return redirect()
            ->route('katalog.index')
            ->with('success', $result['success']);
    }
}
