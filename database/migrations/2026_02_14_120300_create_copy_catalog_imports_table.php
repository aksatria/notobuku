<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('copy_catalog_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('institutions')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('source_id')->nullable()->constrained('copy_catalog_sources')->nullOnDelete();
            $table->foreignId('biblio_id')->nullable()->constrained('biblio')->nullOnDelete();
            $table->string('external_id', 190)->nullable()->index();
            $table->string('title', 255)->nullable();
            $table->string('status', 20)->default('imported'); // imported|failed
            $table->text('error_message')->nullable();
            $table->json('raw_json')->nullable();
            $table->timestamps();

            $table->index(['institution_id', 'created_at']);
            $table->index(['institution_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('copy_catalog_imports');
    }
};

