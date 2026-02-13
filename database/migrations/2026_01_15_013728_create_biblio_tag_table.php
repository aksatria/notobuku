<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('biblio_tag', function (Blueprint $table) {
            $table->engine = 'InnoDB';

            $table->id();
            $table->foreignId('biblio_id')->constrained('biblio')->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained('tags')->cascadeOnDelete();

            $table->enum('source', ['manual', 'ai'])->default('manual');
            $table->enum('status', ['approved', 'draft'])->default('approved');

            $table->timestamps();

            $table->unique(['biblio_id', 'tag_id'], 'biblio_tag_unique');
            $table->index(['tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('biblio_tag');
    }
};
