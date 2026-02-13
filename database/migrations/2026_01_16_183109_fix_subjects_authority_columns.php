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

            // controller pakai normalized_term
            if (!Schema::hasColumn('subjects', 'normalized_term')) {
                $table->string('normalized_term')->nullable()->after('name');
                $table->index('normalized_term', 'subjects_normalized_term_index');
            }

            // controller pakai term
            if (!Schema::hasColumn('subjects', 'term')) {
                $table->string('term')->nullable()->after('normalized_term');
                $table->index('term', 'subjects_term_index');
            }

            // controller pakai scheme
            if (!Schema::hasColumn('subjects', 'scheme')) {
                $table->string('scheme', 32)->default('local')->after('term');
                $table->index('scheme', 'subjects_scheme_index');
            }
        });

        // Backfill aman
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
            if (Schema::hasColumn('subjects', 'normalized_term')) {
                $table->dropIndex('subjects_normalized_term_index');
                $table->dropColumn('normalized_term');
            }
        });
    }

    private function backfill(): void
    {
        DB::table('subjects')
            ->select('id','name','normalized_term','term','scheme')
            ->orderBy('id')
            ->chunkById(500, function ($rows) {
                foreach ($rows as $r) {
                    $name = trim((string) $r->name);

                    $updates = [];

                    $norm = is_null($r->normalized_term) ? '' : trim((string) $r->normalized_term);
                    if ($norm === '' && $name !== '') {
                        $updates['normalized_term'] = $this->normalize($name);
                    }

                    $term = is_null($r->term) ? '' : trim((string) $r->term);
                    if ($term === '' && $name !== '') {
                        $updates['term'] = $name; // bentuk tampil
                    }

                    $scheme = is_null($r->scheme) ? '' : trim((string) $r->scheme);
                    if ($scheme === '') {
                        $updates['scheme'] = 'local';
                    }

                    if (!empty($updates)) {
                        DB::table('subjects')->where('id', $r->id)->update($updates);
                    }
                }
            });
    }

    private function normalize(string $s): string
    {
        $s = mb_strtolower($s, 'UTF-8');
        $s = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $s) ?? $s;
        $s = preg_replace('/\s+/u', ' ', $s) ?? $s;
        return trim($s);
    }
};
