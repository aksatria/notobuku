<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('biblio', function (Blueprint $table) {
            $table->engine = 'InnoDB';

            $table->id();

            // multi-institusi ready
            $table->foreignId('institution_id')->constrained('institutions')->cascadeOnDelete();

            // Data bibliografi inti (ISBD/AACR2/RDA - simplified fields)
            $table->string('title');
            $table->string('subtitle')->nullable();

            // ✅ ISBN perlu ada
            $table->string('isbn', 32)->nullable()->index();

            $table->string('publisher')->nullable();
            $table->unsignedSmallInteger('publish_year')->nullable();
            $table->string('language', 10)->nullable()->default('id');
            $table->string('edition', 50)->nullable();
            $table->string('physical_desc')->nullable(); // contoh: xii + 200 hlm

            // Klasifikasi
            $table->string('ddc', 32)->nullable()->index();         // contoh: 020
            $table->string('call_number', 64)->nullable()->index(); // contoh: 020 PEN

            // Catatan/keterangan
            $table->text('notes')->nullable();

            // --- AI fields (draft & butuh approval)
            $table->longText('ai_summary')->nullable();
            $table->json('ai_suggested_subjects_json')->nullable();
            $table->json('ai_suggested_tags_json')->nullable();
            $table->string('ai_suggested_ddc', 32)->nullable();

            $table->enum('ai_status', ['draft', 'approved', 'rejected'])->default('draft')->index();

            $table->timestamps();

            $table->index(['institution_id', 'title']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('biblio');
    }
};
