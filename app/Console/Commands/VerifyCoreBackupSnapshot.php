<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class VerifyCoreBackupSnapshot extends Command
{
    protected $signature = 'notobuku:backup-restore-drill {--file=}';

    protected $description = 'Restore drill non-destruktif: verifikasi integritas snapshot backup core.';

    public function handle(): int
    {
        $dir = trim((string) config('notobuku.backup.snapshot_dir', 'backups/core'));
        $file = trim((string) $this->option('file'));
        if ($file === '') {
            $file = collect(Storage::files($dir))
                ->filter(fn ($f) => str_ends_with($f, '.json'))
                ->sortDesc()
                ->first() ?? '';
        }
        if ($file === '' || !Storage::exists($file)) {
            $this->error('Snapshot tidak ditemukan.');
            return self::FAILURE;
        }

        $raw = Storage::get($file);
        $data = json_decode($raw, true);
        if (!is_array($data) || !isset($data['tables']) || !is_array($data['tables'])) {
            $this->error('Snapshot tidak valid.');
            return self::FAILURE;
        }

        $issues = [];
        foreach ((array) $data['tables'] as $table => $meta) {
            $existsNow = Schema::hasTable((string) $table);
            if (!$existsNow) {
                $issues[] = 'Tabel saat ini tidak ada: ' . $table;
                continue;
            }
            if (!is_array($meta) || !array_key_exists('count', $meta)) {
                $issues[] = 'Metadata tabel rusak: ' . $table;
                continue;
            }
        }

        $report = [
            'checked_at' => now()->toIso8601String(),
            'snapshot_file' => $file,
            'issue_count' => count($issues),
            'issues' => $issues,
        ];
        $reportFile = rtrim($dir, '/\\') . '/drill-report-' . now()->format('Ymd-His') . '.json';
        Storage::put($reportFile, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        if (!empty($issues)) {
            foreach ($issues as $issue) {
                $this->warn($issue);
            }
            $this->warn('Restore drill selesai dengan issue. Report: ' . $reportFile);
            return self::FAILURE;
        }

        $this->info('Restore drill sukses. Report: ' . $reportFile);
        return self::SUCCESS;
    }
}

