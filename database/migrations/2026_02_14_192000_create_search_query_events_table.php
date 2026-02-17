<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('search_query_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institution_id')->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->unsignedBigInteger('search_query_id')->nullable()->index();
            $table->string('query', 255);
            $table->string('normalized_query', 255)->index();
            $table->unsignedInteger('hits')->default(0);
            $table->boolean('is_zero_result')->default(false)->index();
            $table->string('suggestion', 180)->nullable();
            $table->decimal('suggestion_score', 5, 2)->nullable();
            $table->timestamp('searched_at')->index();
            $table->timestamps();

            $table->index(['institution_id', 'searched_at'], 'search_query_events_inst_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_query_events');
    }
};

