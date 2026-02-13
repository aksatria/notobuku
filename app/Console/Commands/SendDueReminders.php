<?php

namespace App\Console\Commands;

use App\Mail\DueReminderMail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SendDueReminders extends Command
{
    /**
     * Signature:
     *  php artisan notobuku:reminder-jatuh-tempo
     *
     * Opsi:
     *  --dry-run : hanya menampilkan kandidat, tidak mengirim
     */
    protected $signature = 'notobuku:reminder-jatuh-tempo {--dry-run : Tampilkan kandidat tanpa mengirim}';

    protected $description = 'Kirim notifikasi pengingat jatuh tempo (H-2, H-1) dan terlambat (H+1) untuk transaksi pinjam.';

    public function handle(): int
    {
        $dryRun = (bool)$this->option('dry-run');

        $today = now()->startOfDay();

        // Reminder yang kita kirim:
        // H-2, H-1 (due_soon) dan H+1 (overdue)
        $plans = [
            ['key' => 'due_h2', 'type' => 'due_soon', 'dayOffset' => 2, 'label' => 'H-2'],
            ['key' => 'due_h1', 'type' => 'due_soon', 'dayOffset' => 1, 'label' => 'H-1'],
            // H+1 artinya: due date kemarin (today - 1)
            ['key' => 'overdue_h1', 'type' => 'overdue', 'dayOffset' => -1, 'label' => 'H+1'],
        ];

        $hasMemberEmail = Schema::hasColumn('members', 'email');
        $hasMemberPhone = Schema::hasColumn('members', 'phone');

        $totalCandidates = 0;
        $totalSent = 0;

        foreach ($plans as $plan) {
            $scheduledFor = $today->copy();
            $targetDate = $today->copy()->addDays((int)$plan['dayOffset']);

            $query = DB::table('loan_items')
                ->join('loans', 'loans.id', '=', 'loan_items.loan_id')
                ->join('members', 'members.id', '=', 'loans.member_id')
                ->join('items', 'items.id', '=', 'loan_items.item_id')

                // ✅ FIX: tabel biblio kamu adalah "biblio" (bukan "biblios")
                ->leftJoin('biblio', 'biblio.id', '=', 'items.biblio_id')

                ->whereNull('loan_items.returned_at')
                ->whereIn('loans.status', ['open'])
                ->whereDate('loan_items.due_at', '=', $targetDate->toDateString())
                ->select([
                    'loans.id as loan_id',
                    'loans.institution_id',
                    'loans.branch_id',
                    'loans.loan_code',
                    'loans.member_id',
                    'loans.due_at as loan_due_at',
                    'loan_items.id as loan_item_id',
                    'loan_items.due_at as item_due_at',
                    'items.barcode as item_barcode',

                    // ✅ FIX: alias title dari tabel "biblio"
                    'biblio.title as biblio_title',

                    'members.full_name as member_name',
                    'members.member_code as member_code',
                    $hasMemberEmail ? 'members.email as member_email' : DB::raw('NULL as member_email'),
                    $hasMemberPhone ? 'members.phone as member_phone' : DB::raw('NULL as member_phone'),
                ])
                ->orderBy('loans.id')
                ->orderBy('loan_items.id');

            $rows = $query->get();
            if ($rows->isEmpty()) {
                $this->line("[{$plan['label']}] kandidat: 0");
                continue;
            }

            $totalCandidates += $rows->count();

            // Group per loan untuk 1 email / 1 notifikasi, berisi banyak item
            $grouped = $rows->groupBy('loan_id');

            $this->line("[{$plan['label']}] kandidat loan: " . $grouped->count() . " (item: {$rows->count()})");

            foreach ($grouped as $loanId => $items) {
                $first = $items->first();
                if (!$first) continue;

                $memberEmail = $first->member_email;

                $payload = [
                    'plan_key' => $plan['key'],
                    'type' => $plan['type'],
                    'label' => $plan['label'],
                    'loan_id' => (int)$loanId,
                    'loan_code' => (string)$first->loan_code,
                    'member_id' => (int)$first->member_id,
                    'member_name' => (string)$first->member_name,
                    'member_code' => (string)$first->member_code,
                    'institution_id' => (int)$first->institution_id,
                    'branch_id' => $first->branch_id !== null ? (int)$first->branch_id : null,
                    'due_date' => (string)($first->loan_due_at ?? $first->item_due_at),
                    'items' => $items->map(function ($r) {
                        return [
                            'loan_item_id' => (int)$r->loan_item_id,
                            'barcode' => (string)($r->item_barcode ?? '-'),
                            'title' => (string)($r->biblio_title ?? ''),
                            'due_at' => (string)$r->item_due_at,
                        ];
                    })->values()->all(),
                ];

                // Idempotency: jangan kirim dua kali untuk kombinasi yang sama
                $exists = DB::table('member_notifications')
                    ->where('member_id', (int)$first->member_id)
                    ->where('loan_id', (int)$loanId)
                    ->where('type', (string)$plan['type'])
                    ->whereDate('scheduled_for', $scheduledFor->toDateString())
                    ->where('plan_key', (string)$plan['key'])
                    ->exists();

                if ($exists) {
                    $this->line(" - skip (sudah ada): {$first->loan_code}");
                    continue;
                }

                if ($dryRun) {
                    $this->line(" - DRYRUN: {$first->loan_code} → {$first->member_name}");
                    continue;
                }

                // Simpan log notif (in-app selalu)
                $notifId = DB::table('member_notifications')->insertGetId([
                    'institution_id' => (int)$first->institution_id,
                    'member_id' => (int)$first->member_id,
                    'loan_id' => (int)$loanId,
                    'type' => (string)$plan['type'],
                    'plan_key' => (string)$plan['key'],
                    'channel' => ($memberEmail && filter_var($memberEmail, FILTER_VALIDATE_EMAIL)) ? 'email' : 'inapp',
                    'status' => 'queued',
                    'scheduled_for' => $scheduledFor,
                    'sent_at' => null,
                    'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Email (opsional): hanya jika kolom email ada & nilainya valid
                if ($memberEmail && filter_var($memberEmail, FILTER_VALIDATE_EMAIL)) {
                    try {
                        Mail::to($memberEmail)->send(new DueReminderMail($payload));

                        DB::table('member_notifications')->where('id', $notifId)->update([
                            'status' => 'sent',
                            'sent_at' => now(),
                            'updated_at' => now(),
                        ]);

                        $totalSent++;
                        $this->line(" - sent: {$first->loan_code} → {$memberEmail}");
                    } catch (\Throwable $e) {
                        DB::table('member_notifications')->where('id', $notifId)->update([
                            'status' => 'failed',
                            'error_message' => Str::limit($e->getMessage(), 900),
                            'updated_at' => now(),
                        ]);
                        $this->error(" - failed: {$first->loan_code} → {$memberEmail} | {$e->getMessage()}");
                    }
                } else {
                    // In-app only
                    DB::table('member_notifications')->where('id', $notifId)->update([
                        'status' => 'sent',
                        'sent_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $totalSent++;
                    $this->line(" - inapp: {$first->loan_code} → {$first->member_name}");
                }
            }
        }

        $this->info("Selesai. Kandidat item: {$totalCandidates}. Notif terkirim: {$totalSent}.");

        return self::SUCCESS;
    }
}
