<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * CATATAN PENTING:
     * Tabel `shelves` SUDAH ADA dari migration lama:
     * 2026_01_15_013722_create_shelves_table (status: Ran)
     *
     * Migration ini kemungkinan terlanjur dibuat ulang di tanggal 2026_01_24
     * sehingga jadi duplikat dan gagal saat migrate.
     *
     * Solusi aman:
     * - Jika tabel sudah ada -> SKIP (agar migration ini bisa ditandai Ran tanpa merusak data)
     * - Jika tabel belum ada (edge case) -> buat tabel
     */
    public function up(): void
    {
        // ✅ kalau tabel sudah ada, jangan bikin ulang
        if (Schema::hasTable('shelves')) {
            return;
        }

        Schema::create('shelves', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('institution_id');
            $table->unsignedBigInteger('branch_id');

            $table->string('name', 150);
            $table->string('code', 50)->nullable();
            $table->string('location', 255)->nullable();
            $table->text('notes')->nullable();

            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index('institution_id');
            $table->index('branch_id');
            $table->index('is_active');
            $table->index('sort_order');

            // unik per institusi + cabang + nama rak (opsional sesuai kebutuhan)
            $table->unique(['institution_id', 'branch_id', 'name'], 'shelves_inst_branch_name_unique');

            // foreign key (kalau branches ada)
            $table->foreign('branch_id')
                ->references('id')->on('branches')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }

    /**
     * down() sengaja dibuat AMAN:
     * Karena tabel shelves sudah ada dari migration lama,
     * kita tidak boleh drop table yang sudah berisi data.
     */
    public function down(): void
    {
        // ❗ Jangan drop table shelves agar tidak merusak data lama.
        // Kalau benar-benar butuh rollback, lakukan manual dengan keputusan sadar.
        return;
    }
};
