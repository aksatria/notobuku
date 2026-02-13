<?php

namespace App\Services;

use App\Models\AuthorityAuthor;
use App\Models\AuthorityPublisher;
use App\Models\AuthoritySubject;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class AuthorityService
{
    public function searchAuthors(string $q, int $limit = 10): array
    {
        return $this->searchCached('authors', $q, $limit, function () use ($q, $limit) {
            $norm = $this->normalize($q);

            return AuthorityAuthor::query()
                ->where('preferred_name', 'like', "%{$q}%")
                ->orWhere('normalized_name', 'like', "%{$norm}%")
                ->orderBy('preferred_name')
                ->limit($limit)
                ->get(['id', 'preferred_name'])
                ->map(fn($row) => ['id' => (int) $row->id, 'label' => (string) $row->preferred_name])
                ->all();
        });
    }

    public function searchSubjects(string $q, int $limit = 10): array
    {
        return $this->searchCached('subjects', $q, $limit, function () use ($q, $limit) {
            $norm = $this->normalize($q);

            return AuthoritySubject::query()
                ->where('preferred_term', 'like', "%{$q}%")
                ->orWhere('normalized_term', 'like', "%{$norm}%")
                ->orderBy('preferred_term')
                ->limit($limit)
                ->get(['id', 'preferred_term'])
                ->map(fn($row) => ['id' => (int) $row->id, 'label' => (string) $row->preferred_term])
                ->all();
        });
    }

    public function searchPublishers(string $q, int $limit = 10): array
    {
        return $this->searchCached('publishers', $q, $limit, function () use ($q, $limit) {
            $norm = $this->normalize($q);

            return AuthorityPublisher::query()
                ->where('preferred_name', 'like', "%{$q}%")
                ->orWhere('normalized_name', 'like', "%{$norm}%")
                ->orderBy('preferred_name')
                ->limit($limit)
                ->get(['id', 'preferred_name'])
                ->map(fn($row) => ['id' => (int) $row->id, 'label' => (string) $row->preferred_name])
                ->all();
        });
    }

    private function searchCached(string $type, string $q, int $limit, callable $resolver): array
    {
        $q = trim($q);
        if ($q === '') {
            return [];
        }

        $key = "authority:{$type}:" . md5($q . '|' . $limit);

        return Cache::remember($key, now()->addMinutes(5), $resolver);
    }

    private function normalize(string $text): string
    {
        return (string) Str::of($text)
            ->lower()
            ->replaceMatches('/[^\p{L}\p{N}\s]/u', ' ')
            ->squish();
    }
}
