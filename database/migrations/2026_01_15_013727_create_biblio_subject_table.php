<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('biblio_subject', function (Blueprint $table) {
            $table->engine = 'InnoDB';

            $table->id();
            $table->foreignId('biblio_id')->constrained('biblio')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();

            $table->enum('source', ['manual', 'ai'])->default('manual');
            $table->enum('status', ['approved', 'draft'])->default('approved');

            $table->timestamps();

            $table->unique(['biblio_id', 'subject_id'], 'biblio_subject_unique');
            $table->index(['subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('biblio_subject');
    }
};
