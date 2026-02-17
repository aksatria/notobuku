<?php

namespace App\Services;

use App\Models\Biblio;
use App\Models\Item;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CatalogDetailService
{
    public function __construct(private readonly BiblioInteractionService $biblioInteractionService)
    {
    }

    public function buildShowData(
        int $institutionId,
        int $biblioId,
        bool $canManage,
        bool $isAuthenticated,
        ?int $userId,
        ?int $branchId
    ): array {
        $biblio = Biblio::query()
            ->where('institution_id', $institutionId)
            ->with(['authors', 'subjects', 'tags', 'attachments'])
            ->withCount([
                'items',
                'availableItems as available_items_count',
            ])
            ->findOrFail($biblioId);

        $this->biblioInteractionService->recordClick(
            (int) $biblio->id,
            $institutionId,
            $userId,
            $branchId
        );

        $itemsQuery = Item::query()
            ->where('items.institution_id', $institutionId)
            ->where('items.biblio_id', $biblio->id)
            ->select('items.*');

        if (Schema::hasTable('branches')) {
            $itemsQuery->leftJoin('branches as br', function ($join) use ($institutionId) {
                $join->on('br.id', '=', 'items.branch_id')
                    ->where('br.institution_id', '=', $institutionId);
            })->addSelect([
                DB::raw('br.name as branch_name'),
            ]);
        }

        if (Schema::hasTable('shelves')) {
            $itemsQuery->leftJoin('shelves as sh', function ($join) use ($institutionId) {
                $join->on('sh.id', '=', 'items.shelf_id')
                    ->where('sh.institution_id', '=', $institutionId);
            })->addSelect([
                DB::raw('sh.name as rack_name'),
                DB::raw('sh.name as shelf_name'),
            ]);
        }

        $items = $itemsQuery
            ->orderByRaw("CASE items.status WHEN 'available' THEN 0 WHEN 'reserved' THEN 1 WHEN 'borrowed' THEN 2 WHEN 'maintenance' THEN 3 WHEN 'damaged' THEN 4 WHEN 'lost' THEN 5 ELSE 99 END")
            ->orderBy('items.barcode')
            ->paginate(20)
            ->withQueryString();

        $authorIds = $biblio->authors?->pluck('id')->filter()->values() ?? collect();
        $subjectIds = $biblio->subjects?->pluck('id')->filter()->values() ?? collect();

        $relatedBiblios = collect();
        if ($authorIds->isNotEmpty() || $subjectIds->isNotEmpty()) {
            $relatedQuery = Biblio::query()
                ->where('institution_id', $institutionId)
                ->where('id', '<>', $biblio->id)
                ->with(['authors:id,name', 'subjects:id,term,name'])
                ->withCount([
                    'items',
                    'availableItems as available_items_count',
                ])
                ->where(function ($query) use ($authorIds, $subjectIds) {
                    if ($authorIds->isNotEmpty()) {
                        $query->orWhereHas('authors', function ($authorQuery) use ($authorIds) {
                            $authorQuery->whereIn('authors.id', $authorIds);
                        });
                    }
                    if ($subjectIds->isNotEmpty()) {
                        $query->orWhereHas('subjects', function ($subjectQuery) use ($subjectIds) {
                            $subjectQuery->whereIn('subjects.id', $subjectIds);
                        });
                    }
                });

            $subjectIdList = $subjectIds->implode(',');
            $authorIdList = $authorIds->implode(',');
            if ($subjectIdList !== '' || $authorIdList !== '') {
                $scoreSql = 'CASE';
                if ($subjectIdList !== '') {
                    $scoreSql .= " WHEN EXISTS (SELECT 1 FROM biblio_subject bs WHERE bs.biblio_id = biblio.id AND bs.subject_id IN ($subjectIdList)) THEN 2";
                }
                if ($authorIdList !== '') {
                    $scoreSql .= " WHEN EXISTS (SELECT 1 FROM biblio_author ba WHERE ba.biblio_id = biblio.id AND ba.author_id IN ($authorIdList)) THEN 1";
                }
                $scoreSql .= ' ELSE 0 END';
                $relatedQuery->addSelect(DB::raw("$scoreSql as match_score"))->orderByDesc('match_score');
            }

            $relatedBiblios = $relatedQuery
                ->orderByDesc('available_items_count')
                ->orderByDesc('items_count')
                ->orderBy('title')
                ->limit(6)
                ->get();
        }

        $attachmentsQuery = $biblio->attachments()->orderByDesc('created_at');
        if (!$canManage) {
            if ($isAuthenticated) {
                $attachmentsQuery->whereIn('visibility', ['public', 'member']);
            } else {
                $attachmentsQuery->where('visibility', 'public');
            }
        }
        $attachments = $attachmentsQuery->get();

        return [
            'biblio' => $biblio,
            'items' => $items,
            'relatedBiblios' => $relatedBiblios,
            'attachments' => $attachments,
        ];
    }
}

