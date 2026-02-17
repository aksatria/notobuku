<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('search_tuning_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institution_id');
            $table->integer('title_exact_weight')->default(80);
            $table->integer('author_exact_weight')->default(40);
            $table->integer('subject_exact_weight')->default(25);
            $table->integer('publisher_exact_weight')->default(15);
            $table->integer('isbn_exact_weight')->default(100);
            $table->unsignedTinyInteger('short_query_max_len')->default(4);
            $table->decimal('short_query_multiplier', 5, 2)->default(1.60);
            $table->decimal('available_weight', 8, 2)->default(10.00);
            $table->decimal('borrowed_penalty', 8, 2)->default(3.00);
            $table->decimal('reserved_penalty', 8, 2)->default(2.00);
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->unique('institution_id', 'search_tuning_settings_institution_unique');
            $table->index(['institution_id', 'updated_at'], 'search_tuning_settings_institution_updated_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_tuning_settings');
    }
};

