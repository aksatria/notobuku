<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('authors', function (Blueprint $table) {
            $table->engine = 'InnoDB';

            $table->id();
            $table->string('name');
            $table->string('normalized_name')->nullable();
            $table->string('type')->nullable(); // personal/corporate/meeting
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['name']);
            $table->index(['normalized_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('authors');
    }
};
