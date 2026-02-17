<?php

namespace App\Console\Commands;

use App\Support\ReservationMetrics;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

class SendReservationKpiAlert extends Command
{
    protected $signature = 'notobuku:reservation-kpi-alert {--institution=}';

    protected $description = 'Send reservation KPI alert when backlog rises or fulfillment drops.';

    public function handle(): int
    {
        $institutionId = (int) ($this->option('institution') ?? 0);
        if ($institutionId <= 0) {
            $institutionId = null;
        }

        $snapshot = ReservationMetrics::snapshot($institutionId);
        if (!(bool) ($snapshot['ok'] ?? false)) {
            $this->warn('Reservation metrics unavailable.');
            return self::SUCCESS;
        }

        $kpi = (array) ($snapshot['kpi'] ?? []);
        $totals = (array) ($snapshot['totals'] ?? []);

        $fulfillment = (float) ($kpi['fulfillment_rate_pct'] ?? 0);
        $expiry = (float) ($kpi['expiry_rate_pct'] ?? 0);
        $backlog = (int) ($totals['backlog_queued'] ?? 0);

        $minFulfillment = (float) config('notobuku.reservations.kpi.alert_fulfillment_min_pct', 70);
        $maxBacklog = (int) config('notobuku.reservations.kpi.alert_backlog_max', 100);
        $maxExpiry = (float) config('notobuku.reservations.kpi.alert_expiry_max_pct', 20);

        $shouldAlert = $fulfillment < $minFulfillment || $backlog > $maxBacklog || $expiry > $maxExpiry;
        if (!$shouldAlert) {
            $this->audit('reservation_kpi_alert', 'skipped', ['reason' => 'healthy', 'snapshot' => $snapshot]);
            $this->info('Reservation KPI sehat. Tidak ada alert.');
            return self::SUCCESS;
        }

        $cooldownMinutes = max(1, (int) config('notobuku.reservations.kpi.alert_cooldown_minutes', 30));
        $cacheKey = 'reservations:kpi:alert:last';
        $last = (string) Cache::get($cacheKey, '');
        if (!$this->shouldTrigger($last, $cooldownMinutes)) {
            $this->audit('reservation_kpi_alert', 'skipped', ['reason' => 'cooldown', 'snapshot' => $snapshot]);
            $this->info('Reservation KPI alert cooldown aktif.');
            return self::SUCCESS;
        }

        $subject = '[NOTOBUKU] Reservation KPI Alert';
        $message = implode("\n", [
            'Waktu: ' . now()->toDateTimeString(),
            'Fulfillment rate: ' . number_format($fulfillment, 2, '.', '') . '%',
            'Expiry rate: ' . number_format($expiry, 2, '.', '') . '%',
            'Backlog queued: ' . $backlog,
            'Window days: ' . (int) ($snapshot['window_days'] ?? 0),
        ]);

        $channels = [];
        $emailTo = trim((string) config('notobuku.reservations.kpi.alert_email_to', ''));
        if ($emailTo !== '') {
            try {
                Mail::raw($message, function ($mail) use ($emailTo, $subject) {
                    $mail->to($emailTo)->subject($subject);
                });
                $channels[] = 'email';
            } catch (\Throwable $e) {
                Log::warning('Reservation KPI email alert failed: ' . $e->getMessage());
            }
        }

        $webhook = trim((string) config('notobuku.reservations.kpi.alert_webhook_url', ''));
        if ($webhook !== '') {
            try {
                Http::timeout(6)->post($webhook, [
                    'event' => 'reservation_kpi_alert',
                    'at' => now()->toIso8601String(),
                    'snapshot' => $snapshot,
                ]);
                $channels[] = 'webhook';
            } catch (\Throwable $e) {
                Log::warning('Reservation KPI webhook alert failed: ' . $e->getMessage());
            }
        }

        Cache::put($cacheKey, now()->toIso8601String(), now()->addDays(30));
        $status = empty($channels) ? 'no_channel' : 'sent';
        $this->audit('reservation_kpi_alert', $status, ['channels' => $channels, 'snapshot' => $snapshot]);

        $this->info('Reservation KPI alert selesai. status=' . $status);

        return self::SUCCESS;
    }

    private function shouldTrigger(string $lastTriggered, int $cooldownMinutes): bool
    {
        if (trim($lastTriggered) === '') {
            return true;
        }

        try {
            return now()->diffInMinutes($lastTriggered) >= $cooldownMinutes;
        } catch (\Throwable $e) {
            return true;
        }
    }

    private function audit(string $action, string $status, array $meta): void
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
