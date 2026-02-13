<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('biblio_metadata', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('biblio_id')
                ->constrained('biblio')
                ->cascadeOnDelete();
            $table->json('dublin_core_json');
            $table->json('marc_core_json')->nullable();
            $table->timestamps();

            $table->unique(['biblio_id'], 'biblio_metadata_biblio_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('biblio_metadata');
    }
};
