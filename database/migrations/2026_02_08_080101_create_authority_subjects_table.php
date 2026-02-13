<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('authority_subjects', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->string('preferred_term');
            $table->string('normalized_term')->index();
            $table->string('scheme')->default('local')->index();
            $table->foreignId('parent_id')->nullable()
                ->constrained('authority_subjects')
                ->nullOnDelete();
            $table->json('aliases')->nullable();
            $table->json('external_ids')->nullable();
            $table->timestamps();

            $table->unique(['scheme', 'normalized_term'], 'authority_subjects_scheme_norm_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('authority_subjects');
    }
};
