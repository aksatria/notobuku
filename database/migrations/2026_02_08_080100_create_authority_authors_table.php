<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('authority_authors', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->string('preferred_name');
            $table->string('normalized_name')->index();
            $table->json('aliases')->nullable();
            $table->json('external_ids')->nullable();
            $table->timestamps();

            $table->unique(['normalized_name'], 'authority_authors_normalized_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('authority_authors');
    }
};
