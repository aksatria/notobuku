<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\CatalogAccess;
use App\Services\CatalogMetadataService;
use App\Services\MarcValidationService;
use App\Services\PustakawanDigital\ExternalApiService;
use Illuminate\Http\Request;

class CatalogMetadataController extends Controller
{
    use CatalogAccess;

    public function __construct(private readonly CatalogMetadataService $catalogMetadataService)
    {
    }

    public function isbnLookup(Request $request, ExternalApiService $externalApiService)
    {
        abort_unless(auth()->check() && $this->canManageCatalog(), 403);

        $result = $this->catalogMetadataService->lookupByIsbn(
            (string) $request->query('isbn', ''),
            $externalApiService
        );

        return response()->json($result['body'], $result['status']);
    }

    public function validateMetadata(Request $request, MarcValidationService $validationService)
    {
        abort_unless(auth()->check() && $this->canManageCatalog(), 403);

        $result = $this->catalogMetadataService->validateDraft(
            $request->all(),
            $this->currentInstitutionId(),
            $validationService
        );

        return response()->json($result);
    }
}
