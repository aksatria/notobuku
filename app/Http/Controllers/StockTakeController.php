<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Shelf;
use App\Models\StockTake;
use App\Services\StockTakeService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StockTakeController extends Controller
{
    private function currentInstitutionId(): int
    {
        $id = (int) (auth()->user()->institution_id ?? 0);
        return $id > 0 ? $id : 1;
    }

    public function index(Request $request)
    {
        $institutionId = $this->currentInstitutionId();

        $stockTakes = StockTake::query()
            ->where('institution_id', $institutionId)
            ->with(['branch:id,name', 'shelf:id,name', 'user:id,name'])
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $branches = Branch::query()
            ->where('institution_id', $institutionId)
            ->where('is_active', 1)
            ->orderBy('name')
            ->get(['id', 'name']);

        $shelves = Shelf::query()
            ->where('institution_id', $institutionId)
            ->where('is_active', 1)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('stock_takes.index', compact('stockTakes', 'branches', 'shelves'));
    }

    public function store(Request $request)
    {
        $institutionId = $this->currentInstitutionId();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'branch_id' => ['nullable', 'integer'],
            'shelf_id' => ['nullable', 'integer'],
            'scope_status' => ['nullable', 'string', 'max:40'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $stockTake = StockTake::query()->create([
            'institution_id' => $institutionId,
            'user_id' => (int) auth()->id(),
            'branch_id' => $validated['branch_id'] ?? null,
            'shelf_id' => $validated['shelf_id'] ?? null,
            'name' => $validated['name'],
            'scope_status' => $validated['scope_status'] ?: 'all',
            'status' => 'draft',
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()
            ->route('stock_takes.show', $stockTake->id)
            ->with('success', 'Sesi stock opname dibuat.');
    }

    public function show(int $id, StockTakeService $service)
    {
        $stockTake = $this->findOrFail($id);
        $summary = $service->summary($stockTake);
        $lines = $stockTake->lines()
            ->with('item:id,barcode,status,condition')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('stock_takes.show', compact('stockTake', 'summary', 'lines'));
    }

    public function start(int $id, StockTakeService $service)
    {
        $stockTake = $this->findOrFail($id);
        $service->start($stockTake);

        return redirect()
            ->route('stock_takes.show', $id)
            ->with('success', 'Stock opname dimulai. Mulai scan barcode.');
    }

    public function scan(int $id, Request $request, StockTakeService $service)
    {
        $stockTake = $this->findOrFail($id);
        if ($stockTake->status !== 'in_progress') {
            return redirect()->route('stock_takes.show', $id)->with('error', 'Sesi belum aktif.');
        }

        $validated = $request->validate([
            'barcode' => ['required', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $summary = $service->scan($stockTake, $validated['barcode'], $validated['notes'] ?? null);

        return redirect()
            ->route('stock_takes.show', $id)
            ->with('success', 'Barcode diproses. Found: ' . (int) ($summary['found_items_count'] ?? 0));
    }

    public function complete(int $id, StockTakeService $service)
    {
        $stockTake = $this->findOrFail($id);
        $service->complete($stockTake);

        return redirect()
            ->route('stock_takes.show', $id)
            ->with('success', 'Stock opname selesai dan item missing sudah ditandai.');
    }

    public function exportCsv(int $id): StreamedResponse
    {
        $stockTake = $this->findOrFail($id);
        $filename = 'stock_opname_' . $stockTake->id . '_' . now()->format('Ymd_His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        return response()->stream(function () use ($stockTake) {
            $out = fopen('php://output', 'w');
            fwrite($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($out, ['barcode', 'expected', 'found', 'scan_status', 'title_snapshot', 'status_snapshot', 'condition_snapshot', 'notes', 'scanned_at']);
            $stockTake->lines()->orderBy('id')->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $line) {
                    fputcsv($out, [
                        (string) ($line->barcode ?? ''),
                        (int) ($line->expected ? 1 : 0),
                        (int) ($line->found ? 1 : 0),
                        (string) ($line->scan_status ?? ''),
                        (string) ($line->title_snapshot ?? ''),
                        (string) ($line->status_snapshot ?? ''),
                        (string) ($line->condition_snapshot ?? ''),
                        (string) ($line->notes ?? ''),
                        optional($line->scanned_at)->format('Y-m-d H:i:s'),
                    ]);
                }
            });
            fclose($out);
        }, 200, $headers);
    }

    private function findOrFail(int $id): StockTake
    {
        return StockTake::query()
            ->where('institution_id', $this->currentInstitutionId())
            ->where('id', $id)
            ->firstOrFail();
    }
}

