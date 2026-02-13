<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('biblio_author', function (Blueprint $table) {
            $table->engine = 'InnoDB';

            $table->id();
            $table->foreignId('biblio_id')->constrained('biblio')->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('authors')->cascadeOnDelete();

            $table->string('role')->nullable(); // pengarang, editor, penerjemah, dll
            $table->unsignedSmallInteger('sort_order')->default(1);

            $table->timestamps();

            $table->unique(['biblio_id', 'author_id', 'role'], 'biblio_author_unique');
            $table->index(['author_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('biblio_author');
    }
};
