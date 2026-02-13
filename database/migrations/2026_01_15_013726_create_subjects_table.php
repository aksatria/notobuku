<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('subjects', function (Blueprint $table) {
            $table->engine = 'InnoDB';

            $table->id();

            // Tajuk subjek (controlled vocabulary)
            $table->string('name');            // ✅ dipakai seeder
            $table->string('code', 50)->nullable(); // kode opsional (mis: PEND, ISLAM)

            $table->timestamps();

            $table->index('name');
            $table->unique(['code'], 'subjects_code_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subjects');
    }
};
