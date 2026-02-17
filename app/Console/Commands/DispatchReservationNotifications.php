<?php

namespace App\Console\Commands;

use App\Services\ReservationNotificationService;
use Illuminate\Console\Command;

class DispatchReservationNotifications extends Command
{
    protected $signature = 'reservations:dispatch-notifications {--limit=200}';

    protected $description = 'Dispatch queued/failed reservation notifications with retry and dead-letter flow.';

    public function handle(ReservationNotificationService $service): int
    {
        $limit = (int) $this->option('limit');
        if ($limit <= 0) {
            $limit = 200;
        }

        $stats = $service->dispatchPending($limit);
        $this->info('Reservation notifications dispatched: sent=' . (int) ($stats['sent'] ?? 0)
            . ', failed=' . (int) ($stats['failed'] ?? 0)
            . ', dead_letter=' . (int) ($stats['dead_letter'] ?? 0));

        return self::SUCCESS;
    }
}
