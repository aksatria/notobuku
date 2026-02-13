<?php

namespace Tests\Unit;

use App\Models\Biblio;
use App\Models\BiblioIdentifier;
use App\Models\MarcSetting;
use App\Services\MarcControlFieldBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MarcControlFieldBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_build007_pads_to_min_length_from_profile(): void
    {
        $institutionId = DB::table('institutions')->insertGetId([
            'name' => 'Test Institution',
            'code' => 'TEST-007',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        MarcSetting::create([
            'key' => 'media_profiles',
            'value_json' => [
                [
                    'name' => 'short007',
                    'keywords' => ['short007'],
                    'type_006' => 'a',
                    'type_007' => 'ta',
                    'pattern_006' => 'a                 ',
                    'pattern_007' => 'ta',
                    'min_007' => 6,
                    'pattern_008_books' => '{entered}{status}{date1}{date2}{place}                {lang}  ',
                ],
            ],
        ]);

        $biblio = Biblio::create([
            'institution_id' => $institutionId,
            'title' => 'Short 007',
            'normalized_title' => 'short 007',
            'publisher' => 'Test Press',
            'place_of_publication' => 'Jakarta',
            'publish_year' => 2024,
            'language' => 'id',
            'material_type' => 'short007',
            'media_type' => 'teks',
            'ai_status' => 'draft',
        ]);

        $builder = new MarcControlFieldBuilder();
        $value = $builder->build007($biblio);

        $this->assertSame(6, strlen($value));
        $this->assertSame('ta', substr($value, 0, 2));
    }

    public function test_online_detection_strict_requires_uri_scheme(): void
    {
        $institutionId = DB::table('institutions')->insertGetId([
            'name' => 'Test Institution',
            'code' => 'TEST-STRICT',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        MarcSetting::create([
            'key' => 'online_detection_mode',
            'value_json' => 'strict',
        ]);

        $biblio = Biblio::create([
            'institution_id' => $institutionId,
            'title' => 'Strict Online',
            'normalized_title' => 'strict online',
            'publisher' => 'Test Press',
            'place_of_publication' => 'Jakarta',
            'publish_year' => 2024,
            'language' => 'id',
            'material_type' => 'buku',
            'media_type' => 'teks',
            'ai_status' => 'draft',
        ]);

        BiblioIdentifier::create([
            'biblio_id' => $biblio->id,
            'scheme' => 'doi',
            'value' => '10.1000/test',
            'normalized_value' => '10.1000/test',
            'uri' => 'https://example.org/no-scheme',
        ]);

        $builder = new MarcControlFieldBuilder();
        $field008 = $builder->build008($biblio);

        $this->assertSame(40, strlen($field008));
        $this->assertNotSame('o', $field008[23]);
    }

    public function test_online_detection_loose_accepts_uri_without_scheme(): void
    {
        $institutionId = DB::table('institutions')->insertGetId([
            'name' => 'Test Institution',
            'code' => 'TEST-LOOSE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        MarcSetting::create([
            'key' => 'online_detection_mode',
            'value_json' => 'loose',
        ]);

        $biblio = Biblio::create([
            'institution_id' => $institutionId,
            'title' => 'Loose Online',
            'normalized_title' => 'loose online',
            'publisher' => 'Test Press',
            'place_of_publication' => 'Jakarta',
            'publish_year' => 2024,
            'language' => 'id',
            'material_type' => 'buku',
            'media_type' => 'teks',
            'ai_status' => 'draft',
        ]);

        BiblioIdentifier::create([
            'biblio_id' => $biblio->id,
            'scheme' => 'doi',
            'value' => '10.1000/test',
            'normalized_value' => '10.1000/test',
            'uri' => 'https://example.org/loose-only',
        ]);

        $builder = new MarcControlFieldBuilder();
        $field008 = $builder->build008($biblio);

        $this->assertSame(40, strlen($field008));
        $this->assertSame('o', $field008[23]);
    }

    public function test_build008_separates_music_and_audio_patterns_by_type(): void
    {
        $institutionId = DB::table('institutions')->insertGetId([
            'name' => 'Test Institution Audio Music',
            'code' => 'TEST-AUDIO-MUSIC',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $patternAudio = '{entered}{status}{date1}{date2}{place}A' . str_repeat(' ', 16) . '{lang}  ';
        $patternMusic = '{entered}{status}{date1}{date2}{place}M' . str_repeat(' ', 16) . '{lang}  ';

        MarcSetting::create([
            'key' => 'media_profiles',
            'value_json' => [
                [
                    'name' => 'music',
                    'keywords' => ['music'],
                    'type_006' => 'j',
                    'type_007' => 'sd',
                    'pattern_006' => 'j                 ',
                    'pattern_007' => 'sd fmnngnn',
                    'min_007' => 7,
                    'pattern_008_music' => $patternMusic,
                ],
                [
                    'name' => 'audio',
                    'keywords' => ['audio'],
                    'type_006' => 'i',
                    'type_007' => 'sd',
                    'pattern_006' => 'i                 ',
                    'pattern_007' => 'sd fmnngnn',
                    'min_007' => 7,
                    'pattern_008_audio' => $patternAudio,
                ],
            ],
        ]);

        $audio = Biblio::create([
            'institution_id' => $institutionId,
            'title' => 'Audio Title',
            'normalized_title' => 'audio title',
            'publisher' => 'Test Press',
            'place_of_publication' => 'Jakarta',
            'publish_year' => 2024,
            'language' => 'id',
            'material_type' => 'audio',
            'media_type' => 'cd audio',
            'ai_status' => 'draft',
        ]);

        $music = Biblio::create([
            'institution_id' => $institutionId,
            'title' => 'Music Title',
            'normalized_title' => 'music title',
            'publisher' => 'Test Press',
            'place_of_publication' => 'Jakarta',
            'publish_year' => 2024,
            'language' => 'id',
            'material_type' => 'music',
            'media_type' => 'cd audio',
            'ai_status' => 'draft',
        ]);

        $builder = new MarcControlFieldBuilder();

        $audio008 = $builder->build008($audio);
        $music008 = $builder->build008($music);

        $this->assertSame('A', $audio008[18]);
        $this->assertSame('M', $music008[18]);
    }
}
