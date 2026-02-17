<?php

namespace App\Console\Commands;

use App\Support\OpacMetrics;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendOpacSloAlert extends Command
{
    protected $signature = 'notobuku:opac-slo-alert';

    protected $description = 'Send OPAC SLO alert when burn-rate reaches warning/critical.';

    public function handle(): int
    {
        $snapshot = OpacMetrics::snapshot();
        $slo = (array) ($snapshot['slo'] ?? []);
        $state = (string) ($slo['state'] ?? 'ok');
        if (!in_array($state, ['warning', 'critical'], true)) {
            $this->info('OPAC SLO sehat. Tidak kirim alert.');
            return self::SUCCESS;
        }

        $cooldownMinutes = max(1, (int) config('notobuku.opac.slo.alert_cooldown_minutes', 15));
        $cooldownKey = 'opac:slo:alerts:last_triggered_at';
        $lastTriggered = (string) Cache::get($cooldownKey, '');
        if (!$this->shouldTrigger($lastTriggered, $cooldownMinutes)) {
            $this->info('OPAC alert masih cooldown.');
            return self::SUCCESS;
        }

        $subject = '[NOTOBUKU] OPAC SLO ' . strtoupper($state);
        $message = implode("\n", [
            'Waktu: ' . now()->toDateTimeString(),
            'State: ' . $state,
            'Burn rate 5m: ' . (float) ($slo['burn_rate_5m'] ?? 0),
            'Burn rate 60m: ' . (float) ($slo['burn_rate_60m'] ?? 0),
            'Target availability: ' . (float) ($slo['target_pct'] ?? 99.5) . '%',
            'Requests total: ' . (int) ($snapshot['requests'] ?? 0),
            'Error rate: ' . (float) ($snapshot['error_rate_pct'] ?? 0) . '%',
            'Latency p95: ' . (int) data_get($snapshot, 'latency.p95_ms', 0) . ' ms',
        ]);

        $sent = [];
        $emails = $this->resolveEmailRecipients();
        if (!empty($emails)) {
            try {
                Mail::raw($message, function ($mail) use ($emails, $subject) {
                    $mail->to($emails)->subject($subject);
                });
                $sent[] = 'email';
            } catch (\Throwable $e) {
                Log::warning('OPAC SLO email alert failed: ' . $e->getMessage());
            }
        }

        $webhook = trim((string) config('notobuku.opac.slo.alert_webhook_url', ''));
        if ($webhook !== '') {
            try {
                Http::timeout(5)->post($webhook, [
                    'event' => 'opac_slo_alert',
                    'at' => now()->toIso8601String(),
                    'state' => $state,
                    'slo' => $slo,
                    'metrics' => [
                        'requests' => (int) ($snapshot['requests'] ?? 0),
                        'errors' => (int) ($snapshot['errors'] ?? 0),
                        'error_rate_pct' => (float) ($snapshot['error_rate_pct'] ?? 0),
                        'p95_ms' => (int) data_get($snapshot, 'latency.p95_ms', 0),
                    ],
                ]);
                $sent[] = 'webhook';
            } catch (\Throwable $e) {
                Log::warning('OPAC SLO webhook alert failed: ' . $e->getMessage());
            }
        }

        Cache::put($cooldownKey, now()->toIso8601String(), now()->addDays(30));
        $this->info('OPAC SLO alert terkirim. channel=' . (empty($sent) ? 'none' : implode(',', $sent)));
        return self::SUCCESS;
    }

    private function shouldTrigger(string $lastTriggeredAt, int $cooldownMinutes): bool
    {
        if (trim($lastTriggeredAt) === '') {
            return true;
        }
        try {
            return \Illuminate\Support\Carbon::parse($lastTriggeredAt)->diffInMinutes(now()) >= $cooldownMinutes;
        } catch (\Throwable) {
            return true;
        }
    }

    /**
     * @return array<int, string>
     */
    private function resolveEmailRecipients(): array
    {
        $primary = (string) config('notobuku.opac.slo.alert_email_to', '');
        $ops = (string) config('notobuku.catalog.ops_email_to', '');
        $raw = trim($primary . ',' . $ops);
        if ($raw === '') {
            return [];
        }

        $emails = preg_split('/[;,]+/', $raw) ?: [];
        $emails = array_values(array_unique(array_filter(array_map(
            fn ($e) => trim((string) $e),
            $emails
        ))));

        return $emails;
    }
}
