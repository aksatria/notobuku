<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SignoffUatChecklist extends Command
{
    protected $signature = 'notobuku:uat-signoff {--date=} {--status=pass} {--operator=} {--note=}';

    protected $description = 'Simpan sign-off operator untuk checklist UAT.';

    public function handle(): int
    {
        if (!Schema::hasTable('uat_signoffs')) {
            $this->error('Tabel uat_signoffs belum ada. Jalankan migrate.');
            return self::FAILURE;
        }

        $date = trim((string) $this->option('date'));
        $checkDate = $date !== '' ? \Illuminate\Support\Carbon::parse($date)->toDateString() : now()->toDateString();
        $status = trim((string) $this->option('status'));
        if (!in_array($status, ['pass', 'fail', 'pending'], true)) {
            $this->error('Status harus: pass|fail|pending');
            return self::FAILURE;
        }
        $operator = trim((string) $this->option('operator'));
        $note = trim((string) $this->option('note'));
        $institutionId = (int) config('notobuku.opac.public_institution_id', 1);

        DB::table('uat_signoffs')->updateOrInsert(
            ['institution_id' => $institutionId, 'check_date' => $checkDate],
            [
                'status' => $status,
                'operator_name' => $operator !== '' ? $operator : null,
                'signed_at' => $status !== 'pending' ? now() : null,
                'notes' => $note !== '' ? $note : null,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        $this->info('Sign-off tersimpan: ' . $checkDate . ' status=' . $status);
        return self::SUCCESS;
    }
}

