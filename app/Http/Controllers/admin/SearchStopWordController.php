<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Search\SearchStopWordService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SearchStopWordController extends Controller
{
    public function __construct(private readonly SearchStopWordService $stopWords)
    {
    }

    private function institutionId(): int
    {
        $id = (int) (auth()->user()->institution_id ?? 0);
        return $id > 0 ? $id : 1;
    }

    public function index(Request $request)
    {
        $institutionId = $this->institutionId();
        $rows = DB::table('search_stop_words')
            ->where('institution_id', $institutionId)
            ->orderBy('word')
            ->paginate(30)
            ->withQueryString();

        $branches = DB::table('branches')
            ->select('id', 'name')
            ->where('is_active', 1)
            ->orderBy('name')
            ->get();

        return view('admin.search-stopwords', [
            'rows' => $rows,
            'branches' => $branches,
        ]);
    }

    public function store(Request $request)
    {
        $institutionId = $this->institutionId();
        $data = $request->validate([
            'words' => ['required', 'string'],
            'branch_id' => ['nullable', 'integer'],
        ]);

        $words = array_values(array_unique(array_filter(array_map(
            fn ($w) => mb_strtolower(trim((string) $w)),
            preg_split('/[,\n;]+/', (string) $data['words'])
        ))));
        $branchId = $data['branch_id'] ?? null;
        $now = now();
        $added = 0;

        foreach ($words as $word) {
            if ($word === '' || mb_strlen($word) < 2) continue;
            $exists = DB::table('search_stop_words')
                ->where('institution_id', $institutionId)
                ->where('branch_id', $branchId)
                ->where('word', $word)
                ->exists();
            if ($exists) continue;

            DB::table('search_stop_words')->insert([
                'institution_id' => $institutionId,
                'branch_id' => $branchId,
                'word' => $word,
                'updated_by' => auth()->id(),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $added++;
        }

        $this->stopWords->clearCache($institutionId);
        return back()->with('status', "Stopwords ditambahkan: {$added}.");
    }

    public function destroy(int $id)
    {
        $institutionId = $this->institutionId();
        DB::table('search_stop_words')
            ->where('institution_id', $institutionId)
            ->where('id', $id)
            ->delete();

        $this->stopWords->clearCache($institutionId);
        return back()->with('status', 'Stopword dihapus.');
    }
}

