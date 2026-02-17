<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ClassicIlsModulesSeeder extends Seeder
{
    public function run(): void
    {
        if (!$this->requiredTablesExist()) {
            $this->command?->warn('ClassicIlsModulesSeeder skipped: tabel modul klasik belum tersedia.');
            return;
        }

        $institutionId = $this->resolveInstitutionId();
        if ($institutionId <= 0) {
            $this->command?->warn('ClassicIlsModulesSeeder skipped: institution tidak ditemukan.');
            return;
        }

        $userId = $this->resolveOperatorUserId($institutionId);
        if ($userId <= 0) {
            $this->command?->warn('ClassicIlsModulesSeeder skipped: user operator tidak ditemukan.');
            return;
        }

        $branchId = (int) DB::table('branches')
            ->where('institution_id', $institutionId)
            ->orderBy('id')
            ->value('id');

        $shelfId = (int) DB::table('shelves')
            ->where('institution_id', $institutionId)
            ->orderBy('id')
            ->value('id');

        DB::transaction(function () use ($institutionId, $userId, $branchId, $shelfId) {
            $itemIds = $this->ensureSeedItems($institutionId, $branchId, $shelfId);
            $stockTakeId = $this->seedStockTake($institutionId, $userId, $branchId, $shelfId, $itemIds);
            $sourceIds = $this->seedCopyCatalogSources($institutionId);
            $this->seedCopyCatalogImports($institutionId, $userId, $sourceIds);

            $this->command?->info('ClassicIlsModulesSeeder OK: stock_take_id=' . $stockTakeId);
        });
    }

    private function requiredTablesExist(): bool
    {
        foreach ([
            'institutions',
            'users',
            'biblio',
            'items',
            'stock_takes',
            'stock_take_lines',
            'copy_catalog_sources',
            'copy_catalog_imports',
        ] as $table) {
            if (!Schema::hasTable($table)) {
                return false;
            }
        }

        return true;
    }

    private function resolveInstitutionId(): int
    {
        $id = (int) DB::table('institutions')->where('code', 'NOTO-01')->value('id');
        if ($id > 0) {
            return $id;
        }

        return (int) DB::table('institutions')->orderBy('id')->value('id');
    }

    private function resolveOperatorUserId(int $institutionId): int
    {
        $id = (int) DB::table('users')
            ->where('institution_id', $institutionId)
            ->whereIn('role', ['super_admin', 'admin', 'staff'])
            ->orderBy('id')
            ->value('id');

        if ($id > 0) {
            return $id;
        }

        return (int) DB::table('users')->where('institution_id', $institutionId)->orderBy('id')->value('id');
    }

    private function ensureSeedItems(int $institutionId, int $branchId, int $shelfId): array
    {
        $biblioId = (int) DB::table('biblio')
            ->where('institution_id', $institutionId)
            ->orderBy('id')
            ->value('id');

        if ($biblioId <= 0) {
            $biblioId = (int) DB::table('biblio')->insertGetId([
                'institution_id' => $institutionId,
                'title' => 'Dummy Buku Opname',
                'isbn' => '9786020000999',
                'publisher' => 'Notobuku Press',
                'publish_year' => 2024,
                'language' => 'id',
                'notes' => 'Data dummy untuk modul stock take.',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $rows = [
            ['barcode' => 'STOCK-DEMO-0001', 'accession_number' => 'ACC-STOCK-0001', 'status' => 'available'],
            ['barcode' => 'STOCK-DEMO-0002', 'accession_number' => 'ACC-STOCK-0002', 'status' => 'available'],
            ['barcode' => 'STOCK-DEMO-0003', 'accession_number' => 'ACC-STOCK-0003', 'status' => 'borrowed'],
            ['barcode' => 'STOCK-DEMO-0004', 'accession_number' => 'ACC-STOCK-0004', 'status' => 'available'],
        ];

        $itemIds = [];
        foreach ($rows as $row) {
            DB::table('items')->updateOrInsert(
                ['barcode' => $row['barcode']],
                [
                    'institution_id' => $institutionId,
                    'branch_id' => $branchId > 0 ? $branchId : null,
                    'shelf_id' => $shelfId > 0 ? $shelfId : null,
                    'biblio_id' => $biblioId,
                    'accession_number' => $row['accession_number'],
                    'status' => $row['status'],
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
            $itemId = (int) DB::table('items')->where('barcode', $row['barcode'])->value('id');
            if ($itemId > 0) {
                $itemIds[] = $itemId;
            }
        }

        return $itemIds;
    }

    private function seedStockTake(int $institutionId, int $userId, int $branchId, int $shelfId, array $itemIds): int
    {
        $stockTake = DB::table('stock_takes')
            ->where('institution_id', $institutionId)
            ->where('name', 'DEMO OPNAME - KLASIK ILS')
            ->first();

        if (!$stockTake) {
            $stockTakeId = (int) DB::table('stock_takes')->insertGetId([
                'institution_id' => $institutionId,
                'user_id' => $userId,
                'branch_id' => $branchId > 0 ? $branchId : null,
                'shelf_id' => $shelfId > 0 ? $shelfId : null,
                'name' => 'DEMO OPNAME - KLASIK ILS',
                'scope_status' => 'all',
                'status' => 'completed',
                'started_at' => now()->subDays(2),
                'completed_at' => now()->subDay(),
                'notes' => 'Seed demo stock take klasik.',
                'created_at' => now()->subDays(2),
                'updated_at' => now()->subDay(),
            ]);
        } else {
            $stockTakeId = (int) $stockTake->id;
        }

        DB::table('stock_take_lines')->where('stock_take_id', $stockTakeId)->delete();

        $expectedIds = array_slice($itemIds, 0, 3);
        $foundIds = array_slice($expectedIds, 0, 2);
        $now = now();
        $inserted = 0;

        foreach ($expectedIds as $itemId) {
            $item = DB::table('items')->where('id', $itemId)->first();
            if (!$item) {
                continue;
            }
            $isFound = in_array($itemId, $foundIds, true);
            DB::table('stock_take_lines')->insert([
                'stock_take_id' => $stockTakeId,
                'item_id' => $itemId,
                'barcode' => (string) $item->barcode,
                'expected' => 1,
                'found' => $isFound ? 1 : 0,
                'scan_status' => $isFound ? 'found' : 'missing',
                'status_snapshot' => (string) ($item->status ?? ''),
                'condition_snapshot' => (string) ($item->condition ?? ''),
                'title_snapshot' => (string) DB::table('biblio')->where('id', $item->biblio_id)->value('title'),
                'notes' => null,
                'scanned_at' => $isFound ? $now->copy()->subHours(8) : null,
                'created_at' => $now->copy()->subDay(),
                'updated_at' => $now->copy()->subDay(),
            ]);
            $inserted++;
        }

        DB::table('stock_take_lines')->insert([
            'stock_take_id' => $stockTakeId,
            'item_id' => null,
            'barcode' => 'UNEXPECTED-DEMO-0099',
            'expected' => 0,
            'found' => 1,
            'scan_status' => 'unexpected',
            'status_snapshot' => null,
            'condition_snapshot' => null,
            'title_snapshot' => 'Item luar scope opname',
            'notes' => 'Dummy unexpected barcode',
            'scanned_at' => $now->copy()->subHours(6),
            'created_at' => $now->copy()->subDay(),
            'updated_at' => $now->copy()->subDay(),
        ]);

        $expected = (int) DB::table('stock_take_lines')->where('stock_take_id', $stockTakeId)->where('expected', 1)->count();
        $found = (int) DB::table('stock_take_lines')->where('stock_take_id', $stockTakeId)->where('expected', 1)->where('found', 1)->count();
        $missing = (int) DB::table('stock_take_lines')->where('stock_take_id', $stockTakeId)->where('scan_status', 'missing')->count();
        $unexpected = (int) DB::table('stock_take_lines')->where('stock_take_id', $stockTakeId)->whereIn('scan_status', ['unexpected', 'out_of_scope'])->count();
        $scanned = (int) DB::table('stock_take_lines')->where('stock_take_id', $stockTakeId)->whereNotNull('scanned_at')->count();

        DB::table('stock_takes')
            ->where('id', $stockTakeId)
            ->update([
                'expected_items_count' => $expected,
                'found_items_count' => $found,
                'missing_items_count' => $missing,
                'unexpected_items_count' => $unexpected,
                'scanned_items_count' => $scanned,
                'summary_json' => json_encode([
                    'seeded_lines' => $inserted + 1,
                    'expected_items_count' => $expected,
                    'found_items_count' => $found,
                    'missing_items_count' => $missing,
                    'unexpected_items_count' => $unexpected,
                    'scanned_items_count' => $scanned,
                ], JSON_UNESCAPED_UNICODE),
                'updated_at' => now()->subDay(),
            ]);

        return $stockTakeId;
    }

    private function seedCopyCatalogSources(int $institutionId): array
    {
        $sources = [
            [
                'name' => 'Perpusnas SRU Demo',
                'protocol' => 'sru',
                'endpoint' => 'https://onesearch.id/sru',
                'priority' => 1,
                'settings_json' => null,
            ],
            [
                'name' => 'Z39.50 Gateway Demo',
                'protocol' => 'z3950',
                'endpoint' => 'https://interop.notobuku.test/z3950-gateway',
                'priority' => 2,
                'settings_json' => json_encode(['gateway_url' => 'https://interop.notobuku.test/z3950-gateway']),
            ],
            [
                'name' => 'P2P Konsorsium Demo',
                'protocol' => 'p2p',
                'endpoint' => 'https://interop.notobuku.test/p2p/catalog',
                'priority' => 3,
                'settings_json' => null,
            ],
        ];

        $sourceIds = [];
        foreach ($sources as $row) {
            DB::table('copy_catalog_sources')->updateOrInsert(
                [
                    'institution_id' => $institutionId,
                    'name' => $row['name'],
                ],
                [
                    'protocol' => $row['protocol'],
                    'endpoint' => $row['endpoint'],
                    'is_active' => 1,
                    'priority' => $row['priority'],
                    'settings_json' => $row['settings_json'],
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
            $id = (int) DB::table('copy_catalog_sources')
                ->where('institution_id', $institutionId)
                ->where('name', $row['name'])
                ->value('id');
            if ($id > 0) {
                $sourceIds[] = $id;
            }
        }

        return $sourceIds;
    }

    private function seedCopyCatalogImports(int $institutionId, int $userId, array $sourceIds): void
    {
        if (empty($sourceIds)) {
            return;
        }

        $biblioRows = DB::table('biblio')
            ->where('institution_id', $institutionId)
            ->orderBy('id')
            ->limit(2)
            ->get(['id', 'title', 'isbn', 'publisher', 'publish_year']);

        if ($biblioRows->isEmpty()) {
            return;
        }

        $idx = 0;
        foreach ($biblioRows as $biblio) {
            $sourceId = $sourceIds[$idx % count($sourceIds)];
            $externalId = 'SEED-CC-' . (int) $biblio->id;
            DB::table('copy_catalog_imports')->updateOrInsert(
                [
                    'institution_id' => $institutionId,
                    'external_id' => $externalId,
                ],
                [
                    'user_id' => $userId,
                    'source_id' => $sourceId,
                    'biblio_id' => (int) $biblio->id,
                    'title' => (string) $biblio->title,
                    'status' => 'imported',
                    'error_message' => null,
                    'raw_json' => json_encode([
                        'title' => (string) $biblio->title,
                        'isbn' => (string) ($biblio->isbn ?? ''),
                        'publisher' => (string) ($biblio->publisher ?? ''),
                        'publish_year' => (string) ($biblio->publish_year ?? ''),
                        'seed' => true,
                    ], JSON_UNESCAPED_UNICODE),
                    'updated_at' => now(),
                    'created_at' => now()->subDay(),
                ]
            );
            $idx++;
        }
    }
}

