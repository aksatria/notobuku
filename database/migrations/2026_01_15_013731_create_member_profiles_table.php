<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('member_profiles', function (Blueprint $table) {
            $table->engine = 'InnoDB';

            $table->id();

            $table->foreignId('member_id')
                ->constrained('members')
                ->cascadeOnDelete();

            $table->text('bio')->nullable();
            $table->string('avatar_path')->nullable();

            // ✅ ini yang dibutuhkan seeder + fitur profil publik/privat
            $table->boolean('is_public')->default(true);

            $table->timestamps();

            $table->unique('member_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_profiles');
    }
};
