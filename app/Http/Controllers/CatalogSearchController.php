<?php

namespace App\Http\Controllers;

use App\Services\CatalogSearchService;
use Illuminate\Http\Request;

class CatalogSearchController extends Controller
{
    public function __construct(private readonly CatalogSearchService $catalogSearchService)
    {
    }

    public function index(Request $request)
    {
        return $this->catalogSearchService->index($request);
    }

    public function facets(Request $request)
    {
        return $this->catalogSearchService->facets($request);
    }

    public function indexPublic(Request $request)
    {
        return $this->catalogSearchService->indexPublic($request);
    }

    public function facetsPublic(Request $request)
    {
        return $this->catalogSearchService->facetsPublic($request);
    }

    public function suggest(Request $request)
    {
        return $this->catalogSearchService->suggest($request);
    }

    public function setShelvesPreference(Request $request)
    {
        $enabled = (string) $request->input('enabled', '0') === '1';
        session(['opac_shelves' => $enabled]);

        return response()->json([
            'success' => true,
            'enabled' => $enabled,
        ]);
    }

    public function apiSearch(Request $request)
    {
        return $this->catalogSearchService->apiSearch($request);
    }

    public function apiShow($id)
    {
        return $this->catalogSearchService->apiShow($id);
    }
}
