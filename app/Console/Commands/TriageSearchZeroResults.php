<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TriageSearchZeroResults extends Command
{
    protected $signature = 'notobuku:search-zero-triage
        {--institution= : Scope ke 1 institution}
        {--limit=500 : Maksimal baris diproses}
        {--min-search-count=2 : Minimal frekuensi pencarian}
        {--age-hours=24 : Umur minimum query open agar bisa ditutup otomatis}
        {--force-close-open=1 : Tutup semua sisa open menjadi ignored}';

    protected $description = 'Auto triage zero-result query: resolved_auto/ignored agar antrean open tetap nol.';

    public function handle(): int
    {
        if (!Schema::hasTable('search_queries')) {
            $this->warn('Tabel search_queries tidak ditemukan.');
            return self::SUCCESS;
        }

        $limit = max(1, (int) $this->option('limit'));
        $minSearchCount = max(1, (int) $this->option('min-search-count'));
        $ageHours = max(1, (int) $this->option('age-hours'));
        $forceClose = (int) $this->option('force-close-open') === 1;
        $institutionId = $this->option('institution');
        $institutionId = $institutionId !== null && $institutionId !== '' ? (int) $institutionId : null;

        $q = DB::table('search_queries')
            ->where('zero_result_status', 'open')
            ->orderByDesc('search_count')
            ->orderByDesc('last_searched_at')
            ->limit($limit);

        if ($institutionId !== null && $institutionId > 0 && Schema::hasColumn('search_queries', 'institution_id')) {
            $q->where('institution_id', $institutionId);
        }

        $rows = $q->get([
            'id',
            'query',
            'normalized_query',
            'last_hits',
            'search_count',
            'last_searched_at',
            'auto_suggestion_query',
            'auto_suggestion_score',
        ]);

        $resolvedAuto = 0;
        $ignored = 0;
        $skipped = 0;
        $now = now();
        $cutoff = $now->copy()->subHours($ageHours);

        foreach ($rows as $row) {
            $id = (int) ($row->id ?? 0);
            if ($id <= 0) {
                continue;
            }

            $lastHits = (int) ($row->last_hits ?? 0);
            $searchCount = (int) ($row->search_count ?? 0);
            $lastSearchedAt = $row->last_searched_at ? Carbon::parse((string) $row->last_searched_at) : null;
            $hasSuggestion = trim((string) ($row->auto_suggestion_query ?? '')) !== '';
            $suggestionScore = (float) ($row->auto_suggestion_score ?? 0);

            if ($lastHits > 0) {
                DB::table('search_queries')->where('id', $id)->update([
                    'zero_result_status' => 'resolved_auto',
                    'zero_resolved_at' => $now,
                    'zero_resolved_by' => null,
                    'zero_resolution_note' => 'Auto triage: query kini memiliki hasil.',
                    'auto_suggestion_status' => 'resolved_auto',
                    'updated_at' => $now,
                ]);
                $resolvedAuto++;
                continue;
            }

            $isStaleEnough = $lastSearchedAt === null || $lastSearchedAt->lte($cutoff);
            $isFrequent = $searchCount >= $minSearchCount;
            if ($isStaleEnough && ($isFrequent || $hasSuggestion || $forceClose)) {
                $note = 'Auto triage: ditutup otomatis, lanjutkan review sinonim.';
                if ($hasSuggestion && $suggestionScore > 0) {
                    $note .= ' Suggestion=' . trim((string) $row->auto_suggestion_query) . ' (score ' . number_format($suggestionScore, 2) . ').';
                }
                DB::table('search_queries')->where('id', $id)->update([
                    'zero_result_status' => 'ignored',
                    'zero_resolved_at' => $now,
                    'zero_resolved_by' => null,
                    'zero_resolution_note' => $note,
                    'auto_suggestion_status' => $hasSuggestion ? 'pending_review' : 'none',
                    'updated_at' => $now,
                ]);
                $ignored++;
                continue;
            }

            if ($forceClose) {
                DB::table('search_queries')->where('id', $id)->update([
                    'zero_result_status' => 'ignored',
                    'zero_resolved_at' => $now,
                    'zero_resolved_by' => null,
                    'zero_resolution_note' => 'Auto triage force-close untuk menjaga antrean open=0.',
                    'auto_suggestion_status' => $hasSuggestion ? 'pending_review' : 'none',
                    'updated_at' => $now,
                ]);
                $ignored++;
                continue;
            }

            $skipped++;
        }

        $openLeftQ = DB::table('search_queries')->where('zero_result_status', 'open');
        if ($institutionId !== null && $institutionId > 0 && Schema::hasColumn('search_queries', 'institution_id')) {
            $openLeftQ->where('institution_id', $institutionId);
        }
        $openLeft = (int) $openLeftQ->count();

        $this->info('Zero triage selesai.'
            . " resolved_auto={$resolvedAuto}"
            . " ignored={$ignored}"
            . " skipped={$skipped}"
            . " open_left={$openLeft}");

        return self::SUCCESS;
    }
}
