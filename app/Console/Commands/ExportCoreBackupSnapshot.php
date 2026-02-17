<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class ExportCoreBackupSnapshot extends Command
{
    protected $signature = 'notobuku:backup-core-snapshot {--tag=} {--max-rows=2000}';

    protected $description = 'Export snapshot JSON tabel inti untuk backup operasional non-destruktif.';

    public function handle(): int
    {
        $dir = trim((string) config('notobuku.backup.snapshot_dir', 'backups/core'));
        $tag = trim((string) $this->option('tag'));
        $maxRows = max(10, (int) $this->option('max-rows'));
        $tables = (array) config('notobuku.backup.core_tables', []);

        $payload = [
            'generated_at' => now()->toIso8601String(),
            'app_env' => app()->environment(),
            'tag' => $tag !== '' ? $tag : null,
            'tables' => [],
        ];

        foreach ($tables as $table) {
            $table = (string) $table;
            if ($table === '') {
                continue;
            }
            if (!Schema::hasTable($table)) {
                $payload['tables'][$table] = ['exists' => false, 'count' => 0, 'rows' => []];
                continue;
            }
            $count = (int) DB::table($table)->count();
            $rows = DB::table($table)->limit($maxRows)->get()->map(fn ($r) => (array) $r)->all();
            $payload['tables'][$table] = ['exists' => true, 'count' => $count, 'rows' => $rows];
        }

        $file = rtrim($dir, '/\\') . '/core-snapshot-' . now()->format('Ymd-His') . '.json';
        Storage::put($file, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $retain = max(3, (int) config('notobuku.backup.retain_files', 30));
        $files = collect(Storage::files($dir))
            ->filter(fn ($f) => str_ends_with($f, '.json'))
            ->sort()
            ->values();
        if ($files->count() > $retain) {
            $toDelete = $files->slice(0, $files->count() - $retain)->all();
            if (!empty($toDelete)) {
                Storage::delete($toDelete);
            }
        }

        $this->info('Snapshot core tersimpan: ' . $file);
        return self::SUCCESS;
    }
}

