<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Biblio;
use App\Models\Item;
use App\Models\Tag;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CatalogBulkService
{
    public function bulkUpdate(array $data, int $institutionId, int $userId): array
    {
        $ids = $this->normalizeIds($data['ids'] ?? []);
        if (empty($ids)) {
            return ['ok' => false, 'error' => 'Tidak ada koleksi yang dipilih.'];
        }

        $updates = $this->prepareBiblioUpdates($data);
        [$hasItemUpdate, $itemUpdate] = $this->prepareItemUpdate($data);
        $tagsText = trim((string) ($data['tags_text'] ?? ''));
        $hasTagUpdate = $tagsText !== '';

        if (empty($updates) && !$hasItemUpdate && !$hasTagUpdate) {
            return ['ok' => false, 'error' => 'Pilih minimal satu field untuk diperbarui.'];
        }

        $batchKey = (string) Str::uuid();
        $before = [
            'biblio' => [],
            'items' => [],
            'tags' => [],
        ];

        $needsBiblioSnapshot = !empty($updates) || $hasTagUpdate;
        if ($needsBiblioSnapshot) {
            $biblioFields = ['id'];
            foreach (array_keys($updates) as $field) {
                if ($field !== 'updated_at') {
                    $biblioFields[] = $field;
                }
            }
            $biblioQuery = Biblio::query()
                ->where('institution_id', $institutionId)
                ->whereIn('id', $ids);
            if ($hasTagUpdate) {
                $biblioQuery->with('tags:id');
            }
            $biblioRows = $biblioQuery->get(array_unique($biblioFields));
            $before['biblio'] = $biblioRows->map(function ($row) use ($biblioFields) {
                $snap = ['id' => (int) $row->id];
                foreach ($biblioFields as $field) {
                    if ($field === 'id') {
                        continue;
                    }
                    $snap[$field] = $row->{$field};
                }
                return $snap;
            })->values()->all();

            if ($hasTagUpdate) {
                $before['tags'] = $biblioRows->mapWithKeys(function ($row) {
                    return [(int) $row->id => $row->tags->pluck('id')->values()->all()];
                })->all();
            }
        }

        if ($hasItemUpdate) {
            $itemRows = Item::query()
                ->where('institution_id', $institutionId)
                ->whereIn('biblio_id', $ids)
                ->get(['id', 'biblio_id', 'status', 'branch_id', 'shelf_id', 'location_note']);
            $before['items'] = $itemRows->map(function ($row) {
                return [
                    'id' => (int) $row->id,
                    'biblio_id' => (int) $row->biblio_id,
                    'status' => $row->status,
                    'branch_id' => $row->branch_id,
                    'shelf_id' => $row->shelf_id,
                    'location_note' => $row->location_note,
                ];
            })->values()->all();
        }

        $updates['updated_at'] = now();
        $count = 0;
        if (!empty($updates)) {
            $count = Biblio::query()
                ->where('institution_id', $institutionId)
                ->whereIn('id', $ids)
                ->update($updates);
        }

        if ($hasItemUpdate) {
            $itemUpdate['updated_at'] = now();
            Item::query()
                ->where('institution_id', $institutionId)
                ->whereIn('biblio_id', $ids)
                ->update($itemUpdate);
        }

        if ($hasTagUpdate) {
            $tagNames = array_values(array_unique(array_filter(array_map('trim', preg_split('/[;,]+/', $tagsText)))));
            if (!empty($tagNames)) {
                $tags = [];
                foreach ($tagNames as $name) {
                    $norm = strtolower(preg_replace('/\s+/', ' ', $name));
                    $tag = Tag::firstOrCreate(
                        ['normalized_name' => $norm],
                        ['name' => $name, 'normalized_name' => $norm]
                    );
                    $tags[$tag->id] = ['sort_order' => 0];
                }
                Biblio::query()
                    ->where('institution_id', $institutionId)
                    ->whereIn('id', $ids)
                    ->get()
                    ->each(function ($biblio) use ($tags) {
                        $biblio->tags()->sync($tags);
                    });
            }
        }

        try {
            AuditLog::create([
                'user_id' => $userId,
                'action' => 'bulk_update',
                'format' => 'biblio',
                'status' => 'success',
                'meta' => [
                    'count' => (int) count($ids),
                    'ids' => $ids,
                    'updates' => $updates,
                    'item_updates' => $itemUpdate,
                    'tags' => $tagsText,
                    'batch_key' => $batchKey,
                    'institution_id' => $institutionId,
                    'before' => $before,
                ],
            ]);
        } catch (\Throwable) {
            // ignore
        }

        return ['ok' => true, 'success' => 'Batch update berhasil untuk ' . (int) max($count, 0) . ' koleksi.'];
    }

    public function bulkPreview(array $data, int $institutionId): array
    {
        $ids = $this->normalizeIds($data['ids'] ?? []);
        if (empty($ids)) {
            return ['ok' => false, 'status' => 422, 'message' => 'Tidak ada koleksi yang dipilih.'];
        }

        $updates = $this->prepareBiblioUpdates($data);
        [$hasItemUpdate, $itemUpdate] = $this->prepareItemUpdate($data);
        $tagsText = trim((string) ($data['tags_text'] ?? ''));
        $hasTagUpdate = $tagsText !== '';
        if (empty($updates) && !$hasItemUpdate && !$hasTagUpdate) {
            return ['ok' => false, 'status' => 422, 'message' => 'Pilih minimal satu field untuk diperbarui.'];
        }

        $fields = [];
        if (array_key_exists('material_type', $updates)) {
            $fields[] = 'Jenis Konten';
        }
        if (array_key_exists('media_type', $updates)) {
            $fields[] = 'Media';
        }
        if (array_key_exists('language', $updates)) {
            $fields[] = 'Bahasa';
        }
        if (array_key_exists('publisher', $updates)) {
            $fields[] = 'Penerbit';
        }
        if (array_key_exists('ddc', $updates)) {
            $fields[] = 'DDC';
        }
        if ($hasTagUpdate) {
            $fields[] = 'Tag';
        }
        if ($hasItemUpdate) {
            if (array_key_exists('status', $itemUpdate)) {
                $fields[] = 'Status Eksemplar';
            }
            if (array_key_exists('branch_id', $itemUpdate)) {
                $fields[] = 'Cabang';
            }
            if (array_key_exists('shelf_id', $itemUpdate)) {
                $fields[] = 'Rak';
            }
            if (array_key_exists('location_note', $itemUpdate)) {
                $fields[] = 'Catatan lokasi';
            }
        }

        $query = Biblio::query()
            ->where('institution_id', $institutionId)
            ->whereIn('id', $ids);
        $count = (int) $query->count();
        $items = $query
            ->with('authors:id,name')
            ->orderBy('title')
            ->limit(10)
            ->get()
            ->map(function ($biblio) {
                return [
                    'id' => (int) $biblio->id,
                    'title' => (string) ($biblio->display_title ?? $biblio->title ?? ''),
                    'authors' => $biblio->authors?->pluck('name')->filter()->take(2)->implode(', ') ?? '',
                ];
            })
            ->values()
            ->all();

        return [
            'ok' => true,
            'body' => [
                'count' => $count,
                'fields' => $fields,
                'items' => $items,
            ],
        ];
    }

    public function bulkUndo(int $institutionId, int $userId): array
    {
        $last = AuditLog::query()
            ->where('user_id', $userId)
            ->where('action', 'bulk_update')
            ->orderByDesc('id')
            ->first();

        if (!$last) {
            return ['ok' => false, 'error' => 'Tidak ada batch update untuk dibatalkan.'];
        }

        $meta = is_array($last->meta) ? $last->meta : [];
        if (!empty($meta['institution_id']) && (int) $meta['institution_id'] !== $institutionId) {
            return ['ok' => false, 'error' => 'Batch terakhir bukan untuk institusi ini.'];
        }
        if (!empty($meta['undone_at'])) {
            return ['ok' => false, 'error' => 'Batch terakhir sudah dibatalkan.'];
        }

        $before = $meta['before'] ?? [];
        if (empty($before)) {
            return ['ok' => false, 'error' => 'Data undo tidak tersedia untuk batch ini.'];
        }

        DB::transaction(function () use ($institutionId, $before, $last, $meta, $userId) {
            $now = now();
            $biblioRows = $before['biblio'] ?? [];
            foreach ($biblioRows as $row) {
                if (empty($row['id'])) {
                    continue;
                }

                $update = $row;
                unset($update['id']);
                if (!empty($update)) {
                    $update['updated_at'] = $now;
                    Biblio::query()
                        ->where('institution_id', $institutionId)
                        ->where('id', (int) $row['id'])
                        ->update($update);
                }
            }

            $itemRows = $before['items'] ?? [];
            foreach ($itemRows as $row) {
                if (empty($row['id'])) {
                    continue;
                }
                $update = [
                    'status' => $row['status'] ?? null,
                    'branch_id' => $row['branch_id'] ?? null,
                    'shelf_id' => $row['shelf_id'] ?? null,
                    'location_note' => $row['location_note'] ?? null,
                    'updated_at' => $now,
                ];
                Item::query()
                    ->where('institution_id', $institutionId)
                    ->where('id', (int) $row['id'])
                    ->update($update);
            }

            $tagMap = $before['tags'] ?? [];
            foreach ($tagMap as $biblioId => $tagIds) {
                $biblio = Biblio::query()
                    ->where('institution_id', $institutionId)
                    ->find((int) $biblioId);
                if ($biblio) {
                    $biblio->tags()->sync($tagIds ?: []);
                }
            }

            $meta['undone_at'] = $now->toDateTimeString();
            $last->meta = $meta;
            $last->save();

            AuditLog::create([
                'user_id' => $userId,
                'action' => 'bulk_undo',
                'format' => 'biblio',
                'status' => 'success',
                'meta' => [
                    'batch_key' => $meta['batch_key'] ?? null,
                    'institution_id' => $institutionId,
                ],
            ]);
        });

        return ['ok' => true, 'success' => 'Undo batch terakhir berhasil.'];
    }

    private function normalizeIds($ids): array
    {
        if (is_string($ids)) {
            $ids = array_map('intval', array_filter(explode(',', $ids)));
        } elseif (is_array($ids)) {
            $ids = array_map('intval', $ids);
        } else {
            $ids = [];
        }

        return array_values(array_unique(array_filter($ids, fn ($id) => $id > 0)));
    }

    private function prepareBiblioUpdates(array $data): array
    {
        $updates = [];
        foreach (['material_type', 'media_type', 'language', 'publisher', 'ddc'] as $field) {
            $val = isset($data[$field]) ? trim((string) $data[$field]) : '';
            if ($val !== '') {
                $updates[$field] = $field === 'language' ? strtolower($val) : $val;
            }
        }

        return $updates;
    }

    private function prepareItemUpdate(array $data): array
    {
        $hasItemUpdate = false;
        $itemUpdate = [];

        $itemStatus = trim((string) ($data['item_status'] ?? ''));
        if ($itemStatus !== '') {
            $itemUpdate['status'] = $itemStatus;
            $hasItemUpdate = true;
        }

        $branchId = isset($data['branch_id']) ? (int) $data['branch_id'] : 0;
        if ($branchId > 0) {
            $itemUpdate['branch_id'] = $branchId;
            $hasItemUpdate = true;
        }

        $shelfId = isset($data['shelf_id']) ? (int) $data['shelf_id'] : 0;
        if ($shelfId > 0) {
            $itemUpdate['shelf_id'] = $shelfId;
            $hasItemUpdate = true;
        }

        $locNote = trim((string) ($data['location_note'] ?? ''));
        if ($locNote !== '') {
            $itemUpdate['location_note'] = $locNote;
            $hasItemUpdate = true;
        }

        return [$hasItemUpdate, $itemUpdate];
    }
}

