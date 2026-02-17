<?php

namespace App\Http\Controllers;

use App\Models\CopyCatalogImport;
use App\Models\CopyCatalogSource;
use App\Services\CopyCatalogingService;
use Illuminate\Http\Request;

class CopyCatalogingController extends Controller
{
    private function currentInstitutionId(): int
    {
        $id = (int) (auth()->user()->institution_id ?? 0);
        return $id > 0 ? $id : 1;
    }

    public function index(Request $request, CopyCatalogingService $service)
    {
        $institutionId = $this->currentInstitutionId();
        $sourceId = (int) $request->query('source_id', 0);
        $q = trim((string) $request->query('q', ''));
        $limit = (int) $request->query('limit', 10);

        $sources = CopyCatalogSource::query()
            ->where('institution_id', $institutionId)
            ->orderBy('priority')
            ->orderBy('name')
            ->get();

        $results = [];
        $activeSource = null;
        if ($sourceId > 0 && $q !== '') {
            $activeSource = $sources->firstWhere('id', $sourceId);
            if ($activeSource && $activeSource->is_active) {
                $results = $service->search($activeSource, $q, $limit);
            }
        }

        $imports = CopyCatalogImport::query()
            ->where('institution_id', $institutionId)
            ->with(['source:id,name', 'biblio:id,title', 'user:id,name'])
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        return view('copy_cataloging.index', compact('sources', 'results', 'imports', 'sourceId', 'q', 'limit', 'activeSource'));
    }

    public function storeSource(Request $request)
    {
        $institutionId = $this->currentInstitutionId();
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'protocol' => ['required', 'in:sru,z3950,p2p'],
            'endpoint' => ['required', 'string', 'max:500'],
            'username' => ['nullable', 'string', 'max:120'],
            'password' => ['nullable', 'string', 'max:160'],
            'priority' => ['nullable', 'integer', 'min:1', 'max:999'],
            'is_active' => ['nullable', 'boolean'],
            'gateway_url' => ['nullable', 'string', 'max:500'],
        ]);

        CopyCatalogSource::query()->create([
            'institution_id' => $institutionId,
            'name' => $validated['name'],
            'protocol' => $validated['protocol'],
            'endpoint' => $validated['endpoint'],
            'username' => $validated['username'] ?? null,
            'password' => $validated['password'] ?? null,
            'priority' => $validated['priority'] ?? 10,
            'is_active' => (bool) ($validated['is_active'] ?? true),
            'settings_json' => [
                'gateway_url' => $validated['gateway_url'] ?? null,
            ],
        ]);

        return redirect()->route('copy_cataloging.index')->with('success', 'Sumber copy cataloging ditambahkan.');
    }

    public function import(Request $request, CopyCatalogingService $service)
    {
        $institutionId = $this->currentInstitutionId();
        $validated = $request->validate([
            'source_id' => ['required', 'integer'],
            'record_payload' => ['required', 'string'],
        ]);

        $source = CopyCatalogSource::query()
            ->where('institution_id', $institutionId)
            ->where('id', (int) $validated['source_id'])
            ->firstOrFail();

        $record = json_decode(base64_decode($validated['record_payload'], true) ?: '', true);
        if (!is_array($record)) {
            return redirect()->route('copy_cataloging.index')->with('error', 'Payload record tidak valid.');
        }

        $import = $service->importRecord($source, $institutionId, (int) auth()->id(), $record);

        if ($import->status !== 'imported') {
            return redirect()->route('copy_cataloging.index')->with('error', 'Import gagal: ' . (string) $import->error_message);
        }

        return redirect()
            ->route('katalog.edit', $import->biblio_id)
            ->with('success', 'Copy cataloging berhasil. Lengkapi metadata sebelum publish.');
    }
}

