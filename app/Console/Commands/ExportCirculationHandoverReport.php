<?php

namespace App\Console\Commands;

use App\Support\CirculationSlaClock;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class ExportCirculationHandoverReport extends Command
{
    protected $signature = 'notobuku:circulation-handover-report {--date=}';

    protected $description = 'Generate daily circulation handover report (SLA, top reasons, unresolved list).';

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

        $sourcePath = 'reports/circulation-exceptions/circulation-exceptions-' . $day . '.csv';
        if (!Storage::disk('local')->exists($sourcePath)) {
            $this->warn('Snapshot exception tidak ditemukan: storage/app/' . $sourcePath);
            return self::SUCCESS;
        }

        $rows = $this->loadSnapshotRows($sourcePath);
        if (empty($rows)) {
            $this->warn('Snapshot kosong, handover report dilewati.');
            return self::SUCCESS;
        }

        $sla = $this->buildSlaSummary($rows);
        $reasons = $this->buildTopReasons($rows);
        $unresolved = array_values(array_filter($rows, function (array $row) {
            $status = strtolower(trim((string) ($row['status'] ?? 'open')));
            return !in_array($status, ['resolved', 'closed'], true);
        }));

        $dir = 'reports/circulation-handover';
        $base = 'circulation-handover-' . $day;
        $mdPath = $dir . '/' . $base . '.md';
        $csvPath = $dir . '/' . $base . '-unresolved.csv';

        Storage::disk('local')->put($mdPath, $this->buildMarkdown($day, $sla, $reasons, $unresolved));
        Storage::disk('local')->put($csvPath, $this->buildUnresolvedCsv($unresolved));

        $this->info('Handover report tersimpan: storage/app/' . $mdPath);
        $this->info('Unresolved CSV tersimpan: storage/app/' . $csvPath);
        return self::SUCCESS;
    }

    private function loadSnapshotRows(string $path): array
    {
        $content = Storage::disk('local')->get($path);
        if (!is_string($content) || trim($content) === '') {
            return [];
        }

        $lines = preg_split('/\r\n|\n|\r/', $content);
        if (!$lines || count($lines) < 2) {
            return [];
        }

        $headers = str_getcsv((string) array_shift($lines));
        $headers = array_map(fn($h) => trim((string) $h), $headers);

        $rows = [];
        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            $values = str_getcsv($line);
            $assoc = [];
            foreach ($headers as $idx => $header) {
                $assoc[$header] = (string) ($values[$idx] ?? '');
            }
            $rows[] = [
                'snapshot_date' => (string) ($assoc['snapshot_date'] ?? ''),
                'exception_type' => (string) ($assoc['exception_type'] ?? ''),
                'severity' => (string) ($assoc['severity'] ?? ''),
                'loan_code' => (string) ($assoc['loan_code'] ?? ''),
                'loan_id' => (int) ($assoc['loan_id'] ?? 0),
                'loan_item_id' => (int) ($assoc['loan_item_id'] ?? 0),
                'item_id' => (int) ($assoc['item_id'] ?? 0),
                'barcode' => (string) ($assoc['barcode'] ?? ''),
                'member_id' => (int) ($assoc['member_id'] ?? 0),
                'member_code' => (string) ($assoc['member_code'] ?? ''),
                'detail' => (string) ($assoc['detail'] ?? ''),
                'detected_at' => (string) ($assoc['detected_at'] ?? ''),
                'status' => 'open',
            ];
        }

        return $rows;
    }

    private function buildSlaSummary(array $rows): array
    {
        $summary = [
            'total' => count($rows),
            'open_over_24h' => 0,
            'open_over_72h' => 0,
        ];

        foreach ($rows as $row) {
            $age = $this->ageHours((string) ($row['detected_at'] ?? ''), (string) ($row['snapshot_date'] ?? ''));
            if ($age >= 24) {
                $summary['open_over_24h']++;
            }
            if ($age >= 72) {
                $summary['open_over_72h']++;
            }
        }

        return $summary;
    }

    private function buildTopReasons(array $rows): array
    {
        $counts = [];
        foreach ($rows as $row) {
            $reason = trim((string) ($row['exception_type'] ?? 'unknown'));
            if ($reason === '') {
                $reason = 'unknown';
            }
            $counts[$reason] = ($counts[$reason] ?? 0) + 1;
        }
        arsort($counts);

        $top = [];
        foreach (array_slice($counts, 0, 5, true) as $reason => $count) {
            $top[] = ['reason' => $reason, 'count' => (int) $count];
        }
        return $top;
    }

    private function buildMarkdown(string $day, array $sla, array $reasons, array $unresolved): string
    {
        $lines = [];
        $lines[] = '# Circulation Handover Report';
        $lines[] = '';
        $lines[] = 'Date: ' . $day;
        $lines[] = 'Generated at: ' . now()->toDateTimeString();
        $lines[] = '';
        $lines[] = '## SLA Summary';
        $lines[] = '- Total exceptions: ' . (int) ($sla['total'] ?? 0);
        $lines[] = '- Open >24h: ' . (int) ($sla['open_over_24h'] ?? 0);
        $lines[] = '- Open >72h: ' . (int) ($sla['open_over_72h'] ?? 0);
        $lines[] = '';
        $lines[] = '## Top Reasons';
        if (empty($reasons)) {
            $lines[] = '- n/a';
        } else {
            foreach ($reasons as $row) {
                $lines[] = '- ' . (string) ($row['reason'] ?? 'unknown') . ': ' . (int) ($row['count'] ?? 0);
            }
        }
        $lines[] = '';
        $lines[] = '## Unresolved Items (Top 20)';
        if (empty($unresolved)) {
            $lines[] = '- n/a';
        } else {
            foreach (array_slice($unresolved, 0, 20) as $row) {
                $lines[] = '- [' . (string) ($row['severity'] ?? '') . '] '
                    . (string) ($row['exception_type'] ?? '')
                    . ' loan=' . (string) ($row['loan_code'] ?? '')
                    . ' barcode=' . (string) ($row['barcode'] ?? '')
                    . ' member=' . (string) ($row['member_code'] ?? '');
            }
        }
        $lines[] = '';
        return implode("\n", $lines);
    }

    private function buildUnresolvedCsv(array $rows): string
    {
        $stream = fopen('php://temp', 'w+');
        if ($stream === false) {
            return '';
        }
        fputcsv($stream, [
            'snapshot_date',
            'exception_type',
            'severity',
            'loan_code',
            'loan_id',
            'loan_item_id',
            'item_id',
            'barcode',
            'member_id',
            'member_code',
            'detail',
            'detected_at',
        ]);
        foreach ($rows as $row) {
            fputcsv($stream, [
                (string) ($row['snapshot_date'] ?? ''),
                (string) ($row['exception_type'] ?? ''),
                (string) ($row['severity'] ?? ''),
                (string) ($row['loan_code'] ?? ''),
                (int) ($row['loan_id'] ?? 0),
                (int) ($row['loan_item_id'] ?? 0),
                (int) ($row['item_id'] ?? 0),
                (string) ($row['barcode'] ?? ''),
                (int) ($row['member_id'] ?? 0),
                (string) ($row['member_code'] ?? ''),
                (string) ($row['detail'] ?? ''),
                (string) ($row['detected_at'] ?? ''),
            ]);
        }
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return is_string($csv) ? $csv : '';
    }

    private function ageHours(string $detectedAt, string $snapshotDate): int
    {
        return CirculationSlaClock::elapsedHoursFrom($detectedAt, $snapshotDate);
    }
}
