<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Buat kolom "name" boleh NULL agar controller yang insert (term, scheme, normalized_term)
        // tidak error lagi pada MySQL strict mode.
        DB::statement("ALTER TABLE `subjects` MODIFY `name` VARCHAR(255) NULL");
    }

    public function down(): void
    {
        // Kembalikan ke NOT NULL (hati-hati kalau sudah ada row yang name-nya NULL)
        // Kita set NULL -> '' biar aman sebelum balik ke NOT NULL.
        DB::statement("UPDATE `subjects` SET `name` = COALESCE(`name`, '') WHERE `name` IS NULL");
        DB::statement("ALTER TABLE `subjects` MODIFY `name` VARCHAR(255) NOT NULL");
    }
};
