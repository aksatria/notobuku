<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('reservations')) {
            Schema::create('reservations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('institution_id')->default(1)->index();

                $table->unsignedBigInteger('member_id')->index();
                $table->unsignedBigInteger('biblio_id')->index();

                // item_id = item yang sedang di-hold ketika READY
                $table->unsignedBigInteger('item_id')->nullable()->index();

                // antrean per biblio
                $table->unsignedInteger('queue_no')->default(1)->index();

                // workflow status
                $table->string('status', 20)->default('queued')->index();

                // reserved_at = waktu mulai HOLD (READY)
                $table->timestamp('reserved_at')->nullable()->index();
                // expires_at = batas ambil (READY)
                $table->timestamp('expires_at')->nullable()->index();
                // fulfilled_at = sudah dipinjam (FULFILLED)
                $table->timestamp('fulfilled_at')->nullable()->index();

                $table->unsignedBigInteger('handled_by')->nullable()->index();
                $table->text('notes')->nullable();

                $table->timestamps();

                $table->index(['institution_id', 'item_id', 'status']);
                $table->index(['institution_id', 'biblio_id', 'status', 'queue_no']);
            });

            return;
        }

        /**
         * 1) Pastikan kolom-kolom ada
         */
        Schema::table('reservations', function (Blueprint $table) {
            if (!Schema::hasColumn('reservations', 'institution_id')) {
                $table->unsignedBigInteger('institution_id')->default(1)->index();
            }
            if (!Schema::hasColumn('reservations', 'member_id')) {
                $table->unsignedBigInteger('member_id')->index();
            }
            if (!Schema::hasColumn('reservations', 'biblio_id')) {
                $table->unsignedBigInteger('biblio_id')->index();
            }
            if (!Schema::hasColumn('reservations', 'item_id')) {
                $table->unsignedBigInteger('item_id')->nullable()->index();
            }
            if (!Schema::hasColumn('reservations', 'queue_no')) {
                $table->unsignedInteger('queue_no')->default(1)->index();
            }
            if (!Schema::hasColumn('reservations', 'reserved_at')) {
                $table->timestamp('reserved_at')->nullable()->index();
            }
            if (!Schema::hasColumn('reservations', 'expires_at')) {
                $table->timestamp('expires_at')->nullable()->index();
            }
            if (!Schema::hasColumn('reservations', 'fulfilled_at')) {
                $table->timestamp('fulfilled_at')->nullable()->index();
            }
            if (!Schema::hasColumn('reservations', 'handled_by')) {
                $table->unsignedBigInteger('handled_by')->nullable()->index();
            }
            if (!Schema::hasColumn('reservations', 'notes')) {
                $table->text('notes')->nullable();
            }

            // Kalau belum ada kolom status, tambahkan.
            if (!Schema::hasColumn('reservations', 'status')) {
                $table->string('status', 20)->default('queued')->index();
            }
        });

        /**
         * 2) FIX UTAMA:
         * Paksa reservations.status menjadi VARCHAR(20)
         * agar tidak “data truncated” (ENUM/pendek).
         */
        $this->forceStatusToVarchar20();

        /**
         * 3) Tambah index (aman dibungkus try)
         */
        try {
            Schema::table('reservations', function (Blueprint $table) {
                $table->index(['institution_id', 'item_id', 'status'], 'reservations_inst_item_status_idx');
            });
        } catch (\Throwable $e) {
            // ignore
        }
        try {
            Schema::table('reservations', function (Blueprint $table) {
                $table->index(['institution_id', 'biblio_id', 'status', 'queue_no'], 'reservations_inst_biblio_status_queue_idx');
            });
        } catch (\Throwable $e) {
            // ignore
        }

        /**
         * 4) Mapping data lama:
         * - Jika sebelumnya pakai status = 'active' (atau yang lain), kita konversi:
         *   fulfilled_at not null      => fulfilled
         *   item_id not null & expires_at > now => ready
         *   item_id not null & expires_at <= now => expired
         *   selain itu => queued
         *
         * Catatan: migration ini fokus mapping data di reservations.
         * Release item reserved->available dilakukan oleh command/service expire.
         */
        $now = now()->toDateTimeString();

        // Fulfilled
        DB::table('reservations')
            ->where('status', 'active')
            ->whereNotNull('fulfilled_at')
            ->update(['status' => 'fulfilled', 'updated_at' => $now]);

        // Ready
        DB::table('reservations')
            ->where('status', 'active')
            ->whereNull('fulfilled_at')
            ->whereNotNull('item_id')
            ->whereNotNull('expires_at')
            ->where('expires_at', '>', $now)
            ->update(['status' => 'ready', 'updated_at' => $now]);

        // Expired
        DB::table('reservations')
            ->where('status', 'active')
            ->whereNull('fulfilled_at')
            ->whereNotNull('item_id')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $now)
            ->update(['status' => 'expired', 'updated_at' => $now]);

        // Sisanya queued
        DB::table('reservations')
            ->where('status', 'active')
            ->update(['status' => 'queued', 'updated_at' => $now]);

        /**
         * Opsional: jika ada nilai status lain yang “aneh” (misal '' / null),
         * kita rapikan jadi queued
         */
        DB::table('reservations')
            ->whereNull('status')
            ->update(['status' => 'queued', 'updated_at' => $now]);

        DB::table('reservations')
            ->where('status', '')
            ->update(['status' => 'queued', 'updated_at' => $now]);
    }

    public function down(): void
    {
        // Tidak ada down yang aman (karena kita ubah tipe kolom + mapping data).
    }

    private function forceStatusToVarchar20(): void
    {
        // Deteksi kolom status ada?
        if (!Schema::hasColumn('reservations', 'status')) return;
        if (DB::getDriverName() === 'sqlite') {
            // SQLite tidak mendukung ALTER ... MODIFY dan kolom status sudah
            // bertipe teks secara praktis; lewati hardening khusus MySQL.
            return;
        }

        // MySQL/MariaDB: pakai ALTER TABLE ... MODIFY
        // Ini meng-overwrite ENUM/short-varchar jadi VARCHAR(20).
        // Default kita set 'queued' dan NOT NULL.
        try {
            DB::statement("ALTER TABLE `reservations` MODIFY `status` VARCHAR(20) NOT NULL DEFAULT 'queued'");
        } catch (\Throwable $e) {
            // Kalau gagal karena alasan tertentu, coba tanpa DEFAULT
            try {
                DB::statement("ALTER TABLE `reservations` MODIFY `status` VARCHAR(20) NOT NULL");
            } catch (\Throwable $e2) {
                // last resort: biarkan, nanti update mungkin gagal lagi, tapi minimal kita sudah mencoba.
            }
        }
    }
};
