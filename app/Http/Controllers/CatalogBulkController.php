<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\CatalogAccess;
use App\Services\CatalogBulkService;
use Illuminate\Http\Request;

class CatalogBulkController extends Controller
{
    use CatalogAccess;

    public function __construct(private readonly CatalogBulkService $catalogBulkService)
    {
    }

    private function rules(): array
    {
        return [
            'ids' => ['required'],
            'material_type' => ['nullable', 'string', 'max:32'],
            'media_type' => ['nullable', 'string', 'max:32'],
            'language' => ['nullable', 'string', 'max:10'],
            'publisher' => ['nullable', 'string', 'max:255'],
            'ddc' => ['nullable', 'string', 'max:32'],
            'tags_text' => ['nullable', 'string', 'max:255'],
            'item_status' => ['nullable', 'string', 'max:32'],
            'branch_id' => ['nullable', 'integer', 'min:1'],
            'shelf_id' => ['nullable', 'integer', 'min:1'],
            'location_note' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function bulkUpdate(Request $request)
    {
        abort_unless(auth()->check() && $this->canManageCatalog(), 403);

        $result = $this->catalogBulkService->bulkUpdate(
            $request->validate($this->rules()),
            $this->currentInstitutionId(),
            (int) auth()->id()
        );

        if (!$result['ok']) {
            return back()->with('error', $result['error']);
        }

        return back()->with('success', $result['success']);
    }

    public function bulkPreview(Request $request)
    {
        abort_unless(auth()->check() && $this->canManageCatalog(), 403);

        $result = $this->catalogBulkService->bulkPreview(
            $request->validate($this->rules()),
            $this->currentInstitutionId()
        );

        if (!$result['ok']) {
            return response()->json(['message' => $result['message']], $result['status']);
        }

        return response()->json($result['body']);
    }

    public function bulkUndo()
    {
        abort_unless(auth()->check() && $this->canManageCatalog(), 403);

        $result = $this->catalogBulkService->bulkUndo(
            $this->currentInstitutionId(),
            (int) auth()->id()
        );

        if (!$result['ok']) {
            return back()->with('error', $result['error']);
        }

        return back()->with('success', $result['success']);
    }
}
