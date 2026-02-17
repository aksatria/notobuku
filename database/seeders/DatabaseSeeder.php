<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Database\Seeders\NotobukuSeeder;
use Database\Seeders\KatalogMetadataSeeder;
use Database\Seeders\LoanReturnFineSeeder;
use Illuminate\Support\Facades\Log;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (!app()->environment('local')) {
            Log::warning('db:seed executed in non-local environment.', [
                'env' => app()->environment(),
            ]);
        }

        $this->call([
            NotobukuSeeder::class,
            DdcShelvesSeeder::class,
            CirculationPolicyMatrixSeeder::class,
            ReservationPolicyMatrixSeeder::class,
            EmhaTitlesSeeder::class,
            KatalogMetadataSeeder::class,
            DdcSummarySeeder::class,
            KatalogCollectionsSeeder::class,
            SeedBiblioIntroAlgorithmsSeeder::class,
            SeedBiblioMathStructuresSeeder::class,
            SeedBiblioResistBookTagSeeder::class,
            FilsafatBukuProgresifSeeder::class,
            TanMalakaBukuProgresifSeeder::class,
            GramediaSocratesAllBranchesSeeder::class,
            GramediaDaleCarnegieAllBranchesSeeder::class,
            RealCatalogSeeder::class,
            LoanReturnFineSeeder::class,
            AcquisitionsSeeder::class,
            ClassicIlsModulesSeeder::class,
        ]);
    }
}
