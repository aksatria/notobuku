<?php

namespace App\Console\Commands;

use App\Support\CirculationMetrics;
use App\Support\InteropMetrics;
use App\Support\OpacMetrics;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class GenerateUatChecklist extends Command
{
    protected $signature = 'notobuku:uat-generate {--date=}';

    protected $description = 'Generate checklist UAT operasional harian/mingguan dan simpan log pending sign-off.';

    public function handle(): int
    {
        $date = trim((string) $this->option('date'));
        $checkDate = $date !== '' ? \Illuminate\Support\Carbon::parse($date)->toDateString() : now()->toDateString();
        $dir = trim((string) config('notobuku.uat.dir', 'uat/checklists'));
        $institutionId = (int) config('notobuku.opac.public_institution_id', 1);

        $interop = InteropMetrics::snapshot();
        $opac = OpacMetrics::snapshot();
        $circ = CirculationMetrics::snapshot();
        $qualityGateEnabled = (bool) config('notobuku.catalog.quality_gate.enabled', true);
        $zeroOpen = 0;
        if (Schema::hasTable('search_queries') && Schema::hasColumn('search_queries', 'zero_result_status')) {
            $zeroOpen = (int) DB::table('search_queries')
                ->where('institution_id', $institutionId)
                ->where('zero_result_status', 'open')
                ->count();
        }

        $markdown = [];
        $markdown[] = '# UAT Operasional NOTOBUKU';
        $markdown[] = '';
        $markdown[] = '- Tanggal: ' . $checkDate;
        $markdown[] = '- Dibuat: ' . now()->toDateTimeString();
        $markdown[] = '';
        $markdown[] = '## Health Snapshot';
        $markdown[] = '- Interop status: ' . (string) data_get($interop, 'status.label', 'N/A');
        $markdown[] = '- OPAC SLO state: ' . (string) data_get($opac, 'slo.state', 'ok');
        $markdown[] = '- OPAC p95: ' . (int) data_get($opac, 'latency.p95_ms', 0) . ' ms';
        $markdown[] = '- Circulation health: ' . (string) data_get($circ, 'health.label', 'N/A');
        $markdown[] = '- Catalog quality gate: ' . ($qualityGateEnabled ? 'enabled' : 'disabled');
        $markdown[] = '- Zero-result queue open: ' . $zeroOpen;
        $markdown[] = '';
        $markdown[] = '## Checklist';
        $markdown[] = '- [ ] Katalog: create/edit/search berhasil.';
        $markdown[] = '- [ ] Sirkulasi: pinjam/kembali/perpanjang berhasil.';
        $markdown[] = '- [ ] Anggota: import preview+confirm berhasil.';
        $markdown[] = '- [ ] Laporan: export CSV/XLSX berhasil.';
        $markdown[] = '- [ ] Serial issue: claim workflow berhasil.';
        $markdown[] = '- [ ] Interop OAI/SRU merespon normal.';
        $markdown[] = '- [ ] OPAC publik dapat diakses dan performa stabil.';
        $markdown[] = '';
        $markdown[] = '## Sign-off';
        $markdown[] = '- Operator: __________________';
        $markdown[] = '- Status: PASS / FAIL';
        $markdown[] = '- Catatan: __________________';

        $file = rtrim($dir, '/\\') . '/uat-' . $checkDate . '.md';
        Storage::put($file, implode("\n", $markdown));

        if (Schema::hasTable('uat_signoffs')) {
            DB::table('uat_signoffs')->updateOrInsert(
                ['institution_id' => $institutionId, 'check_date' => $checkDate],
                [
                    'status' => 'pending',
                    'checklist_file' => $file,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }

        $this->info('Checklist UAT dibuat: ' . $file);
        return self::SUCCESS;
    }
}
