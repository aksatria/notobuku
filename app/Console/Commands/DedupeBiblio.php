<?php

namespace App\Console\Commands;

use App\Models\Biblio;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DedupeBiblio extends Command
{
    protected $signature = 'notobuku:dedupe-biblio {--dry-run : Hanya tampilkan rencana tanpa mengubah data}';

    protected $description = 'Hilangkan duplikat biblio berdasarkan normalized_title + responsibility_statement.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $groups = DB::select("
            select normalized_title, responsibility_statement, count(*) as c
            from biblio
            group by normalized_title, responsibility_statement
            having c > 1
            order by c desc
        ");

        if (empty($groups)) {
            $this->info('Tidak ada duplikat.');
            return 0;
        }

        $totalMerged = 0;
        foreach ($groups as $g) {
            $title = (string) ($g->normalized_title ?? '');
            $resp = (string) ($g->responsibility_statement ?? '');

            $rows = Biblio::query()
                ->where('normalized_title', $title)
                ->where('responsibility_statement', $resp)
                ->withCount('items')
                ->get();

            if ($rows->count() <= 1) {
                continue;
            }

            $keep = $rows->sortByDesc(function (Biblio $b) {
                $score = 0;
                $score += $b->items_count ?? 0;
                $score += $b->cover_path ? 3 : 0;
                $score += $b->notes ? 2 : 0;
                $score += $b->publisher ? 1 : 0;
                $score += $b->publish_year ? 1 : 0;
                return $score;
            })->first();

            $dupIds = $rows->pluck('id')->filter(fn ($id) => $id !== $keep->id)->values()->all();
            if (empty($dupIds)) {
                continue;
            }

            $this->line("Duplikat '{$title}' / '{$resp}': keep={$keep->id}, remove=" . implode(',', $dupIds));
            if ($dryRun) {
                continue;
            }

            DB::transaction(function () use ($keep, $dupIds, &$totalMerged) {
                $this->mergePivot('biblio_author', ['author_id', 'role', 'sort_order'], $keep->id, $dupIds);
                $this->mergePivot('biblio_subject', ['subject_id', 'type', 'sort_order'], $keep->id, $dupIds);
                $this->mergePivot('biblio_tag', ['tag_id', 'sort_order'], $keep->id, $dupIds);
                $this->mergePivot('biblio_ddc', ['ddc_class_id'], $keep->id, $dupIds);
                $this->mergePivot('biblio_identifiers', ['scheme', 'value', 'normalized_value', 'uri'], $keep->id, $dupIds);
                $this->mergePivot('biblio_user_metrics', ['institution_id', 'user_id', 'click_count', 'borrow_count', 'last_clicked_at', 'last_borrowed_at'], $keep->id, $dupIds);

                $this->mergeSingle('biblio_metadata', $keep->id, $dupIds);
                $this->mergeSingle('biblio_metrics', $keep->id, $dupIds);

                $this->updateBiblioId('items', $keep->id, $dupIds);
                $this->updateBiblioId('reservations', $keep->id, $dupIds);
                $this->updateBiblioId('purchase_order_lines', $keep->id, $dupIds);
                $this->updateBiblioId('posts', $keep->id, $dupIds);
                $this->updateBiblioId('biblio_events', $keep->id, $dupIds);

                Biblio::query()->whereIn('id', $dupIds)->delete();
                $totalMerged += count($dupIds);
            });
        }

        if ($dryRun) {
            $this->info('Dry run selesai.');
            return 0;
        }

        $this->info("Selesai. Duplikat dihapus: {$totalMerged}");
        return 0;
    }

    private function mergePivot(string $table, array $cols, int $keepId, array $dupIds): void
    {
        if (!DB::getSchemaBuilder()->hasTable($table)) {
            return;
        }

        $rows = DB::table($table)->whereIn('biblio_id', $dupIds)->get();
        foreach ($rows as $row) {
            $data = ['biblio_id' => $keepId];
            foreach ($cols as $col) {
                if (property_exists($row, $col)) {
                    $data[$col] = $row->{$col};
                }
            }
            DB::table($table)->insertOrIgnore($data);
        }

        DB::table($table)->whereIn('biblio_id', $dupIds)->delete();
    }

    private function mergeSingle(string $table, int $keepId, array $dupIds): void
    {
        if (!DB::getSchemaBuilder()->hasTable($table)) {
            return;
        }

        $keepExists = DB::table($table)->where('biblio_id', $keepId)->exists();
        if (!$keepExists) {
            $row = DB::table($table)->whereIn('biblio_id', $dupIds)->first();
            if ($row) {
                $data = (array) $row;
                $data['biblio_id'] = $keepId;
                unset($data['id'], $data['created_at'], $data['updated_at']);
                DB::table($table)->insertOrIgnore($data);
            }
        }

        DB::table($table)->whereIn('biblio_id', $dupIds)->delete();
    }

    private function updateBiblioId(string $table, int $keepId, array $dupIds): void
    {
        if (!DB::getSchemaBuilder()->hasTable($table)) {
            return;
        }
        DB::table($table)->whereIn('biblio_id', $dupIds)->update(['biblio_id' => $keepId]);
    }
}
