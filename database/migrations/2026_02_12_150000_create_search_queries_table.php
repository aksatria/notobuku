<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_queries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institution_id')->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('query', 255);
            $table->string('normalized_query', 255)->index();
            $table->unsignedInteger('search_count')->default(1);
            $table->unsignedInteger('last_hits')->default(0);
            $table->timestamp('last_searched_at')->nullable()->index();
            $table->timestamps();

            $table->unique(['institution_id', 'normalized_query']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_queries');
    }
};
