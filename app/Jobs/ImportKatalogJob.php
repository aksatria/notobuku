<?php

namespace App\Jobs;

use App\Jobs\AiCatalogingJob;
use App\Services\ImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class ImportKatalogJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $path,
        public string $format,
        public int $institutionId,
        public ?int $userId
    ) {
    }

    public function handle(ImportService $importService): void
    {
        $fullPath = Storage::disk('local')->path($this->path);
        $report = $importService->importByFormatFromPath($this->format, $fullPath, $this->institutionId, $this->userId);

        $ids = $report['biblio_ids'] ?? [];
        $total = (int) ($report['created'] ?? 0) + (int) ($report['updated'] ?? 0);
        if (!empty($ids) && $importService->shouldQueueAi($total)) {
            AiCatalogingJob::dispatch($ids, $this->institutionId);
        }

        try {
            Storage::disk('local')->delete($this->path);
        } catch (\Throwable $e) {
            // ignore cleanup failures
        }
    }
}
