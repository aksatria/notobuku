<?php

namespace App\Console\Commands;

use App\Support\CirculationMetrics;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

class SendCirculationHealthAlert extends Command
{
    protected $signature = 'notobuku:circulation-health-alert';

    protected $description = 'Send circulation observability health alert (email/webhook) when status is warning/critical.';

    public function handle(): int
    {
        $snapshot = CirculationMetrics::snapshot();
        $health = (array) ($snapshot['health'] ?? []);
        $label = (string) ($health['label'] ?? 'Sehat');
        $p95 = (int) ($health['p95_ms'] ?? 0);
        $failureRate = (float) ($health['business_failure_rate_pct'] ?? 0);

        if ($label === 'Sehat') {
            $this->writeAudit('circulation_health_alert', 'skipped', [
                'reason' => 'healthy',
                'health' => $health,
            ]);
            $this->info('Circulation health sehat. Tidak ada alert yang dikirim.');
            return self::SUCCESS;
        }

        $cooldownMinutes = max(1, (int) config('notobuku.circulation.alerts.cooldown_minutes', 20));
        $cooldownKey = 'circulation:metrics:alerts:last_triggered_at';
        $lastTriggered = (string) Cache::get($cooldownKey, '');
        if (!$this->shouldTriggerAlert($lastTriggered, $cooldownMinutes)) {
            $this->writeAudit('circulation_health_alert', 'skipped', [
                'reason' => 'cooldown',
                'last_triggered_at' => $lastTriggered,
                'health' => $health,
            ]);
            $this->info('Alert masih cooldown. Pengiriman dilewati.');
            return self::SUCCESS;
        }

        $emailTo = trim((string) config('notobuku.circulation.alerts.email_to', ''));
        $webhookUrl = trim((string) config('notobuku.circulation.alerts.webhook_url', ''));

        $subject = "[NOTOBUKU] Circulation Health {$label}";
        $message = implode("\n", [
            'Waktu: ' . now()->toDateTimeString(),
            'Status: ' . $label,
            'p95 Latency: ' . $p95 . ' ms',
            'Business Failure Rate: ' . number_format($failureRate, 2, '.', '') . '%',
            'Top Failure Reasons: ' . $this->topReasonsAsString((array) ($snapshot['top_failure_reasons'] ?? [])),
        ]);

        $sentChannels = [];
        if ($emailTo !== '') {
            try {
                Mail::raw($message, function ($mail) use ($emailTo, $subject) {
                    $mail->to($emailTo)->subject($subject);
                });
                $sentChannels[] = 'email';
            } catch (\Throwable $e) {
                Log::warning('Failed sending circulation email alert: ' . $e->getMessage());
            }
        }

        if ($webhookUrl !== '') {
            try {
                Http::timeout(5)->post($webhookUrl, [
                    'event' => 'circulation_health_alert',
                    'at' => now()->toIso8601String(),
                    'health' => $health,
                    'totals' => (array) ($snapshot['totals'] ?? []),
                    'top_failure_reasons' => (array) ($snapshot['top_failure_reasons'] ?? []),
                ]);
                $sentChannels[] = 'webhook';
            } catch (\Throwable $e) {
                Log::warning('Failed sending circulation webhook alert: ' . $e->getMessage());
            }
        }

        $status = empty($sentChannels) ? 'no_channel' : 'sent';
        Cache::put($cooldownKey, now()->toIso8601String(), now()->addDays(30));

        $this->writeAudit('circulation_health_alert', $status, [
            'channels' => $sentChannels,
            'health' => $health,
            'totals' => (array) ($snapshot['totals'] ?? []),
        ]);

        $this->info('Circulation health alert selesai. status=' . $status);
        return self::SUCCESS;
    }

    private function topReasonsAsString(array $rows): string
    {
        if (count($rows) === 0) {
            return 'n/a';
        }
        $chunks = [];
        foreach (array_slice($rows, 0, 3) as $row) {
            $chunks[] = (string) ($row['reason'] ?? 'unknown') . ':' . (int) ($row['count'] ?? 0);
        }
        return implode(', ', $chunks);
    }

    private function shouldTriggerAlert(string $lastTriggeredAt, int $cooldownMinutes): bool
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
            // ignore audit failures
        }
    }
}

