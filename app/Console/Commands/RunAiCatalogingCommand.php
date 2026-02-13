<?php

namespace App\Console\Commands;

use App\Models\Biblio;
use App\Services\AiCatalogingService;
use Illuminate\Console\Command;

class RunAiCatalogingCommand extends Command
{
    protected $signature = 'catalog:ai {--force : Force regenerate AI fields} {--limit= : Limit number of records} {--id= : Process specific biblio id}';

    protected $description = 'Generate AI cataloging fields for biblios (summary, suggested subjects/tags, suggested DDC).';

    public function handle(AiCatalogingService $service): int
    {
        $query = Biblio::query()->orderBy('id');

        if ($id = $this->option('id')) {
            $query->where('id', (int) $id);
        }

        if (!$this->option('force')) {
            $query->where(function ($q) {
                $q->whereNull('ai_status')->orWhere('ai_status', '!=', 'approved');
            });
        }

        if ($limit = $this->option('limit')) {
            $query->limit((int) $limit);
        }

        $count = 0;
        $query->chunk(100, function ($biblios) use ($service, &$count) {
            foreach ($biblios as $biblio) {
                $service->runForBiblio($biblio, (bool) $this->option('force'));
                $count++;
            }
        });

        $this->info("AI cataloging done for {$count} biblio(s).");
        return self::SUCCESS;
    }
}
