<?php

namespace App\Console\Commands;

use App\Support\CirculationSlaClock;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

class SendCirculationPicReminder extends Command
{
    protected $signature = 'notobuku:circulation-pic-reminder';

    protected $description = 'Send SLA breach reminder grouped by PIC owner for unresolved circulation exceptions.';

    public function handle(): int
    {
        if (!Schema::hasTable('circulation_exception_acknowledgements')) {
            $this->warn('Tabel circulation_exception_acknowledgements belum tersedia.');
            return self::SUCCESS;
        }

        $slaHours = max(1, (int) config('notobuku.circulation.pic_reminder.sla_hours', 24));
        $cooldownMinutes = max(1, (int) config('notobuku.circulation.pic_reminder.cooldown_minutes', 120));
        $fallbackEmail = trim((string) config('notobuku.circulation.pic_reminder.fallback_email_to', ''));

        $rows = DB::table('circulation_exception_acknowledgements as a')
            ->leftJoin('users as u', 'u.id', '=', 'a.owner_user_id')
            ->whereIn('a.status', ['open', 'ack'])
            ->whereNotNull('a.owner_user_id')
            ->get([
                'a.id',
                'a.snapshot_date',
                'a.exception_type',
                'a.severity',
                'a.status',
                'a.loan_id',
                'a.barcode',
                'a.owner_user_id',
                'a.owner_assigned_at',
                'u.name as owner_name',
                'u.email as owner_email',
            ]);

        $byOwner = [];
        foreach ($rows as $row) {
            $age = $this->ageHours((string) ($row->owner_assigned_at ?? ''), (string) ($row->snapshot_date ?? ''));
            if ($age < $slaHours) {
                continue;
            }

            $ownerId = (int) ($row->owner_user_id ?? 0);
            if ($ownerId <= 0) {
                continue;
            }

            if (!isset($byOwner[$ownerId])) {
                $byOwner[$ownerId] = [
                    'owner_id' => $ownerId,
                    'owner_name' => (string) ($row->owner_name ?? ('User #' . $ownerId)),
                    'owner_email' => (string) ($row->owner_email ?? ''),
                    'items' => [],
                ];
            }

            $byOwner[$ownerId]['items'][] = [
                'id' => (int) $row->id,
                'snapshot_date' => (string) ($row->snapshot_date ?? ''),
                'exception_type' => (string) ($row->exception_type ?? ''),
                'severity' => (string) ($row->severity ?? ''),
                'status' => (string) ($row->status ?? ''),
                'loan_id' => (int) ($row->loan_id ?? 0),
                'barcode' => (string) ($row->barcode ?? ''),
                'age_hours' => $age,
            ];
        }

        if (empty($byOwner)) {
            $this->info('Tidak ada item breach untuk PIC reminder.');
            return self::SUCCESS;
        }

        $sent = 0;
        foreach ($byOwner as $owner) {
            $ownerId = (int) ($owner['owner_id'] ?? 0);
            $cooldownKey = 'circulation:pic:reminder:last:' . $ownerId;
            $last = (string) Cache::get($cooldownKey, '');
            if (!$this->shouldTrigger($last, $cooldownMinutes)) {
                continue;
            }

            $to = trim((string) ($owner['owner_email'] ?? ''));
            if ($to === '') {
                $to = $fallbackEmail;
            }
            if ($to === '') {
                continue;
            }

            $subject = '[NOTOBUKU] PIC Reminder - Circulation Exceptions';
            $body = $this->buildBody($owner);

            try {
                Mail::raw($body, function ($mail) use ($to, $subject) {
                    $mail->to($to)->subject($subject);
                });
                Cache::put($cooldownKey, now()->toIso8601String(), now()->addDays(30));
                $sent++;
            } catch (\Throwable $e) {
                Log::warning('Failed sending circulation PIC reminder: ' . $e->getMessage());
            }
        }

        $this->info('PIC reminder selesai. total_sent=' . $sent);
        return self::SUCCESS;
    }

    private function buildBody(array $owner): string
    {
        $lines = [];
        $lines[] = 'PIC: ' . (string) ($owner['owner_name'] ?? '');
        $lines[] = 'Waktu: ' . now()->toDateTimeString();
        $lines[] = 'Jumlah item SLA breach: ' . count((array) ($owner['items'] ?? []));
        $lines[] = 'Daftar item (maks 20):';
        foreach (array_slice((array) ($owner['items'] ?? []), 0, 20) as $item) {
            $lines[] = '- #' . (int) ($item['id'] ?? 0)
                . ' [' . (string) ($item['severity'] ?? '') . ']'
                . ' ' . (string) ($item['exception_type'] ?? '')
                . ' age=' . (int) ($item['age_hours'] ?? 0) . 'h'
                . ' loan=' . (int) ($item['loan_id'] ?? 0)
                . ' barcode=' . (string) ($item['barcode'] ?? '');
        }

        return implode("\n", $lines);
    }

    private function ageHours(string $ownerAssignedAt, string $snapshotDate): int
    {
        if (trim($ownerAssignedAt) !== '') {
            return CirculationSlaClock::elapsedHoursFrom($ownerAssignedAt, null);
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
}
