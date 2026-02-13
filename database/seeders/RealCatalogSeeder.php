<?php

namespace Database\Seeders;

use App\Models\Author;
use App\Models\Biblio;
use App\Models\Subject;
use App\Models\Tag;
use App\Services\MetadataMappingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RealCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $path = storage_path('app/imports/catalog_20.json');
        if (!file_exists($path)) {
            $this->command?->warn("catalog_20.json tidak ditemukan di $path");
            return;
        }

        $raw = file_get_contents($path);
        $items = json_decode($raw, true);
        if (!is_array($items)) {
            $this->command?->warn('catalog_20.json tidak valid.');
            return;
        }

        $institutionId = 1;
        if (Schema::hasTable('institutions')) {
            $institutionId = (int) (DB::table('institutions')->value('id') ?? 1);
        }
        $mapping = app(MetadataMappingService::class);

        foreach ($items as $row) {
            $title = trim((string) ($row['title'] ?? ''));
            if ($title === '') {
                continue;
            }

            $subtitle = trim((string) ($row['subtitle'] ?? '')) ?: null;
            $isbn = trim((string) ($row['isbn'] ?? '')) ?: null;
            $publishYear = $this->extractYear($row['publish_year'] ?? null);

            $normalizedTitle = $this->normalize($title . ' ' . ($subtitle ?? ''));

            $query = Biblio::query()->where('institution_id', $institutionId);
            if ($isbn) {
                $query->where('isbn', $isbn);
            } else {
                $query->where('normalized_title', $normalizedTitle);
                if ($publishYear) {
                    $query->where('publish_year', $publishYear);
                }
            }

            $biblio = $query->first();
            $payload = [
                'institution_id' => $institutionId,
                'title' => $title,
                'subtitle' => $subtitle,
                'normalized_title' => $normalizedTitle,
                'publisher' => $this->nullableString($row['publisher'] ?? null),
                'place_of_publication' => $this->nullableString($row['place_of_publication'] ?? null),
                'publish_year' => $publishYear,
                'isbn' => $isbn,
                'language' => $this->nullableString($row['language'] ?? null) ?: 'en',
                'edition' => $this->nullableString($row['edition'] ?? null),
                'physical_desc' => $this->nullableString($row['physical_desc'] ?? null),
                'ddc' => $this->nullableString($row['ddc'] ?? null),
                'call_number' => $this->nullableString($row['call_number'] ?? null),
                'notes' => $this->nullableString($row['notes'] ?? null),
                'material_type' => 'buku',
                'media_type' => 'teks',
                'ai_status' => 'draft',
            ];

            if ($biblio) {
                $biblio->fill($payload);
                $biblio->save();
            } else {
                $biblio = Biblio::create($payload);
            }

            $this->syncAuthors($biblio, (string) ($row['authors'] ?? ''));
            $this->syncSubjects($biblio, (string) ($row['subjects'] ?? ''));
            $this->syncTags($biblio, (string) ($row['tags'] ?? ''));

            $mapping->syncMetadataForBiblio($biblio);
        }

        $this->command?->info('RealCatalogSeeder selesai.');
    }

    private function syncAuthors(Biblio $biblio, string $authorsText): void
    {
        $authors = collect(explode(',', $authorsText))
            ->map(fn($x) => trim((string) $x))
            ->filter()
            ->values();

        $sync = [];
        foreach ($authors as $i => $name) {
            $normalized = $this->normalize($name);
            $author = Author::query()->firstOrCreate(
                ['normalized_name' => $normalized],
                ['name' => $name, 'normalized_name' => $normalized]
            );
            $sync[$author->id] = ['role' => 'pengarang', 'sort_order' => $i + 1];
        }

        if (!empty($sync)) {
            $biblio->authors()->sync($sync);
        }
    }

    private function syncSubjects(Biblio $biblio, string $subjectsText): void
    {
        $subjects = collect(preg_split('/[,;\n]/', $subjectsText))
            ->map(fn($x) => trim((string) $x))
            ->filter()
            ->values();

        $sync = [];
        foreach ($subjects as $i => $term) {
            $normalized = $this->normalize($term);
            $subject = Subject::query()->firstOrCreate(
                ['normalized_term' => $normalized],
                ['name' => $term, 'term' => $term, 'normalized_term' => $normalized, 'scheme' => 'local']
            );
            $sync[$subject->id] = ['type' => 'topic', 'sort_order' => $i + 1];
        }

        if (!empty($sync)) {
            $biblio->subjects()->sync($sync);
        }
    }

    private function syncTags(Biblio $biblio, string $tagsText): void
    {
        $tags = collect(preg_split('/[,;\n]/', $tagsText))
            ->map(fn($x) => trim((string) $x))
            ->filter()
            ->values();

        $sync = [];
        foreach ($tags as $i => $name) {
            $normalized = $this->normalize($name);
            $tag = Tag::query()->firstOrCreate(
                ['normalized_name' => $normalized],
                ['name' => $name, 'normalized_name' => $normalized]
            );
            $sync[$tag->id] = ['sort_order' => $i + 1];
        }

        if (!empty($sync)) {
            $biblio->tags()->sync($sync);
        }
    }

    private function normalize(string $text): string
    {
        return (string) Str::of($text)
            ->lower()
            ->replaceMatches('/[^\p{L}\p{N}\s]/u', ' ')
            ->squish();
    }

    private function extractYear($value): ?int
    {
        if ($value === null) return null;

        $value = trim((string) $value);
        if ($value === '') return null;

        if (preg_match('/(19|20)\d{2}/', $value, $m)) {
            return (int) $m[0];
        }

        if (is_numeric($value)) {
            $intVal = (int) $value;
            return $intVal > 0 ? $intVal : null;
        }

        return null;
    }

    private function nullableString($value): ?string
    {
        if ($value === null) return null;
        $value = trim((string) $value);
        return $value !== '' ? $value : null;
    }
}
