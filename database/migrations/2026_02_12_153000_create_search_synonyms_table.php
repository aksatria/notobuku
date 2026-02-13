<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_synonyms', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institution_id')->index();
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->string('term', 120);
            $table->json('synonyms');
            $table->timestamps();

            $table->unique(['institution_id', 'branch_id', 'term'], 'search_synonyms_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_synonyms');
    }
};
