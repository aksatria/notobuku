<?php

namespace App\Console\Commands;

use App\Services\ReservationService;
use Illuminate\Console\Command;

class ExpireReservations extends Command
{
    protected $signature = 'reservations:expire {--institution= : Jalankan untuk 1 institusi saja} {--limit=200 : Batas proses per run}';
    protected $description = 'Expire HOLD reservasi yang sudah lewat expires_at, release item, dan promote antrean berikutnya.';

    public function handle(ReservationService $svc): int
    {
        $institution = $this->option('institution');
        $institutionId = $institution !== null && $institution !== '' ? (int)$institution : null;

        $limit = (int)$this->option('limit');
        if ($limit <= 0) $limit = 200;

        $count = $svc->expireDueHolds($institutionId, $limit);

        $msg = 'Expired: ' . $count;
        if ($institutionId) $msg .= ' (institution_id=' . $institutionId . ')';
        $this->info($msg);

        return self::SUCCESS;
    }
}
