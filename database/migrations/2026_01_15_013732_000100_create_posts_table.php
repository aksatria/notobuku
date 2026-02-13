<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->engine = 'InnoDB';

            $table->id();

            $table->foreignId('institution_id')->constrained('institutions')->cascadeOnDelete();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();

            $table->foreignId('biblio_id')->nullable()->constrained('biblio')->nullOnDelete();

            $table->text('body')->nullable();
            $table->string('image_path')->nullable();
            $table->unsignedTinyInteger('rating')->nullable(); // 1-5

            $table->enum('visibility', ['global', 'mengikuti'])->default('global');
            $table->enum('status', ['published', 'hidden', 'needs_review'])->default('published');

            $table->unsignedInteger('likes_count')->default(0);
            $table->unsignedInteger('comments_count')->default(0);
            $table->unsignedInteger('reports_count')->default(0);

            $table->timestamps();

            $table->index(['institution_id', 'status']);
            $table->index(['member_id']);
            $table->index(['biblio_id']);
            $table->index(['created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
