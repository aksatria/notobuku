<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('biblio_ddc', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('biblio_id')
                ->constrained('biblio')
                ->cascadeOnDelete();
            $table->foreignId('ddc_class_id')
                ->constrained('ddc_classes')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['biblio_id', 'ddc_class_id'], 'biblio_ddc_unique');
            $table->index(['ddc_class_id', 'biblio_id'], 'biblio_ddc_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('biblio_ddc');
    }
};
