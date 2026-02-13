<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ExportMemberImportHistorySnapshot extends Command
{
    protected $signature = 'notobuku:member-import-snapshot {--month=}';

    protected $description = 'Export monthly snapshot of member import history to storage.';

    public function handle(): int
    {
        $month = trim((string) $this->option('month'));
        try {
            $anchor = $month !== ''
                ? \Illuminate\Support\Carbon::parse($month . '-01')->startOfMonth()
                : now()->subMonthNoOverflow()->startOfMonth();
        } catch (\Throwable $e) {
            $this->error('Format --month tidak valid. Gunakan YYYY-MM.');
            return self::FAILURE;
        }

        $from = $anchor->copy()->startOfMonth();
        $to = $anchor->copy()->endOfMonth();
        $rows = DB::table('audit_logs')
            ->whereIn('action', ['member_import', 'member_import_undo'])
            ->whereBetween('created_at', [$from, $to])
            ->orderBy('id')
            ->get(['id', 'user_id', 'action', 'status', 'meta', 'created_at']);

        if ($rows->isEmpty()) {
            $this->info('Tidak ada data audit import anggota untuk periode tersebut.');
            return self::SUCCESS;
        }

        $userNames = DB::table('users')
            ->whereIn('id', $rows->pluck('user_id')->filter()->unique()->values()->all())
            ->pluck('name', 'id');

        $dir = 'reports/member-import-snapshots';
        $filename = sprintf('member-import-history-%s.csv', $from->format('Y-m'));
        $path = $dir . '/' . $filename;

        $stream = fopen('php://temp', 'w+');
        if ($stream === false) {
            $this->error('Gagal membuat stream snapshot.');
            return self::FAILURE;
        }

        fputcsv($stream, [
            'audit_id',
            'created_at',
            'institution_id',
            'action',
            'status',
            'user_id',
            'user_name',
            'batch_key',
            'inserted',
            'updated',
            'skipped',
            'undone_from_audit_id',
            'force_email_duplicate',
        ]);

        foreach ($rows as $row) {
            $meta = json_decode((string) ($row->meta ?? '{}'), true);
            if (!is_array($meta)) {
                $meta = [];
            }

            fputcsv($stream, [
                (int) $row->id,
                (string) $row->created_at,
                (int) ($meta['institution_id'] ?? 0),
                (string) $row->action,
                (string) ($row->status ?? ''),
                (int) ($row->user_id ?? 0),
                (string) ($userNames[$row->user_id] ?? 'System'),
                (string) ($meta['batch_key'] ?? ''),
                (int) ($meta['inserted'] ?? 0),
                (int) ($meta['updated'] ?? 0),
                (int) ($meta['skipped'] ?? 0),
                (int) ($meta['undone_from_audit_id'] ?? 0),
                !empty($meta['force_email_duplicate']) ? 1 : 0,
            ]);
        }

        rewind($stream);
        $content = stream_get_contents($stream);
        fclose($stream);

        if ($content === false) {
            $this->error('Gagal membaca konten snapshot.');
            return self::FAILURE;
        }

        Storage::disk('local')->put($path, $content);
        $this->info('Snapshot tersimpan: storage/app/' . $path);

        return self::SUCCESS;
    }
}
