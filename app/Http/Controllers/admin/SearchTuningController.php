<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Search\SearchTuningService;
use Illuminate\Http\Request;

class SearchTuningController extends Controller
{
    public function __construct(private readonly SearchTuningService $tuning)
    {
    }

    private function institutionId(): int
    {
        $id = (int) (auth()->user()->institution_id ?? 0);
        return $id > 0 ? $id : 1;
    }

    public function index()
    {
        $institutionId = $this->institutionId();
        return view('admin.search-tuning', [
            'settings' => $this->tuning->forInstitution($institutionId),
            'defaults' => $this->tuning->defaults(),
        ]);
    }

    public function update(Request $request)
    {
        $institutionId = $this->institutionId();
        $data = $request->validate([
            'title_exact_weight' => ['required', 'integer', 'min:0', 'max:500'],
            'author_exact_weight' => ['required', 'integer', 'min:0', 'max:500'],
            'subject_exact_weight' => ['required', 'integer', 'min:0', 'max:500'],
            'publisher_exact_weight' => ['required', 'integer', 'min:0', 'max:500'],
            'isbn_exact_weight' => ['required', 'integer', 'min:0', 'max:1000'],
            'short_query_max_len' => ['required', 'integer', 'min:1', 'max:12'],
            'short_query_multiplier' => ['required', 'numeric', 'min:1', 'max:5'],
            'available_weight' => ['required', 'numeric', 'min:0', 'max:100'],
            'borrowed_penalty' => ['required', 'numeric', 'min:0', 'max:100'],
            'reserved_penalty' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $this->tuning->upsertForInstitution($institutionId, $data, auth()->id());

        return back()->with('status', 'Pengaturan relevance berhasil diperbarui.');
    }

    public function reset()
    {
        $institutionId = $this->institutionId();
        $this->tuning->resetForInstitution($institutionId, auth()->id());
        return back()->with('status', 'Pengaturan relevance dikembalikan ke default.');
    }

    public function applyPreset(Request $request)
    {
        $institutionId = $this->institutionId();
        $data = $request->validate([
            'preset' => ['required', 'in:school,university,public'],
        ]);

        $preset = (string) $data['preset'];
        $payload = match ($preset) {
            'school' => [
                'title_exact_weight' => 95,
                'author_exact_weight' => 30,
                'subject_exact_weight' => 20,
                'publisher_exact_weight' => 10,
                'isbn_exact_weight' => 120,
                'short_query_max_len' => 4,
                'short_query_multiplier' => 1.8,
                'available_weight' => 12,
                'borrowed_penalty' => 3,
                'reserved_penalty' => 2,
            ],
            'university' => [
                'title_exact_weight' => 80,
                'author_exact_weight' => 45,
                'subject_exact_weight' => 35,
                'publisher_exact_weight' => 18,
                'isbn_exact_weight' => 100,
                'short_query_max_len' => 4,
                'short_query_multiplier' => 1.6,
                'available_weight' => 10,
                'borrowed_penalty' => 3,
                'reserved_penalty' => 2,
            ],
            default => [
                'title_exact_weight' => 75,
                'author_exact_weight' => 35,
                'subject_exact_weight' => 30,
                'publisher_exact_weight' => 20,
                'isbn_exact_weight' => 100,
                'short_query_max_len' => 5,
                'short_query_multiplier' => 1.5,
                'available_weight' => 14,
                'borrowed_penalty' => 2.5,
                'reserved_penalty' => 1.5,
            ],
        };

        $this->tuning->upsertForInstitution($institutionId, $payload, auth()->id());
        return back()->with('status', 'Preset relevance diterapkan: ' . ucfirst($preset) . '.');
    }
}
