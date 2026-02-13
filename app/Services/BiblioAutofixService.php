<?php

namespace App\Services;

use App\Models\Biblio;
use App\Models\DdcClass;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class BiblioAutofixService
{
    public function autofix(Biblio $biblio): bool
    {
        $dirty = false;

        $ddcBase = $this->extractDdcBase((string) ($biblio->ddc ?? ''));
        $validDdc = $ddcBase !== '' && DdcClass::query()->where('code', $ddcBase)->exists();
        if (!$validDdc) {
            $text = $this->buildTextForDdc($biblio);
            $inferred = $this->inferDdcFromText($text);
            if ($inferred) {
                $biblio->ddc = $inferred;
                $dirty = true;
            }
        }

        if (empty($biblio->call_number)) {
            $ddcForCall = $this->extractDdcBase((string) ($biblio->ddc ?? ''));
            if ($ddcForCall !== '') {
                $author = $this->extractAuthorName($biblio);
                $biblio->call_number = $this->makeCallNumber($ddcForCall, $author ?? $biblio->title);
                $dirty = true;
            }
        }

        if (empty($biblio->place_of_publication) || empty($biblio->physical_desc)) {
            $ol = $this->fetchOpenLibraryByIsbn((string) ($biblio->isbn ?? ''));
            if (!empty($ol['publish_place']) && empty($biblio->place_of_publication)) {
                $biblio->place_of_publication = $ol['publish_place'];
                $dirty = true;
            }
            if (!empty($ol['pages']) && empty($biblio->physical_desc)) {
                $biblio->physical_desc = $ol['pages'] . ' hlm';
                $dirty = true;
            }
        }

        if ($dirty) {
            $biblio->save();
        }

        return $dirty;
    }

    private function buildTextForDdc(Biblio $biblio): string
    {
        $parts = [
            $biblio->title,
            $biblio->subtitle,
            $biblio->notes,
            $biblio->publisher,
        ];
        return Str::of(implode(' ', array_filter($parts)))->lower()->toString();
    }

    private function inferDdcFromText(string $text): ?string
    {
        $map = [
            '/\b(computer|computing|programming|software|database|algorithm|data|informatics|information system|it)\b/i' => '004',
            '/\b(mathematics|math|algebra|calculus|statistics|probability)\b/i' => '510',
            '/\b(physics|mechanics|quantum|thermodynamics)\b/i' => '530',
            '/\b(chemistry|chemical)\b/i' => '540',
            '/\b(biology|genetics|ecology|botany|zoology)\b/i' => '570',
            '/\b(medicine|medical|nursing|health|pharmacy)\b/i' => '610',
            '/\b(engineering|civil|mechanical|electrical)\b/i' => '620',
            '/\b(agriculture|farming|horticulture)\b/i' => '630',
            '/\b(business|management|marketing|finance|accounting|economics)\b/i' => '650',
            '/\b(art|music|architecture|painting|design|photography)\b/i' => '700',
            '/\b(literature|novel|poetry|drama|fiction|literary)\b/i' => '800',
            '/\b(history|geography|biography|war|historical)\b/i' => '900',
            '/\b(religion|theology|islam|christianity|bible|quran)\b/i' => '200',
            '/\b(philosophy|ethics|psychology|logic)\b/i' => '100',
            '/\b(sociology|political|education|law|social)\b/i' => '300',
            '/\b(language|linguistics|grammar|translation)\b/i' => '400',
        ];

        foreach ($map as $pattern => $ddc) {
            if (preg_match($pattern, $text)) {
                return $ddc;
            }
        }

        return null;
    }

    private function extractAuthorName(Biblio $biblio): ?string
    {
        if (!empty($biblio->responsibility_statement)) {
            $raw = trim((string) $biblio->responsibility_statement);
            $raw = preg_replace('/^oleh\\s+/i', '', $raw);
            $raw = trim((string) $raw);
            if ($raw !== '') {
                return $raw;
            }
        }

        $biblio->loadMissing('authors');
        $author = $biblio->authors?->first();
        return $author?->name;
    }

    private function makeCallNumber(string $ddc, string $authorOrTitle): string
    {
        $parts = preg_split('/\\s+/', trim($authorOrTitle));
        $last = $parts ? end($parts) : $authorOrTitle;
        $cutter = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', (string) $last), 0, 3));
        if ($cutter === '') {
            $cutter = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $authorOrTitle), 0, 3));
        }
        if ($cutter === '') {
            $cutter = 'XXX';
        }
        return trim($ddc . ' ' . $cutter);
    }

    private function extractDdcBase(string $ddc): string
    {
        $ddc = trim($ddc);
        if ($ddc === '') return '';
        if (preg_match('/^(\\d{3})/', $ddc, $m)) {
            return $m[1];
        }
        return '';
    }

    private function fetchOpenLibraryByIsbn(string $isbn): array
    {
        $isbn = preg_replace('/[^0-9Xx]/', '', $isbn);
        if ($isbn === '') return [];

        $resp = Http::retry(2, 400)->get('https://openlibrary.org/api/books', [
            'bibkeys' => 'ISBN:' . $isbn,
            'format' => 'json',
            'jscmd' => 'data',
        ]);

        if (!$resp->ok()) return [];
        $data = $resp->json();
        $row = $data['ISBN:' . $isbn] ?? null;
        if (!$row) return [];

        $place = $row['publish_places'][0]['name'] ?? null;
        $pages = $row['number_of_pages'] ?? null;

        return [
            'publish_place' => $place ? (string) $place : null,
            'pages' => is_numeric($pages) ? (int) $pages : null,
        ];
    }
}
