<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('books')) return;

        Schema::create('books', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('author')->nullable();
            $table->string('isbn', 32)->nullable()->index();
            $table->string('publisher')->nullable();
            $table->string('year', 10)->nullable();
            $table->string('subject')->nullable();
            $table->string('call_number')->nullable(); // DDC/Call Number
            $table->text('description')->nullable();
            $table->string('cover_path')->nullable();
            $table->timestamps();

            $table->index(['title']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('books');
    }
};
