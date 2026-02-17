<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('copy_catalog_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('institutions')->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('protocol', 20)->default('sru'); // sru|z3950|p2p
            $table->string('endpoint', 500);
            $table->string('username', 120)->nullable();
            $table->string('password', 160)->nullable();
            $table->json('settings_json')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('priority')->default(10);
            $table->timestamps();

            $table->index(['institution_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('copy_catalog_sources');
    }
};

