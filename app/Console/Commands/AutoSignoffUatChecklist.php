<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AutoSignoffUatChecklist extends Command
{
    protected $signature = 'notobuku:uat-auto-signoff
        {--date= : Tanggal sign-off (YYYY-MM-DD)}
        {--window-days=30 : Window readiness dalam hari}
        {--operator= : Nama operator otomatis}
        {--note= : Catatan tambahan}
        {--strict-ready : Pakai strict-ready saat evaluasi readiness}';

    protected $description = 'Auto sign-off UAT harian berdasarkan readiness certificate.';

    public function handle(): int
    {
        if (!Schema::hasTable('uat_signoffs')) {
            $this->error('Tabel uat_signoffs belum ada. Jalankan migrate.');
            return self::FAILURE;
        }

        $date = trim((string) $this->option('date'));
        $checkDate = $date !== '' ? \Illuminate\Support\Carbon::parse($date)->toDateString() : now()->toDateString();
        $windowDays = max(1, (int) $this->option('window-days'));
        $institutionId = (int) config('notobuku.opac.public_institution_id', 1);
        $operator = trim((string) $this->option('operator'));
        if ($operator === '') {
            $operator = trim((string) config('notobuku.uat.auto_signoff.operator', 'SYSTEM AUTO'));
        }
        $extraNote = trim((string) $this->option('note'));
        $strictReady = (bool) $this->option('strict-ready');
        $strictReady = $strictReady || (bool) config('notobuku.uat.auto_signoff.strict_ready', true);

        // Pastikan checklist harian selalu ada.
        Artisan::call('notobuku:uat-generate', [
            '--date' => $checkDate,
        ]);

        $readinessArgs = [
            '--date' => $checkDate,
            '--institution' => $institutionId,
            '--window-days' => $windowDays,
        ];
        if ($strictReady) {
            $readinessArgs['--strict-ready'] = true;
        }

        $readinessExit = Artisan::call('notobuku:readiness-certificate', $readinessArgs);
        $status = $readinessExit === self::SUCCESS ? 'pass' : 'fail';
        $defaultNote = $status === 'pass'
            ? 'Auto sign-off: readiness READY/READY_WITH_NOTES.'
            : 'Auto sign-off: readiness NOT_READY atau strict-ready gagal.';
        $note = trim($defaultNote . ' ' . $extraNote);

        $checklistFile = trim((string) config('notobuku.uat.dir', 'uat/checklists')) . '/uat-' . $checkDate . '.md';

        DB::table('uat_signoffs')->updateOrInsert(
            ['institution_id' => $institutionId, 'check_date' => $checkDate],
            [
                'status' => $status,
                'operator_name' => $operator !== '' ? $operator : 'SYSTEM AUTO',
                'signed_at' => now(),
                'notes' => $note,
                'checklist_file' => $checklistFile,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        $this->line(trim((string) Artisan::output()));
        $this->info("Auto sign-off tersimpan: {$checkDate} status={$status}");

        return $status === 'pass' ? self::SUCCESS : self::FAILURE;
    }
}

