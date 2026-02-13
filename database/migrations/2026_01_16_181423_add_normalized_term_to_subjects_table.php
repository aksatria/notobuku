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
            // Tambah kolom untuk pencarian/normalisasi tajuk
            if (!Schema::hasColumn('subjects', 'normalized_term')) {
                $table->string('normalized_term')->nullable()->after('name');
                $table->index('normalized_term', 'subjects_normalized_term_index');
            }
        });

        // Backfill data lama: isi normalized_term dari name
        // (Aman untuk data existing, tidak bikin unique constraint gagal)
        $this->backfillNormalizedTerm();
    }

    public function down(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            if (Schema::hasColumn('subjects', 'normalized_term')) {
                $table->dropIndex('subjects_normalized_term_index');
                $table->dropColumn('normalized_term');
            }
        });
    }

    private function backfillNormalizedTerm(): void
    {
        // Proses bertahap biar aman untuk data besar
        DB::table('subjects')
            ->select('id', 'name', 'normalized_term')
            ->orderBy('id')
            ->chunkById(500, function ($rows) {
                foreach ($rows as $r) {
                    $current = is_null($r->normalized_term) ? '' : trim((string) $r->normalized_term);
                    if ($current !== '') continue;

                    $name = trim((string) $r->name);
                    $norm = $this->normalize($name);

                    DB::table('subjects')
                        ->where('id', $r->id)
                        ->update(['normalized_term' => $norm]);
                }
            });
    }

    private function normalize(string $s): string
    {
        $s = mb_strtolower($s, 'UTF-8');
        $s = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $s) ?? $s; // buang simbol
        $s = preg_replace('/\s+/u', ' ', $s) ?? $s;            // rapikan spasi
        return trim($s);
    }
};
