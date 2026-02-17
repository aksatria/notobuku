<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('search_stop_words', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institution_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('word', 80);
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->unique(['institution_id', 'branch_id', 'word'], 'search_stop_words_unique');
            $table->index(['institution_id', 'word'], 'search_stop_words_institution_word_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_stop_words');
    }
};

