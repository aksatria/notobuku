<?php

namespace Database\Seeders;

use App\Models\AuthorityAuthor;
use App\Models\AuthorityPublisher;
use App\Models\AuthoritySubject;
use App\Models\Author;
use App\Models\Biblio;
use App\Models\DdcClass;
use App\Models\Subject;
use App\Models\Tag;
use App\Services\MetadataMappingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class KatalogMetadataSeeder extends Seeder
{
    public function run(): void
    {
        DB::beginTransaction();

        try {
            $institutionId = DB::table('institutions')->value('id') ?? 1;

            $ddcTop = [
                ['code' => '000', 'name' => 'Computer science, information & general works'],
                ['code' => '100', 'name' => 'Philosophy & psychology'],
                ['code' => '200', 'name' => 'Religion'],
                ['code' => '300', 'name' => 'Social sciences'],
                ['code' => '400', 'name' => 'Language'],
                ['code' => '500', 'name' => 'Science'],
                ['code' => '600', 'name' => 'Technology'],
                ['code' => '700', 'name' => 'Arts & recreation'],
                ['code' => '800', 'name' => 'Literature'],
                ['code' => '900', 'name' => 'History & geography'],
            ];

            foreach ($ddcTop as $row) {
                DdcClass::query()->updateOrCreate(
                    ['code' => $row['code']],
                    [
                        'name' => $row['name'],
                        'normalized_name' => $this->normalize($row['name']),
                        'parent_id' => null,
                        'level' => 1,
                    ]
                );
            }

            $parent000 = DdcClass::query()->where('code', '000')->value('id');
            $parent800 = DdcClass::query()->where('code', '800')->value('id');

            $ddcChildren = [
                ['code' => '020', 'name' => 'Library & information sciences', 'parent_id' => $parent000, 'level' => 2],
                ['code' => '028', 'name' => 'Reading & use of other information media', 'parent_id' => $parent000, 'level' => 2],
                ['code' => '808', 'name' => 'Rhetoric & collections of literature', 'parent_id' => $parent800, 'level' => 2],
                ['code' => '813', 'name' => 'American fiction in English', 'parent_id' => $parent800, 'level' => 2],
            ];

            foreach ($ddcChildren as $row) {
                DdcClass::query()->updateOrCreate(
                    ['code' => $row['code']],
                    [
                        'name' => $row['name'],
                        'normalized_name' => $this->normalize($row['name']),
                        'parent_id' => $row['parent_id'],
                        'level' => $row['level'],
                    ]
                );
            }

            AuthorityAuthor::query()->updateOrCreate(
                ['normalized_name' => $this->normalize('Pramoedya Ananta Toer')],
                ['preferred_name' => 'Pramoedya Ananta Toer']
            );
            AuthorityAuthor::query()->updateOrCreate(
                ['normalized_name' => $this->normalize('Dewey, Melvil')],
                ['preferred_name' => 'Dewey, Melvil']
            );

            AuthoritySubject::query()->updateOrCreate(
                ['scheme' => 'local', 'normalized_term' => $this->normalize('Perpustakaan')],
                ['preferred_term' => 'Perpustakaan', 'scheme' => 'local']
            );
            AuthoritySubject::query()->updateOrCreate(
                ['scheme' => 'local', 'normalized_term' => $this->normalize('Fiksi')],
                ['preferred_term' => 'Fiksi', 'scheme' => 'local']
            );

            AuthorityPublisher::query()->updateOrCreate(
                ['normalized_name' => $this->normalize('Gramedia')],
                ['preferred_name' => 'Gramedia']
            );
            AuthorityPublisher::query()->updateOrCreate(
                ['normalized_name' => $this->normalize('Pustaka Ilmu')],
                ['preferred_name' => 'Pustaka Ilmu']
            );

            $b1 = Biblio::query()->updateOrCreate(
                ['institution_id' => $institutionId, 'isbn' => '9786020301234'],
                [
                    'title' => 'Dasar-dasar Ilmu Perpustakaan',
                    'subtitle' => 'Panduan praktis katalogisasi',
                    'normalized_title' => $this->normalize('Dasar-dasar Ilmu Perpustakaan Panduan praktis katalogisasi'),
                    'publisher' => 'Pustaka Ilmu',
                    'publish_year' => 2021,
                    'language' => 'id',
                    'ddc' => '020',
                    'call_number' => '020 PER',
                    'notes' => 'Pengantar katalogisasi dan metadata perpustakaan.',
                    'material_type' => 'buku',
                    'media_type' => 'teks',
                    'ai_status' => 'draft',
                ]
            );

            $author1 = Author::query()->firstOrCreate(
                ['normalized_name' => $this->normalize('Dewey, Melvil')],
                ['name' => 'Dewey, Melvil', 'normalized_name' => $this->normalize('Dewey, Melvil')]
            );
            $b1->authors()->sync([$author1->id => ['role' => 'pengarang', 'sort_order' => 1]]);

            $subject1 = Subject::query()->firstOrCreate(
                ['normalized_term' => $this->normalize('Perpustakaan')],
                ['name' => 'Perpustakaan', 'term' => 'Perpustakaan', 'normalized_term' => $this->normalize('Perpustakaan'), 'scheme' => 'local']
            );
            $b1->subjects()->sync([$subject1->id => ['type' => 'topic', 'sort_order' => 1]]);

            $b2 = Biblio::query()->updateOrCreate(
                ['institution_id' => $institutionId, 'isbn' => '9789799731234'],
                [
                    'title' => 'Bumi Manusia',
                    'subtitle' => null,
                    'normalized_title' => $this->normalize('Bumi Manusia'),
                    'publisher' => 'Gramedia',
                    'publish_year' => 2019,
                    'language' => 'id',
                    'ddc' => '813',
                    'call_number' => '813 PRA',
                    'notes' => 'Novel klasik Indonesia.',
                    'material_type' => 'buku',
                    'media_type' => 'teks',
                    'ai_status' => 'draft',
                ]
            );

            $author2 = Author::query()->firstOrCreate(
                ['normalized_name' => $this->normalize('Pramoedya Ananta Toer')],
                ['name' => 'Pramoedya Ananta Toer', 'normalized_name' => $this->normalize('Pramoedya Ananta Toer')]
            );
            $b2->authors()->sync([$author2->id => ['role' => 'pengarang', 'sort_order' => 1]]);

            $subject2 = Subject::query()->firstOrCreate(
                ['normalized_term' => $this->normalize('Fiksi')],
                ['name' => 'Fiksi', 'term' => 'Fiksi', 'normalized_term' => $this->normalize('Fiksi'), 'scheme' => 'local']
            );
            $b2->subjects()->sync([$subject2->id => ['type' => 'topic', 'sort_order' => 1]]);

            $b3 = Biblio::query()->updateOrCreate(
                ['institution_id' => $institutionId, 'isbn' => '9786020305678'],
                [
                    'title' => 'Metadata untuk Katalog Modern',
                    'subtitle' => 'Dublin Core & MARC21',
                    'normalized_title' => $this->normalize('Metadata untuk Katalog Modern Dublin Core & MARC21'),
                    'publisher' => 'Pustaka Ilmu',
                    'publish_year' => 2022,
                    'language' => 'id',
                    'ddc' => '028',
                    'call_number' => '028 MET',
                    'notes' => 'Membahas standar metadata modern.',
                    'material_type' => 'buku',
                    'media_type' => 'teks',
                    'ai_status' => 'draft',
                ]
            );

            $author3 = Author::query()->firstOrCreate(
                ['normalized_name' => $this->normalize('NotoBuku Team')],
                ['name' => 'NotoBuku Team', 'normalized_name' => $this->normalize('NotoBuku Team')]
            );
            $b3->authors()->sync([$author3->id => ['role' => 'pengarang', 'sort_order' => 1]]);

            $tag = Tag::query()->firstOrCreate(
                ['normalized_name' => $this->normalize('metadata')],
                ['name' => 'Metadata', 'normalized_name' => $this->normalize('metadata')]
            );
            $b3->tags()->sync([$tag->id => ['sort_order' => 1]]);

            $mapping = app(MetadataMappingService::class);
            $mapping->syncMetadataForBiblio($b1);
            $mapping->syncMetadataForBiblio($b2);
            $mapping->syncMetadataForBiblio($b3);

            DB::commit();
            $this->command->info('✅ KatalogMetadataSeeder OK');
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    private function normalize(string $text): string
    {
        return (string) Str::of($text)
            ->lower()
            ->replaceMatches('/[^\p{L}\p{N}\s]/u', ' ')
            ->squish();
    }
}
