<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('biblio', function (Blueprint $table) {

            // --- Katalogisasi / ISBD-style (Perpusnas-like) ---
            if (!Schema::hasColumn('biblio', 'place_of_publication')) {
                $table->string('place_of_publication')->nullable()->after('publisher'); // Kota terbit
            }

            if (!Schema::hasColumn('biblio', 'responsibility_statement')) {
                $table->string('responsibility_statement')->nullable()->after('subtitle'); // Pernyataan tanggung jawab
            }

            if (!Schema::hasColumn('biblio', 'series_title')) {
                $table->string('series_title')->nullable()->after('edition'); // Seri
            }

            if (!Schema::hasColumn('biblio', 'extent')) {
                $table->string('extent')->nullable()->after('physical_desc'); // Ekstensi/kolasi (mis: xii, 216 hlm)
            }

            if (!Schema::hasColumn('biblio', 'dimensions')) {
                $table->string('dimensions')->nullable()->after('extent'); // Dimensi (mis: 14 x 21 cm) atau tinggi
            }

            if (!Schema::hasColumn('biblio', 'illustrations')) {
                $table->string('illustrations')->nullable()->after('dimensions'); // Ilustrasi (mis: il., peta)
            }

            if (!Schema::hasColumn('biblio', 'bibliography_note')) {
                $table->string('bibliography_note')->nullable()->after('notes'); // Bibliografi/indeks
            }

            if (!Schema::hasColumn('biblio', 'general_note')) {
                $table->text('general_note')->nullable()->after('bibliography_note'); // Catatan umum tambahan
            }

            // --- Kontrol koleksi (opsional tapi umum di sistem perpus) ---
            if (!Schema::hasColumn('biblio', 'material_type')) {
                $table->string('material_type', 32)->default('buku')->after('language'); // buku/skripsi/majalah/dll
                $table->index('material_type', 'biblio_material_type_index');
            }

            if (!Schema::hasColumn('biblio', 'media_type')) {
                $table->string('media_type', 32)->default('teks')->after('material_type'); // teks/audio/video/dll
                $table->index('media_type', 'biblio_media_type_index');
            }

            if (!Schema::hasColumn('biblio', 'audience')) {
                $table->string('audience', 32)->nullable()->after('media_type'); // anak/remaja/dewasa/umum
                $table->index('audience', 'biblio_audience_index');
            }

            if (!Schema::hasColumn('biblio', 'is_reference')) {
                $table->boolean('is_reference')->default(false)->after('audience'); // koleksi referensi?
                $table->index('is_reference', 'biblio_is_reference_index');
            }

            // --- Identitas standar (jika dibutuhkan nanti) ---
            if (!Schema::hasColumn('biblio', 'issn')) {
                $table->string('issn', 32)->nullable()->after('isbn'); // untuk serial
                $table->index('issn', 'biblio_issn_index');
            }

            // --- Index tambahan yang berguna untuk performa pencarian ---
            if (!Schema::hasColumn('biblio', 'normalized_title')) {
                $table->string('normalized_title')->nullable()->after('title');
                $table->index('normalized_title', 'biblio_normalized_title_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('biblio', function (Blueprint $table) {
            $drops = [
                'place_of_publication',
                'responsibility_statement',
                'series_title',
                'extent',
                'dimensions',
                'illustrations',
                'bibliography_note',
                'general_note',
                'material_type',
                'media_type',
                'audience',
                'is_reference',
                'issn',
                'normalized_title',
            ];

            foreach ($drops as $col) {
                if (Schema::hasColumn('biblio', $col)) {
                    // drop index kalau ada yang kita buat
                    $idx = match ($col) {
                        'material_type' => 'biblio_material_type_index',
                        'media_type' => 'biblio_media_type_index',
                        'audience' => 'biblio_audience_index',
                        'is_reference' => 'biblio_is_reference_index',
                        'issn' => 'biblio_issn_index',
                        'normalized_title' => 'biblio_normalized_title_index',
                        default => null,
                    };
                    if ($idx) {
                        $table->dropIndex($idx);
                    }
                    $table->dropColumn($col);
                }
            }
        });
    }
};
