<?php

namespace App\Console\Commands;

use App\Support\CirculationSlaClock;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

class SendCirculationExceptionEscalation extends Command
{
    protected $signature = 'notobuku:circulation-exception-escalation';

    protected $description = 'Escalate unresolved circulation exceptions based on SLA age (warning/critical).';

    public function handle(): int
    {
        if (!Schema::hasTable('circulation_exception_acknowledgements')) {
            $this->warn('Tabel circulation_exception_acknowledgements belum tersedia.');
            return self::SUCCESS;
        }

        $warningHours = max(1, (int) config('notobuku.circulation.escalation.warning_hours', 24));
        $criticalHours = max($warningHours + 1, (int) config('notobuku.circulation.escalation.critical_hours', 72));
        $cooldownMinutes = max(1, (int) config('notobuku.circulation.escalation.cooldown_minutes', 60));

        $rows = DB::table('circulation_exception_acknowledgements')
            ->whereIn('status', ['open', 'ack'])
            ->get([
                'id',
                'institution_id',
                'snapshot_date',
                'fingerprint',
                'exception_type',
                'severity',
                'status',
                'loan_id',
                'loan_item_id',
                'item_id',
                'barcode',
                'member_id',
                'ack_at',
                'created_at',
            ]);

        $warning = [];
        $critical = [];
        foreach ($rows as $row) {
            $age = $this->ageHours(
                (string) ($row->ack_at ?? ''),
                (string) ($row->created_at ?? ''),
                (string) ($row->snapshot_date ?? '')
            );

            $payload = [
                'id' => (int) $row->id,
                'institution_id' => (int) ($row->institution_id ?? 0),
                'snapshot_date' => (string) ($row->snapshot_date ?? ''),
                'fingerprint' => (string) ($row->fingerprint ?? ''),
                'exception_type' => (string) ($row->exception_type ?? ''),
                'severity' => (string) ($row->severity ?? ''),
                'status' => (string) ($row->status ?? ''),
                'loan_id' => (int) ($row->loan_id ?? 0),
                'loan_item_id' => (int) ($row->loan_item_id ?? 0),
                'item_id' => (int) ($row->item_id ?? 0),
                'barcode' => (string) ($row->barcode ?? ''),
                'member_id' => (int) ($row->member_id ?? 0),
                'age_hours' => $age,
            ];

            if ($age >= $criticalHours) {
                $critical[] = $payload;
            } elseif ($age >= $warningHours) {
                $warning[] = $payload;
            }
        }

        if (empty($warning) && empty($critical)) {
            $this->writeAudit('circulation_exception_escalation', 'skipped', [
                'reason' => 'no_candidates',
                'warning_hours' => $warningHours,
                'critical_hours' => $criticalHours,
            ]);
            $this->info('Tidak ada exception untuk escalation.');
            return self::SUCCESS;
        }

        $sent = [];
        if (!empty($warning)) {
            if ($this->triggerLevel('warning', $warning, $cooldownMinutes)) {
                $sent[] = 'warning';
            }
        }
        if (!empty($critical)) {
            if ($this->triggerLevel('critical', $critical, $cooldownMinutes)) {
                $sent[] = 'critical';
            }
        }

        $this->writeAudit('circulation_exception_escalation', !empty($sent) ? 'sent' : 'skipped', [
            'sent_levels' => $sent,
            'warning_count' => count($warning),
            'critical_count' => count($critical),
            'warning_hours' => $warningHours,
            'critical_hours' => $criticalHours,
        ]);

        $this->info('Escalation selesai. sent_levels=' . implode(',', $sent));
        return self::SUCCESS;
    }

    private function triggerLevel(string $level, array $items, int $cooldownMinutes): bool
    {
        $key = 'circulation:exception:escalation:last:' . $level;
        $last = (string) Cache::get($key, '');
        if (!$this->shouldTrigger($last, $cooldownMinutes)) {
            return false;
        }

        $emailTo = trim((string) config(
            'notobuku.circulation.escalation.' . ($level === 'critical' ? 'critical_email_to' : 'warning_email_to'),
            ''
        ));
        $webhookUrl = trim((string) config('notobuku.circulation.escalation.webhook_url', ''));

        $subject = "[NOTOBUKU] Circulation Exception Escalation " . strtoupper($level);
        $body = $this->buildMessage($level, $items);

        $sent = false;
        if ($emailTo !== '') {
            try {
                Mail::raw($body, function ($mail) use ($emailTo, $subject) {
                    $mail->to($emailTo)->subject($subject);
                });
                $sent = true;
            } catch (\Throwable $e) {
                Log::warning('Failed sending circulation escalation email: ' . $e->getMessage());
            }
        }

        if ($webhookUrl !== '') {
            try {
                Http::timeout(5)->post($webhookUrl, [
                    'event' => 'circulation_exception_escalation',
                    'level' => $level,
                    'at' => now()->toIso8601String(),
                    'count' => count($items),
                    'items' => array_slice($items, 0, 50),
                ]);
                $sent = true;
            } catch (\Throwable $e) {
                Log::warning('Failed sending circulation escalation webhook: ' . $e->getMessage());
            }
        }

        if ($sent) {
            Cache::put($key, now()->toIso8601String(), now()->addDays(30));
        }

        return $sent;
    }

    private function buildMessage(string $level, array $items): string
    {
        $lines = [];
        $lines[] = 'Waktu: ' . now()->toDateTimeString();
        $lines[] = 'Level: ' . strtoupper($level);
        $lines[] = 'Jumlah item: ' . count($items);
        $lines[] = 'Contoh item:';
        foreach (array_slice($items, 0, 10) as $it) {
            $lines[] = '- #' . (int) ($it['id'] ?? 0)
                . ' type=' . (string) ($it['exception_type'] ?? '')
                . ' status=' . (string) ($it['status'] ?? '')
                . ' age=' . (int) ($it['age_hours'] ?? 0) . 'h'
                . ' loan=' . (int) ($it['loan_id'] ?? 0)
                . ' barcode=' . (string) ($it['barcode'] ?? '');
        }
        return implode("\n", $lines);
    }

    private function ageHours(string $ackAt, string $createdAt, string $snapshotDate): int
    {
        if (trim($ackAt) !== '') {
            return CirculationSlaClock::elapsedHoursFrom($ackAt, null);
        }
        if (trim($createdAt) !== '') {
            return CirculationSlaClock::elapsedHoursFrom($createdAt, null);
        }
        return CirculationSlaClock::elapsedHoursFrom(null, $snapshotDate);
    }

    private function shouldTrigger(string $lastTriggeredAt, int $cooldownMinutes): bool
    {
        if (trim($lastTriggeredAt) === '') {
            return true;
        }
        try {
            $last = \Illuminate\Support\Carbon::parse($lastTriggeredAt);
            return $last->diffInMinutes(now()) >= $cooldownMinutes;
        } catch (\Throwable $e) {
            return true;
        }
    }

    private function writeAudit(string $action, string $status, array $meta): void
    {
        if (!Schema::hasTable('audit_logs')) {
            return;
        }
        try {
            DB::table('audit_logs')->insert([
                'user_id' => null,
                'action' => $action,
                'format' => 'system',
                'status' => $status,
                'meta' => json_encode($meta, JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // ignore
        }
    }
}
