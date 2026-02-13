<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1) Kalau tabel belum ada, buat dari nol
        if (!Schema::hasTable('branches')) {
            Schema::create('branches', function (Blueprint $table) {
                $table->id();

                $table->unsignedBigInteger('institution_id')->index();

                $table->string('name', 150);

                // code opsional tapi ada
                $table->string('code', 50)->nullable();

                $table->string('address', 255)->nullable();
                $table->text('notes')->nullable();

                $table->boolean('is_active')->default(true)->index();

                $table->timestamps();

                // code unik per institusi (kalau diisi). Multiple NULL tetap aman di MySQL.
                $table->unique(['institution_id', 'code'], 'branches_institution_code_unique');
            });

            return;
        }

        // 2) Kalau tabel sudah ada, tambahkan kolom yang kurang tanpa mengganggu kolom existing
        Schema::table('branches', function (Blueprint $table) {

            // institution_id
            if (!Schema::hasColumn('branches', 'institution_id')) {
                $table->unsignedBigInteger('institution_id')->default(1)->index()->after('id');
            } else {
                // pastikan ada index (kalau belum)
                try { $table->index('institution_id'); } catch (\Throwable $e) {}
            }

            // name
            if (!Schema::hasColumn('branches', 'name')) {
                $table->string('name', 150)->after('institution_id');
            }

            // code (opsional)
            if (!Schema::hasColumn('branches', 'code')) {
                $table->string('code', 50)->nullable()->after('name');
            }

            // address
            if (!Schema::hasColumn('branches', 'address')) {
                $table->string('address', 255)->nullable()->after('code');
            }

            // notes
            if (!Schema::hasColumn('branches', 'notes')) {
                $table->text('notes')->nullable()->after('address');
            }

            // is_active
            if (!Schema::hasColumn('branches', 'is_active')) {
                $table->boolean('is_active')->default(true)->index()->after('notes');
            } else {
                try { $table->index('is_active'); } catch (\Throwable $e) {}
            }

            // timestamps
            if (!Schema::hasColumn('branches', 'created_at')) {
                $table->timestamp('created_at')->nullable();
            }
            if (!Schema::hasColumn('branches', 'updated_at')) {
                $table->timestamp('updated_at')->nullable();
            }
        });

        // 3) Tambahkan unique constraint untuk (institution_id, code) bila belum ada
        // Catatan: constraint name harus unik.
        try {
            Schema::table('branches', function (Blueprint $table) {
                $table->unique(['institution_id', 'code'], 'branches_institution_code_unique');
            });
        } catch (\Throwable $e) {
            // kemungkinan sudah ada -> biarkan
        }
    }

    public function down(): void
    {
        // Aman: kalau tabel dibuat oleh migration ini, maka drop.
        // Kalau tabel sudah ada dari awal, sebaiknya JANGAN drop seluruh tabel.
        // Jadi: kita hanya drop constraint + kolom yang ditambahkan (jika ada).

        if (!Schema::hasTable('branches')) return;

        // drop unique bila ada
        try {
            Schema::table('branches', function (Blueprint $table) {
                $table->dropUnique('branches_institution_code_unique');
            });
        } catch (\Throwable $e) {}

        // Jangan drop kolom name/institution_id karena bisa jadi sudah dipakai sebelum migration ini.
        // Fokus: drop kolom yang "mungkin" ditambahkan, tapi aman kalau tidak ada.
        Schema::table('branches', function (Blueprint $table) {
            if (Schema::hasColumn('branches', 'is_active')) {
                // jangan drop kalau kamu sudah pakai, tapi ini untuk rollback dev
                // $table->dropColumn('is_active');
            }
            // Kalau mau rollback total, kamu bisa aktifkan dropColumn di atas.
        });
    }
};
