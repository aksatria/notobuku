<?php

namespace App\Services;

use App\Models\DdcClass;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class DdcService
{
    public function search(string $q, int $limit = 10): array
    {
        $q = trim($q);
        if ($q === '') {
            return [];
        }

        $key = 'ddc:search:' . md5($q . '|' . $limit);

        return Cache::remember($key, now()->addMinutes(5), function () use ($q, $limit) {
            $norm = $this->normalize($q);

            return DdcClass::query()
                ->where('code', 'like', "{$q}%")
                ->orWhere('name', 'like', "%{$q}%")
                ->orWhere('normalized_name', 'like', "%{$norm}%")
                ->orderBy('code')
                ->limit($limit)
                ->get(['id', 'code', 'name'])
                ->map(fn($row) => [
                    'id' => (int) $row->id,
                    'label' => trim((string) $row->code . ' - ' . (string) $row->name),
                ])
                ->all();
        });
    }

    private function normalize(string $text): string
    {
        return (string) Str::of($text)
            ->lower()
            ->replaceMatches('/[^\p{L}\p{N}\s]/u', ' ')
            ->squish();
    }
}
