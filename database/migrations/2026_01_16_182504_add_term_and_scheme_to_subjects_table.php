<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subjects', function (Blueprint $table) {

            // Kolom "term" = bentuk asli tajuk (yang ditampilkan)
            if (!Schema::hasColumn('subjects', 'term')) {
                $table->string('term')->nullable()->after('name');
                $table->index('term', 'subjects_term_index');
            }

            // Kolom "scheme" = sumber tajuk (local / LCSH / MeSH, dll)
            if (!Schema::hasColumn('subjects', 'scheme')) {
                $table->string('scheme', 32)->default('local')->after('term');
                $table->index('scheme', 'subjects_scheme_index');
            }
        });

        // Backfill: kalau sudah ada data lama berbasis `name`,
        // isi term = name, scheme = 'local' bila kosong.
        $this->backfill();
    }

    public function down(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            if (Schema::hasColumn('subjects', 'scheme')) {
                $table->dropIndex('subjects_scheme_index');
                $table->dropColumn('scheme');
            }
            if (Schema::hasColumn('subjects', 'term')) {
                $table->dropIndex('subjects_term_index');
                $table->dropColumn('term');
            }
        });
    }

    private function backfill(): void
    {
        DB::table('subjects')
            ->select('id', 'name', 'term', 'scheme')
            ->orderBy('id')
            ->chunkById(500, function ($rows) {
                foreach ($rows as $r) {
                    $updates = [];

                    $term = is_null($r->term) ? '' : trim((string)$r->term);
                    if ($term === '') {
                        $updates['term'] = trim((string)$r->name);
                    }

                    $scheme = is_null($r->scheme) ? '' : trim((string)$r->scheme);
                    if ($scheme === '') {
                        $updates['scheme'] = 'local';
                    }

                    if (!empty($updates)) {
                        DB::table('subjects')->where('id', $r->id)->update($updates);
                    }
                }
            });
    }
};
