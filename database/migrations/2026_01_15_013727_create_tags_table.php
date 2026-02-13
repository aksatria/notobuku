<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tags', function (Blueprint $table) {
            $table->engine = 'InnoDB';

            $table->id();
            $table->string('name')->unique();
            $table->string('normalized_name')->nullable();
            $table->timestamps();

            $table->index(['name']);
            $table->index(['normalized_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tags');
    }
};
