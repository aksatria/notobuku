<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\CatalogAccess;
use App\Services\CatalogDetailService;

class CatalogDetailController extends Controller
{
    use CatalogAccess;

    public function __construct(private readonly CatalogDetailService $catalogDetailService)
    {
    }

    public function show($id)
    {
        $institutionId = $this->currentInstitutionId();
        $isPublic = request()->routeIs('opac.*');
        $canManage = auth()->check() ? $this->canManageCatalog() : false;

        $branchId = null;
        if (auth()->check()) {
            $candidate = (int) (auth()->user()->branch_id ?? 0);
            $branchId = $candidate > 0 ? $candidate : null;
        }

        $data = $this->catalogDetailService->buildShowData(
            $institutionId,
            (int) $id,
            $canManage,
            auth()->check(),
            auth()->id(),
            $branchId
        );

        return view('katalog.show', [
            'biblio' => $data['biblio'],
            'items' => $data['items'],
            'relatedBiblios' => $data['relatedBiblios'],
            'attachments' => $data['attachments'],
            'indexRouteName' => $isPublic ? 'opac.index' : 'katalog.index',
            'showRouteName' => $isPublic ? 'opac.show' : 'katalog.show',
            'isPublic' => $isPublic,
            'canManage' => $canManage,
        ]);
    }
}
