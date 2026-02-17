<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ReservationNotificationService
{
    public function queueForReservationEvent(array $reservation, string $eventType): void
    {
        if (!Schema::hasTable('member_notifications')) {
            return;
        }

        $memberId = (int) ($reservation['member_id'] ?? 0);
        $institutionId = (int) ($reservation['institution_id'] ?? 0);
        $reservationId = (int) ($reservation['id'] ?? 0);
        if ($memberId <= 0 || $institutionId <= 0 || $reservationId <= 0) {
            return;
        }

        $channels = (array) config('notobuku.reservations.notification.channels', ['inapp', 'email']);
        if (empty($channels)) {
            $channels = ['inapp'];
        }

        $payload = $this->buildPayload($reservation, $eventType);
        $maxAttempts = max(1, (int) config('notobuku.reservations.notification.max_attempts', 5));

        foreach ($channels as $channelRaw) {
            $channel = strtolower(trim((string) $channelRaw));
            if ($channel === '') {
                continue;
            }

            $exists = DB::table('member_notifications')
                ->where('member_id', $memberId)
                ->where('reservation_id', $reservationId)
                ->where('type', 'reservation_' . $eventType)
                ->where('channel', $channel)
                ->whereDate('scheduled_for', now()->toDateString())
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('member_notifications')->insert([
                'institution_id' => $institutionId,
                'member_id' => $memberId,
                'loan_id' => null,
                'reservation_id' => $reservationId,
                'type' => 'reservation_' . $eventType,
                'plan_key' => 'reservation_' . $eventType,
                'channel' => $channel,
                'status' => 'queued',
                'attempt_count' => 0,
                'max_attempts' => $maxAttempts,
                'scheduled_for' => now(),
                'next_retry_at' => now(),
                'sent_at' => null,
                'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function dispatchPending(int $limit = 200): array
    {
        if (!Schema::hasTable('member_notifications')) {
            return ['sent' => 0, 'failed' => 0, 'dead_letter' => 0];
        }

        $limit = max(1, min(1000, $limit));
        $rows = DB::table('member_notifications')
            ->whereIn('status', ['queued', 'failed'])
            ->where(function ($q) {
                if (Schema::hasColumn('member_notifications', 'next_retry_at')) {
                    $q->whereNull('next_retry_at')->orWhere('next_retry_at', '<=', now());
                    return;
                }
                $q->where('scheduled_for', '<=', now());
            })
            ->orderBy('scheduled_for')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $stats = ['sent' => 0, 'failed' => 0, 'dead_letter' => 0];

        foreach ($rows as $row) {
            $payload = [];
            try {
                $payload = is_string($row->payload) ? (json_decode($row->payload, true) ?: []) : (array) $row->payload;
            } catch (\Throwable $e) {
                $payload = [];
            }

            $attempt = (int) ($row->attempt_count ?? 0) + 1;
            $maxAttempts = max(1, (int) ($row->max_attempts ?? config('notobuku.reservations.notification.max_attempts', 5)));

            $result = $this->sendToChannel((string) $row->channel, (int) $row->member_id, $payload, (string) ($row->type ?? 'reservation_event'));

            if ($result['ok']) {
                DB::table('member_notifications')->where('id', $row->id)->update([
                    'status' => 'sent',
                    'attempt_count' => $attempt,
                    'sent_at' => now(),
                    'error_message' => null,
                    'updated_at' => now(),
                ]);
                $stats['sent']++;
                continue;
            }

            $retryBase = max(1, (int) config('notobuku.reservations.notification.retry_base_minutes', 3));
            $nextRetryAt = now()->addMinutes($retryBase * (int) pow(2, max(0, $attempt - 1)));

            if ($attempt >= $maxAttempts) {
                DB::table('member_notifications')->where('id', $row->id)->update([
                    'status' => 'dead_letter',
                    'attempt_count' => $attempt,
                    'dead_lettered_at' => now(),
                    'error_message' => Str::limit((string) ($result['error'] ?? 'unknown error'), 900),
                    'updated_at' => now(),
                ]);
                $stats['dead_letter']++;
            } else {
                DB::table('member_notifications')->where('id', $row->id)->update([
                    'status' => 'failed',
                    'attempt_count' => $attempt,
                    'next_retry_at' => $nextRetryAt,
                    'error_message' => Str::limit((string) ($result['error'] ?? 'unknown error'), 900),
                    'updated_at' => now(),
                ]);
                $stats['failed']++;
            }
        }

        return $stats;
    }

    private function sendToChannel(string $channel, int $memberId, array $payload, string $type): array
    {
        $channel = strtolower(trim($channel));

        if ($channel === 'inapp') {
            return ['ok' => true];
        }

        $member = null;
        if (Schema::hasTable('members')) {
            $member = DB::table('members')->where('id', $memberId)->first(['id', 'full_name', 'email', 'phone']);
        }

        if ($channel === 'email') {
            $to = trim((string) ($member->email ?? ''));
            if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
                $to = trim((string) config('notobuku.reservations.notification.fallback_email_to', ''));
            }
            if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
                return ['ok' => false, 'error' => 'email tidak tersedia'];
            }

            try {
                $subject = '[NOTOBUKU] Update reservasi';
                $body = (string) ($payload['message'] ?? 'Ada pembaruan status reservasi Anda.');
                Mail::raw($body, function ($mail) use ($to, $subject) {
                    $mail->to($to)->subject($subject);
                });
                return ['ok' => true];
            } catch (\Throwable $e) {
                return ['ok' => false, 'error' => $e->getMessage()];
            }
        }

        if ($channel === 'whatsapp' || $channel === 'push') {
            $url = trim((string) config('notobuku.reservations.notification.' . ($channel === 'whatsapp' ? 'whatsapp_webhook' : 'push_webhook'), ''));
            if ($url === '') {
                return ['ok' => false, 'error' => $channel . ' webhook belum disetel'];
            }

            try {
                Http::timeout(8)->post($url, [
                    'channel' => $channel,
                    'type' => $type,
                    'member' => [
                        'id' => $memberId,
                        'name' => (string) ($member->full_name ?? ''),
                        'phone' => (string) ($member->phone ?? ''),
                        'email' => (string) ($member->email ?? ''),
                    ],
                    'payload' => $payload,
                ])->throw();
                return ['ok' => true];
            } catch (\Throwable $e) {
                Log::warning('Reservation notification webhook failed: ' . $e->getMessage());
                return ['ok' => false, 'error' => $e->getMessage()];
            }
        }

        return ['ok' => false, 'error' => 'channel tidak dikenali'];
    }

    private function buildPayload(array $reservation, string $eventType): array
    {
        $title = trim((string) ($reservation['biblio_title'] ?? 'Judul tidak tersedia'));
        $memberName = trim((string) ($reservation['member_name'] ?? 'Pemustaka'));

        $message = match ($eventType) {
            'created' => "Reservasi untuk '{$title}' sudah masuk antrean.",
            'ready' => "Reservasi '{$title}' siap diambil.",
            'expired' => "Reservasi '{$title}' kedaluwarsa karena melewati batas ambil.",
            'cancelled' => "Reservasi '{$title}' dibatalkan.",
            'fulfilled' => "Reservasi '{$title}' sudah dipenuhi.",
            default => "Ada pembaruan reservasi '{$title}'.",
        };

        return [
            'event' => $eventType,
            'member_name' => $memberName,
            'title' => $title,
            'queue_no' => (int) ($reservation['queue_no'] ?? 0),
            'status' => (string) ($reservation['status'] ?? ''),
            'expires_at' => (string) ($reservation['expires_at'] ?? ''),
            'message' => $message,
        ];
    }
}
