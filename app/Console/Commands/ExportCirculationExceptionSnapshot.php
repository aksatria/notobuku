<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class ExportCirculationExceptionSnapshot extends Command
{
    protected $signature = 'notobuku:circulation-exception-snapshot {--date=}';

    protected $description = 'Export daily circulation exception snapshot (CSV) for audit operations.';

    public function handle(): int
    {
        $dateInput = trim((string) $this->option('date'));
        try {
            $day = $dateInput !== ''
                ? Carbon::parse($dateInput)->toDateString()
                : now()->subDay()->toDateString();
        } catch (\Throwable $e) {
            $this->error('Format --date tidak valid. Gunakan YYYY-MM-DD.');
            return self::FAILURE;
        }

        $dayStart = Carbon::parse($day)->startOfDay();
        $dayEnd = Carbon::parse($day)->endOfDay();

        $rows = [];
        $rows = array_merge($rows, $this->overdueExtremeRows($dayEnd));
        $rows = array_merge($rows, $this->fineVoidRows($dayStart, $dayEnd));
        $rows = array_merge($rows, $this->branchMismatchRows());

        $dir = 'reports/circulation-exceptions';
        $file = $dir . '/circulation-exceptions-' . $day . '.csv';

        $stream = fopen('php://temp', 'w+');
        if ($stream === false) {
            $this->error('Gagal membuat stream CSV.');
            return self::FAILURE;
        }

        fputcsv($stream, [
            'snapshot_date',
            'exception_type',
            'severity',
            'institution_id',
            'branch_id',
            'loan_id',
            'loan_code',
            'loan_item_id',
            'item_id',
            'barcode',
            'member_id',
            'member_code',
            'detail',
            'days_late',
            'detected_at',
        ]);

        foreach ($rows as $row) {
            fputcsv($stream, [
                $day,
                (string) ($row['exception_type'] ?? ''),
                (string) ($row['severity'] ?? ''),
                (int) ($row['institution_id'] ?? 0),
                (int) ($row['branch_id'] ?? 0),
                (int) ($row['loan_id'] ?? 0),
                (string) ($row['loan_code'] ?? ''),
                (int) ($row['loan_item_id'] ?? 0),
                (int) ($row['item_id'] ?? 0),
                (string) ($row['barcode'] ?? ''),
                (int) ($row['member_id'] ?? 0),
                (string) ($row['member_code'] ?? ''),
                (string) ($row['detail'] ?? ''),
                (int) ($row['days_late'] ?? 0),
                (string) ($row['detected_at'] ?? now()->toDateTimeString()),
            ]);
        }

        rewind($stream);
        $content = stream_get_contents($stream);
        fclose($stream);

        if ($content === false) {
            $this->error('Gagal membaca konten CSV.');
            return self::FAILURE;
        }

        Storage::disk('local')->put($file, $content);
        $this->info('Snapshot exception tersimpan: storage/app/' . $file);
        $this->info('Total rows: ' . count($rows));

        return self::SUCCESS;
    }

    private function overdueExtremeRows(Carbon $asOf): array
    {
        if (!Schema::hasTable('loan_items') || !Schema::hasTable('loans') || !Schema::hasTable('items')) {
            return [];
        }
        $thresholdDays = max(1, (int) config('notobuku.circulation.exceptions.overdue_extreme_days', 30));

        $query = DB::table('loan_items as li')
            ->join('loans as l', 'l.id', '=', 'li.loan_id')
            ->join('items as i', 'i.id', '=', 'li.item_id')
            ->leftJoin('members as m', 'm.id', '=', 'l.member_id')
            ->whereNull('li.returned_at')
            ->whereNotNull('li.due_at')
            ->where('li.due_at', '<', $asOf->copy()->subDays($thresholdDays)->toDateTimeString())
            ->select([
                'l.institution_id',
                'l.branch_id',
                'l.id as loan_id',
                'l.loan_code',
                'li.id as loan_item_id',
                'li.item_id',
                'i.barcode',
                'm.id as member_id',
                'm.member_code',
                DB::raw('DATEDIFF("' . $asOf->toDateString() . '", li.due_at) as days_late'),
            ])
            ->orderByDesc(DB::raw('DATEDIFF("' . $asOf->toDateString() . '", li.due_at)'))
            ->limit(300)
            ->get();

        return $query->map(function ($r) use ($asOf) {
            $daysLate = max(0, (int) ($r->days_late ?? 0));
            return [
                'exception_type' => 'overdue_extreme',
                'severity' => $daysLate >= 60 ? 'critical' : 'warning',
                'institution_id' => (int) ($r->institution_id ?? 0),
                'branch_id' => (int) ($r->branch_id ?? 0),
                'loan_id' => (int) ($r->loan_id ?? 0),
                'loan_code' => (string) ($r->loan_code ?? ''),
                'loan_item_id' => (int) ($r->loan_item_id ?? 0),
                'item_id' => (int) ($r->item_id ?? 0),
                'barcode' => (string) ($r->barcode ?? ''),
                'member_id' => (int) ($r->member_id ?? 0),
                'member_code' => (string) ($r->member_code ?? ''),
                'detail' => 'Overdue aktif melebihi threshold',
                'days_late' => $daysLate,
                'detected_at' => $asOf->toDateTimeString(),
            ];
        })->all();
    }

    private function fineVoidRows(Carbon $dayStart, Carbon $dayEnd): array
    {
        if (!Schema::hasTable('audits')) {
            return [];
        }

        $rows = DB::table('audits')
            ->where('action', 'fine.void')
            ->whereBetween('created_at', [$dayStart, $dayEnd])
            ->orderByDesc('id')
            ->limit(300)
            ->get([
                'institution_id',
                'actor_user_id',
                'auditable_id',
                'metadata',
                'created_at',
            ]);

        return $rows->map(function ($r) {
            $meta = json_decode((string) ($r->metadata ?? '{}'), true);
            if (!is_array($meta)) {
                $meta = [];
            }

            return [
                'exception_type' => 'fine_void_activity',
                'severity' => 'warning',
                'institution_id' => (int) ($r->institution_id ?? 0),
                'branch_id' => 0,
                'loan_id' => 0,
                'loan_code' => '',
                'loan_item_id' => (int) ($meta['loan_item_id'] ?? 0),
                'item_id' => 0,
                'barcode' => '',
                'member_id' => 0,
                'member_code' => '',
                'detail' => 'Fine void by user_id=' . (int) ($r->actor_user_id ?? 0) . ' fine_id=' . (int) ($r->auditable_id ?? 0),
                'days_late' => 0,
                'detected_at' => (string) ($r->created_at ?? now()->toDateTimeString()),
            ];
        })->all();
    }

    private function branchMismatchRows(): array
    {
        if (!Schema::hasTable('loan_items') || !Schema::hasTable('loans') || !Schema::hasTable('items')) {
            return [];
        }
        if (!Schema::hasColumn('loans', 'branch_id') || !Schema::hasColumn('items', 'branch_id')) {
            return [];
        }

        $rows = DB::table('loan_items as li')
            ->join('loans as l', 'l.id', '=', 'li.loan_id')
            ->join('items as i', 'i.id', '=', 'li.item_id')
            ->leftJoin('members as m', 'm.id', '=', 'l.member_id')
            ->whereNull('li.returned_at')
            ->where(function ($q) {
                $q->whereNull('l.branch_id')
                    ->orWhereNull('i.branch_id')
                    ->orWhereColumn('l.branch_id', '<>', 'i.branch_id');
            })
            ->select([
                'l.institution_id',
                'l.branch_id as loan_branch_id',
                'i.branch_id as item_branch_id',
                'l.id as loan_id',
                'l.loan_code',
                'li.id as loan_item_id',
                'li.item_id',
                'i.barcode',
                'm.id as member_id',
                'm.member_code',
            ])
            ->orderByDesc('li.id')
            ->limit(300)
            ->get();

        return $rows->map(function ($r) {
            $loanBranch = (int) ($r->loan_branch_id ?? 0);
            $itemBranch = (int) ($r->item_branch_id ?? 0);
            return [
                'exception_type' => 'branch_mismatch_active_loan',
                'severity' => 'critical',
                'institution_id' => (int) ($r->institution_id ?? 0),
                'branch_id' => $loanBranch,
                'loan_id' => (int) ($r->loan_id ?? 0),
                'loan_code' => (string) ($r->loan_code ?? ''),
                'loan_item_id' => (int) ($r->loan_item_id ?? 0),
                'item_id' => (int) ($r->item_id ?? 0),
                'barcode' => (string) ($r->barcode ?? ''),
                'member_id' => (int) ($r->member_id ?? 0),
                'member_code' => (string) ($r->member_code ?? ''),
                'detail' => 'loan.branch_id=' . $loanBranch . ' item.branch_id=' . $itemBranch,
                'days_late' => 0,
                'detected_at' => now()->toDateTimeString(),
            ];
        })->all();
    }
}

