<?php

namespace Database\Seeders;

use App\Models\Author;
use App\Models\Biblio;
use App\Models\Item;
use App\Models\Subject;
use App\Models\Tag;
use App\Services\MetadataMappingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SeedBiblioIntroAlgorithmsSeeder extends Seeder
{
    public function run(): void
    {
        $institutionId = (int) (DB::table('institutions')->value('id') ?? 1);
        $branchId = DB::table('branches')->where('institution_id', $institutionId)->value('id');
        $metadata = app(MetadataMappingService::class);

        $isbn = '9780262033848';
        $title = 'Introduction to Algorithms';
        $subtitle = 'Third Edition';
        $authors = [
            'Thomas H. Cormen',
            'Charles E. Leiserson',
            'Ronald L. Rivest',
            'Clifford Stein',
        ];

        $coverPath = $this->downloadCoverByIsbn($isbn, 'covers/intro-algorithms');

        $biblio = Biblio::query()->updateOrCreate(
            ['institution_id' => $institutionId, 'isbn' => $isbn],
            [
                'title' => $title,
                'subtitle' => $subtitle,
                'normalized_title' => $this->normalizeTitle($title, $subtitle),
                'responsibility_statement' => 'oleh ' . implode(', ', $authors),
                'publisher' => 'MIT Press',
                'place_of_publication' => 'Cambridge, MA, USA',
                'publish_year' => 2009,
                'isbn' => $isbn,
                'language' => 'eng',
                'edition' => '3rd edition',
                'physical_desc' => '1312 hlm',
                'extent' => '1312 hlm',
                'material_type' => 'buku',
                'media_type' => 'teks',
                'ddc' => '005.1',
                'call_number' => '005 COR',
                'cover_path' => $coverPath,
                'notes' => $this->buildNotesHtml(),
                'ai_status' => 'draft',
            ]
        );

        foreach ($authors as $i => $name) {
            $authorModel = Author::query()->firstOrCreate(
                ['normalized_name' => $this->normalizeLoose($name)],
                ['name' => $name, 'normalized_name' => $this->normalizeLoose($name)]
            );
            $biblio->authors()->syncWithoutDetaching([
                $authorModel->id => ['role' => 'aut', 'sort_order' => $i + 1],
            ]);
        }

        $subjects = [
            'Algorithms',
            'Data structures (Computer science)',
            'Computer programming',
            'Computer science',
        ];
        foreach ($subjects as $i => $term) {
            $subjectModel = Subject::query()->firstOrCreate(
                ['normalized_term' => $this->normalizeLoose($term)],
                ['name' => $term, 'term' => $term, 'normalized_term' => $this->normalizeLoose($term), 'scheme' => 'local']
            );
            $biblio->subjects()->syncWithoutDetaching([
                $subjectModel->id => ['type' => 'topic', 'sort_order' => $i + 1],
            ]);
        }

        $tags = ['algorithms', 'data-structures', 'referensi'];
        foreach ($tags as $i => $name) {
            $tagModel = Tag::query()->firstOrCreate(
                ['normalized_name' => $this->normalizeLoose($name)],
                ['name' => $name, 'normalized_name' => $this->normalizeLoose($name)]
            );
            $biblio->tags()->syncWithoutDetaching([
                $tagModel->id => ['sort_order' => $i + 1],
            ]);
        }

        if (!$biblio->items()->exists()) {
            Item::create([
                'institution_id' => $institutionId,
                'branch_id' => $branchId ?: null,
                'biblio_id' => $biblio->id,
                'barcode' => $this->generateUniqueCode('NB'),
                'accession_number' => $this->generateUniqueCode('ACC'),
                'status' => 'available',
            ]);
        }

        $dcI18n = [
            'id' => [
                'title' => $title,
                'creator' => $authors,
                'subject' => ['Algoritma', 'Struktur data', 'Ilmu komputer'],
                'description' => 'Referensi algoritma dan struktur data yang lengkap untuk mahasiswa dan praktisi.',
                'publisher' => 'MIT Press',
                'date' => '2009',
                'language' => 'id',
                'type' => 'buku',
                'format' => 'teks',
            ],
            'en' => [
                'title' => $title,
                'creator' => $authors,
                'subject' => $subjects,
                'description' => 'Comprehensive reference on algorithms and data structures with rigorous analysis.',
                'publisher' => 'MIT Press',
                'date' => '2009',
                'language' => 'en',
                'type' => 'book',
                'format' => 'text',
            ],
        ];

        $identifiers = [
            ['scheme' => 'call_number', 'value' => '005 COR', 'uri' => null],
            ['scheme' => 'isbn', 'value' => $isbn, 'uri' => null],
            ['scheme' => 'openlibrary', 'value' => '/works/OL4781294W', 'uri' => 'https://openlibrary.org/works/OL4781294W'],
        ];

        $metadata->syncMetadataForBiblio($biblio, $dcI18n, $identifiers);
    }

    private function buildNotesHtml(): string
    {
        return '<p><strong>Ringkasan:</strong> Referensi algoritma modern dengan pembahasan struktur data dan analisis kompleksitas.</p>'
            . '<ul><li>Mencakup desain algoritma, graf, greedy, dynamic programming.</li>'
            . '<li>Cocok untuk kuliah inti ilmu komputer.</li></ul>';
    }

    private function normalizeLoose(string $text): string
    {
        return (string) Str::of($text)
            ->lower()
            ->replaceMatches('/[^a-z0-9\\s]/', ' ')
            ->squish();
    }

    private function normalizeTitle(string $title, ?string $subtitle): string
    {
        $base = trim($title);
        $sub = trim((string) $subtitle);
        if ($sub !== '') $base .= ' ' . $sub;
        return $this->normalizeLoose($base);
    }

    private function generateUniqueCode(string $prefix): string
    {
        $date = now()->format('Ymd');
        for ($tries = 0; $tries < 20; $tries++) {
            $code = $prefix . '-' . $date . '-' . Str::upper(Str::random(6));
            $exists = Item::query()->where('barcode', $code)->orWhere('accession_number', $code)->exists();
            if (!$exists) return $code;
        }
        return $prefix . '-' . $date . '-' . Str::upper(Str::random(10));
    }

    private function downloadCoverByIsbn(string $isbn, string $baseName): ?string
    {
        $url = 'https://covers.openlibrary.org/b/isbn/' . $isbn . '-L.jpg';
        try {
            $resp = Http::retry(2, 400)->get($url);
            if (!$resp->ok()) return null;
            $file = $baseName . '-' . Str::random(6) . '.jpg';
            Storage::disk('public')->put($file, $resp->body());
            return $file;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
