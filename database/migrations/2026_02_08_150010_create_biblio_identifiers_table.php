<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('biblio_identifiers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('biblio_id');
            $table->string('scheme', 50);
            $table->string('value', 255);
            $table->string('normalized_value', 255);
            $table->string('uri', 512)->nullable();
            $table->timestamps();

            $table->index(['biblio_id', 'scheme']);
            $table->unique(['biblio_id', 'scheme', 'normalized_value']);
            $table->foreign('biblio_id')->references('id')->on('biblio')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('biblio_identifiers');
    }
};
