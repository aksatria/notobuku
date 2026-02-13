<?php

namespace App\Services;

use App\Models\Biblio;
use Illuminate\Support\Str;

class AiCatalogingService
{
    public function runForBiblio(Biblio $biblio, bool $force = false): array
    {
        if (!$force && $biblio->ai_status === 'approved') {
            return [
                'status' => 'skipped',
                'reason' => 'already_completed',
            ];
        }

        $biblio->loadMissing(['authors', 'subjects', 'tags']);

        $text = $this->buildText($biblio);

        $summary = $this->buildSummary($biblio, $text);
        $subjects = $this->suggestSubjects($biblio, $text);
        $tags = $this->suggestTags($biblio, $text);
        $ddc = $this->suggestDdc($biblio, $text);

        if ($force || empty($biblio->ai_summary)) {
            $biblio->ai_summary = $summary;
        }
        if ($force || empty($biblio->ai_suggested_subjects_json)) {
            $biblio->ai_suggested_subjects_json = $subjects;
        }
        if ($force || empty($biblio->ai_suggested_tags_json)) {
            $biblio->ai_suggested_tags_json = $tags;
        }
        if ($force || empty($biblio->ai_suggested_ddc)) {
            $biblio->ai_suggested_ddc = $ddc;
        }

        $biblio->ai_status = 'approved';
        $biblio->save();

        return [
            'status' => 'completed',
            'ai_summary' => $biblio->ai_summary,
            'ai_suggested_subjects_json' => $biblio->ai_suggested_subjects_json,
            'ai_suggested_tags_json' => $biblio->ai_suggested_tags_json,
            'ai_suggested_ddc' => $biblio->ai_suggested_ddc,
        ];
    }

    private function buildText(Biblio $biblio): string
    {
        $parts = [
            $biblio->title,
            $biblio->subtitle,
            $biblio->notes,
            $biblio->general_note,
            $biblio->bibliography_note,
        ];

        $subjects = $biblio->subjects?->pluck('term')->filter()->implode(' ') ?? '';
        $tags = $biblio->tags?->pluck('name')->filter()->implode(' ') ?? '';

        $parts[] = $subjects;
        $parts[] = $tags;

        return Str::of(implode(' ', array_filter($parts)))->lower()->squish()->toString();
    }

    private function buildSummary(Biblio $biblio, string $text): string
    {
        $notes = trim((string) ($biblio->notes ?? ''));
        if ($notes !== '') {
            $first = preg_split('/(?<=[.!?])\s+/', $notes)[0] ?? $notes;
            return Str::of($first)->limit(220)->toString();
        }

        $author = $biblio->authors?->first()?->name ?? null;
        $publisher = $biblio->publisher ?? null;
        $year = $biblio->publish_year ?? null;

        $chunks = array_filter([
            $biblio->title,
            $author ? "oleh {$author}" : null,
            $publisher ? "diterbitkan {$publisher}" : null,
            $year ? "({$year})" : null,
        ]);

        return trim(implode(' ', $chunks));
    }

    private function suggestSubjects(Biblio $biblio, string $text): array
    {
        $current = $biblio->subjects?->pluck('term')->filter()->values()->all() ?? [];

        $map = [
            'machine learning' => 'Machine learning',
            'artificial intelligence' => 'Artificial intelligence',
            'computer vision' => 'Computer vision',
            'database' => 'Database systems',
            'information retrieval' => 'Information retrieval',
            'software engineering' => 'Software engineering',
            'computer networks' => 'Computer networks',
            'cryptography' => 'Cryptography',
            'security' => 'Computer security',
            'programming' => 'Programming',
            'computer graphics' => 'Computer graphics',
            'robot' => 'Robotics',
            'science fiction' => 'Science fiction',
        ];

        $extra = [];
        foreach ($map as $needle => $label) {
            if (str_contains($text, $needle)) {
                $extra[] = $label;
            }
        }

        return array_values(array_unique(array_merge($current, $extra)));
    }

    private function suggestTags(Biblio $biblio, string $text): array
    {
        $tags = [];
        $map = [
            'ai' => ['artificial intelligence', 'ai'],
            'ml' => ['machine learning', 'neural'],
            'db' => ['database', 'sql'],
            'security' => ['cryptography', 'security'],
            'network' => ['network', 'networks'],
            'programming' => ['programming', 'coding', 'code'],
            'fiction' => ['science fiction', 'fiction'],
        ];

        foreach ($map as $tag => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($text, $needle)) {
                    $tags[] = $tag;
                    break;
                }
            }
        }

        return array_values(array_unique($tags));
    }

    private function suggestDdc(Biblio $biblio, string $text): ?string
    {
        $rules = [
            '/machine learning|deep learning|neural/' => '006.31',
            '/artificial intelligence|robot/' => '006.3',
            '/computer vision|image processing|pattern recognition/' => '006.37',
            '/computer graphics/' => '006.6',
            '/cryptography|computer security|security/' => '005.8',
            '/computer networks|networking/' => '004.6',
            '/operating system/' => '005.4',
            '/database|information retrieval/' => '005.74',
            '/software engineering/' => '005.1',
            '/programming|coding|program language|c\\+\\+|java|python/' => '005.13',
            '/computer science|computers/' => '004',
            '/science fiction|fiction/' => '813',
        ];

        foreach ($rules as $pattern => $ddc) {
            if (preg_match($pattern, $text)) {
                return $ddc;
            }
        }

        return $biblio->ddc ?? null;
    }
}
