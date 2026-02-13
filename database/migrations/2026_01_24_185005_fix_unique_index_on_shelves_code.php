<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shelves', function (Blueprint $table) {
            // Drop unique lama: (institution_id, code)
            // Nama index kamu dari error: shelves_inst_code_unique
            try {
                $table->dropUnique('shelves_inst_code_unique');
            } catch (\Throwable $e) {
                // kalau ternyata sudah tidak ada, abaikan
            }

            // Buat unique baru: (institution_id, branch_id, code)
            // code nullable -> MySQL membolehkan multiple NULL
            $table->unique(['institution_id', 'branch_id', 'code'], 'shelves_inst_branch_code_unique');
        });
    }

    public function down(): void
    {
        Schema::table('shelves', function (Blueprint $table) {
            // rollback unique baru
            try {
                $table->dropUnique('shelves_inst_branch_code_unique');
            } catch (\Throwable $e) {
                // abaikan
            }

            // balikin unique lama
            $table->unique(['institution_id', 'code'], 'shelves_inst_code_unique');
        });
    }
};
