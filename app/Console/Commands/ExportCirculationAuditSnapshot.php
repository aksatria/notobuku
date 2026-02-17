<?php

namespace App\Console\Commands;

use App\Support\CirculationSlaClock;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class ExportCirculationAuditSnapshot extends Command
{
    protected $signature = 'notobuku:circulation-audit-snapshot {--month=}';

    protected $description = 'Export monthly circulation audit snapshot to storage.';

    public function handle(): int
    {
        if (!Schema::hasTable('loan_items') || !Schema::hasTable('loans')) {
            $this->warn('Tabel sirkulasi belum tersedia. Snapshot dilewati.');
            return self::SUCCESS;
        }

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

        $biblioTable = null;
        if (Schema::hasTable('biblio')) {
            $biblioTable = 'biblio';
        } elseif (Schema::hasTable('biblios')) {
            $biblioTable = 'biblios';
        }

        $rowsQ = DB::table('loan_items as li')
            ->join('loans as l', 'l.id', '=', 'li.loan_id')
            ->leftJoin('members as m', 'm.id', '=', 'l.member_id')
            ->leftJoin('items as i', 'i.id', '=', 'li.item_id')
            ->leftJoin('branches as br', 'br.id', '=', 'l.branch_id')
            ->leftJoin('users as u', 'u.id', '=', 'l.created_by')
            ->whereBetween(DB::raw('COALESCE(li.borrowed_at, l.loaned_at)'), [$from, $to])
            ->orderBy('l.id');

        if ($biblioTable !== null) {
            $rowsQ->leftJoin($biblioTable . ' as b', 'b.id', '=', 'i.biblio_id');
            $titleExpr = 'COALESCE(b.title, \'-\') as title';
        } else {
            $titleExpr = '\'-\' as title';
        }

        $rows = $rowsQ->get([
            'l.id as loan_id',
            'l.loan_code',
            'l.institution_id',
            'l.status as loan_status',
            'm.member_code',
            'm.full_name as member_name',
            'i.barcode',
            DB::raw($titleExpr),
            'li.borrowed_at',
            'li.due_at',
            'li.returned_at',
            DB::raw('COALESCE(br.name, \'-\') as branch_name'),
            DB::raw('COALESCE(u.name, \'-\') as operator_name'),
        ]);

        if ($rows->isEmpty()) {
            $this->info('Tidak ada data audit sirkulasi untuk periode tersebut.');
            return self::SUCCESS;
        }

        $stream = fopen('php://temp', 'w+');
        if ($stream === false) {
            $this->error('Gagal membuat stream snapshot.');
            return self::FAILURE;
        }

        fputcsv($stream, [
            'loan_id',
            'loan_code',
            'institution_id',
            'loan_status',
            'member_code',
            'member_name',
            'barcode',
            'title',
            'borrowed_at',
            'due_at',
            'returned_at',
            'late_days',
            'branch_name',
            'operator_name',
        ]);

        foreach ($rows as $row) {
            $lateDays = 0;
            $lateDays = CirculationSlaClock::elapsedLateDays(
                $row->due_at ? (string) $row->due_at : null,
                $row->returned_at ? (string) $row->returned_at : null
            );

            fputcsv($stream, [
                (int) $row->loan_id,
                (string) ($row->loan_code ?? ''),
                (int) ($row->institution_id ?? 0),
                (string) ($row->loan_status ?? ''),
                (string) ($row->member_code ?? ''),
                (string) ($row->member_name ?? ''),
                (string) ($row->barcode ?? ''),
                (string) ($row->title ?? '-'),
                $row->borrowed_at ? (string) \Illuminate\Support\Carbon::parse($row->borrowed_at)->format('Y-m-d H:i:s') : '',
                $row->due_at ? (string) \Illuminate\Support\Carbon::parse($row->due_at)->format('Y-m-d H:i:s') : '',
                $row->returned_at ? (string) \Illuminate\Support\Carbon::parse($row->returned_at)->format('Y-m-d H:i:s') : '',
                (int) $lateDays,
                (string) ($row->branch_name ?? '-'),
                (string) ($row->operator_name ?? '-'),
            ]);
        }

        rewind($stream);
        $content = stream_get_contents($stream);
        fclose($stream);

        if ($content === false) {
            $this->error('Gagal membaca konten snapshot.');
            return self::FAILURE;
        }

        $monthKey = $from->format('Y-m');
        $path = 'reports/circulation-audit-snapshots/circulation-audit-' . $monthKey . '.csv';
        Storage::disk('local')->put($path, $content);

        $this->info('Snapshot tersimpan: storage/app/' . $path);
        return self::SUCCESS;
    }
}
