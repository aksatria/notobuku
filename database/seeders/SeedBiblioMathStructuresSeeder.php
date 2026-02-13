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

class SeedBiblioMathStructuresSeeder extends Seeder
{
    public function run(): void
    {
        $institutionId = (int) (DB::table('institutions')->value('id') ?? 1);
        $branchId = DB::table('branches')->where('institution_id', $institutionId)->value('id');
        $metadata = app(MetadataMappingService::class);

        $isbn = '9781429215107';
        $title = 'Mathematical Structures for Computer Science';
        $subtitle = 'discrete mathematics and its applications';
        $author = 'Judith L. Gersting';

        $coverPath = $this->downloadCoverByIsbn($isbn, 'covers/math-structures-gersting');

        $biblio = Biblio::query()->updateOrCreate(
            ['institution_id' => $institutionId, 'isbn' => $isbn],
            [
                'title' => $title,
                'subtitle' => $subtitle,
                'normalized_title' => $this->normalizeTitle($title, $subtitle),
                'responsibility_statement' => 'oleh ' . $author,
                'publisher' => 'W.H. Freeman and Company, a Macmillan Higher Education Company',
                'place_of_publication' => 'New York, NY',
                'publish_year' => 2014,
                'isbn' => $isbn,
                'language' => 'eng',
                'edition' => '7th edition',
                'physical_desc' => '969 hlm',
                'extent' => '969 hlm',
                'material_type' => 'buku',
                'media_type' => 'teks',
                'ddc' => '004.0151',
                'call_number' => '004 GER',
                'cover_path' => $coverPath,
                'notes' => $this->buildNotesHtml(),
                'ai_status' => 'draft',
            ]
        );

        $authorModel = Author::query()->firstOrCreate(
            ['normalized_name' => $this->normalizeLoose($author)],
            ['name' => $author, 'normalized_name' => $this->normalizeLoose($author)]
        );
        $biblio->authors()->syncWithoutDetaching([
            $authorModel->id => ['role' => 'aut', 'sort_order' => 1],
        ]);

        $subjects = [
            'Textbooks',
            'Mathematical models',
            'Computer science',
            'Mathematics',
            'Mathematics textbooks',
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

        $tags = ['textbook', 'matematika', 'komputasi'];
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
                'creator' => [$author],
                'subject' => ['Buku ajar', 'Matematika diskrit', 'Ilmu komputer'],
                'description' => 'Pengantar matematika diskrit untuk mahasiswa ilmu komputer, dengan fokus pada konsep, model, dan penerapannya.',
                'publisher' => 'W.H. Freeman and Company',
                'date' => '2014',
                'language' => 'id',
                'type' => 'buku',
                'format' => 'teks',
            ],
            'en' => [
                'title' => $title,
                'creator' => [$author],
                'subject' => $subjects,
                'description' => 'A discrete mathematics textbook for computer science students, covering core concepts and applications.',
                'publisher' => 'W.H. Freeman and Company',
                'date' => '2014',
                'language' => 'en',
                'type' => 'book',
                'format' => 'text',
            ],
        ];

        $identifiers = [
            ['scheme' => 'call_number', 'value' => '004 GER', 'uri' => null],
            ['scheme' => 'isbn', 'value' => $isbn, 'uri' => null],
            ['scheme' => 'openlibrary', 'value' => '/works/OL1922611W', 'uri' => 'https://openlibrary.org/works/OL1922611W'],
        ];

        $metadata->syncMetadataForBiblio($biblio, $dcI18n, $identifiers);
    }

    private function buildNotesHtml(): string
    {
        return '<p><strong>Ringkasan:</strong> Buku ajar matematika diskrit untuk ilmu komputer dengan pendekatan terstruktur dan banyak latihan.</p>'
            . '<ul><li>Konsep inti: logika, himpunan, relasi, fungsi, graf.</li>'
            . '<li>Aplikasi ke pemodelan dan komputasi.</li></ul>';
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
