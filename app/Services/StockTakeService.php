<?php

namespace App\Services;

use App\Models\Item;
use App\Models\StockTake;
use App\Models\StockTakeLine;
use Illuminate\Support\Facades\DB;

class StockTakeService
{
    public function start(StockTake $stockTake): StockTake
    {
        if ($stockTake->status !== 'draft') {
            return $stockTake;
        }

        DB::transaction(function () use ($stockTake) {
            $items = Item::query()
                ->where('institution_id', $stockTake->institution_id)
                ->when($stockTake->branch_id, fn ($q) => $q->where('branch_id', $stockTake->branch_id))
                ->when($stockTake->shelf_id, fn ($q) => $q->where('shelf_id', $stockTake->shelf_id))
                ->when($stockTake->scope_status !== 'all', fn ($q) => $q->where('status', $stockTake->scope_status))
                ->with('biblio:id,title')
                ->get(['id', 'barcode', 'status', 'condition', 'biblio_id']);

            $now = now();
            $rows = [];
            foreach ($items as $item) {
                $rows[] = [
                    'stock_take_id' => $stockTake->id,
                    'item_id' => $item->id,
                    'barcode' => $item->barcode,
                    'expected' => true,
                    'found' => false,
                    'scan_status' => 'pending',
                    'status_snapshot' => (string) ($item->status ?? ''),
                    'condition_snapshot' => (string) ($item->condition ?? ''),
                    'title_snapshot' => (string) ($item->biblio->title ?? ''),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if (!empty($rows)) {
                StockTakeLine::query()->insert($rows);
            }

            $stockTake->forceFill([
                'status' => 'in_progress',
                'started_at' => $now,
                'expected_items_count' => count($rows),
                'found_items_count' => 0,
                'missing_items_count' => 0,
                'unexpected_items_count' => 0,
                'scanned_items_count' => 0,
            ])->save();
        });

        return $stockTake->fresh();
    }

    public function scan(StockTake $stockTake, string $barcode, ?string $notes = null): array
    {
        $barcode = trim($barcode);
        if ($barcode === '') {
            return $this->summary($stockTake);
        }

        DB::transaction(function () use ($stockTake, $barcode, $notes) {
            $item = Item::query()
                ->where('institution_id', $stockTake->institution_id)
                ->where('barcode', $barcode)
                ->with('biblio:id,title')
                ->first();

            if (!$item) {
                StockTakeLine::query()->create([
                    'stock_take_id' => $stockTake->id,
                    'barcode' => $barcode,
                    'expected' => false,
                    'found' => true,
                    'scan_status' => 'unexpected',
                    'notes' => $notes,
                    'scanned_at' => now(),
                ]);
                return;
            }

            $inScope = true;
            if ($stockTake->branch_id && (int) $item->branch_id !== (int) $stockTake->branch_id) {
                $inScope = false;
            }
            if ($stockTake->shelf_id && (int) $item->shelf_id !== (int) $stockTake->shelf_id) {
                $inScope = false;
            }
            if ($stockTake->scope_status !== 'all' && (string) $item->status !== (string) $stockTake->scope_status) {
                $inScope = false;
            }

            if ($inScope) {
                $line = StockTakeLine::query()
                    ->where('stock_take_id', $stockTake->id)
                    ->where('item_id', $item->id)
                    ->first();

                if ($line) {
                    $line->forceFill([
                        'found' => true,
                        'scan_status' => 'found',
                        'notes' => $notes ?: $line->notes,
                        'scanned_at' => now(),
                        'status_snapshot' => (string) ($item->status ?? ''),
                        'condition_snapshot' => (string) ($item->condition ?? ''),
                    ])->save();
                } else {
                    StockTakeLine::query()->create([
                        'stock_take_id' => $stockTake->id,
                        'item_id' => $item->id,
                        'barcode' => $item->barcode,
                        'expected' => true,
                        'found' => true,
                        'scan_status' => 'found',
                        'status_snapshot' => (string) ($item->status ?? ''),
                        'condition_snapshot' => (string) ($item->condition ?? ''),
                        'title_snapshot' => (string) ($item->biblio->title ?? ''),
                        'notes' => $notes,
                        'scanned_at' => now(),
                    ]);
                }
            } else {
                StockTakeLine::query()->create([
                    'stock_take_id' => $stockTake->id,
                    'item_id' => $item->id,
                    'barcode' => $item->barcode,
                    'expected' => false,
                    'found' => true,
                    'scan_status' => 'out_of_scope',
                    'status_snapshot' => (string) ($item->status ?? ''),
                    'condition_snapshot' => (string) ($item->condition ?? ''),
                    'title_snapshot' => (string) ($item->biblio->title ?? ''),
                    'notes' => $notes,
                    'scanned_at' => now(),
                ]);
            }
        });

        return $this->summary($stockTake->fresh());
    }

    public function complete(StockTake $stockTake): StockTake
    {
        if (!in_array($stockTake->status, ['in_progress', 'draft'], true)) {
            return $stockTake;
        }

        DB::transaction(function () use ($stockTake) {
            StockTakeLine::query()
                ->where('stock_take_id', $stockTake->id)
                ->where('expected', true)
                ->where('found', false)
                ->update([
                    'scan_status' => 'missing',
                    'updated_at' => now(),
                ]);

            $summary = $this->summary($stockTake->fresh());
            $stockTake->forceFill([
                'status' => 'completed',
                'completed_at' => now(),
                'expected_items_count' => $summary['expected_items_count'],
                'found_items_count' => $summary['found_items_count'],
                'missing_items_count' => $summary['missing_items_count'],
                'unexpected_items_count' => $summary['unexpected_items_count'],
                'scanned_items_count' => $summary['scanned_items_count'],
                'summary_json' => $summary,
            ])->save();
        });

        return $stockTake->fresh();
    }

    public function summary(StockTake $stockTake): array
    {
        $base = StockTakeLine::query()->where('stock_take_id', $stockTake->id);

        $expected = (clone $base)->where('expected', true)->count();
        $found = (clone $base)->where('expected', true)->where('found', true)->count();
        $missing = (clone $base)->where('scan_status', 'missing')->count();
        $unexpected = (clone $base)->whereIn('scan_status', ['unexpected', 'out_of_scope'])->count();
        $scanned = (clone $base)->whereNotNull('scanned_at')->count();

        $summary = [
            'expected_items_count' => $expected,
            'found_items_count' => $found,
            'missing_items_count' => $missing,
            'unexpected_items_count' => $unexpected,
            'scanned_items_count' => $scanned,
        ];

        $stockTake->forceFill($summary)->save();

        return $summary;
    }
}

