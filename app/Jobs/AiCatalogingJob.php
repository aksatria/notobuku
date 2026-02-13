<?php

namespace App\Jobs;

use App\Models\Biblio;
use App\Services\AiCatalogingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class AiCatalogingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param array<int> $biblioIds
     */
    public function __construct(public array $biblioIds, public int $institutionId)
    {
    }

    public function handle(AiCatalogingService $service): void
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $this->biblioIds))));
        if (empty($ids)) {
            return;
        }

        Biblio::query()
            ->where('institution_id', $this->institutionId)
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->chunk(200, function ($biblios) use ($service) {
                foreach ($biblios as $biblio) {
                    $service->runForBiblio($biblio);
                }
            });
    }
}
