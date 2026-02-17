<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\CatalogAccess;
use App\Http\Requests\StoreBiblioRequest;
use App\Http\Requests\UpdateBiblioRequest;
use App\Services\AiCatalogingService;
use App\Services\CatalogWriteService;
use App\Services\MetadataMappingService;

class CatalogWriteController extends Controller
{
    use CatalogAccess;

    public function __construct(private readonly CatalogWriteService $catalogWriteService)
    {
    }

    public function create()
    {
        abort_unless(auth()->check() && $this->canManageCatalog(), 403);

        return view('katalog.create', [
            'canManage' => true,
        ]);
    }

    public function store(
        StoreBiblioRequest $request,
        MetadataMappingService $metadataService,
        AiCatalogingService $aiCatalogingService
    ) {
        abort_unless(auth()->check() && $this->canManageCatalog(), 403);

        return $this->catalogWriteService->store($request, $metadataService, $aiCatalogingService);
    }

    public function update(
        UpdateBiblioRequest $request,
        $id,
        MetadataMappingService $metadataService,
        AiCatalogingService $aiCatalogingService
    ) {
        abort_unless(auth()->check() && $this->canManageCatalog(), 403);

        return $this->catalogWriteService->update($request, (int) $id, $metadataService, $aiCatalogingService);
    }
}
