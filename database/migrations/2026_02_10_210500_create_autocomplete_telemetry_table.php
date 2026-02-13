<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('autocomplete_telemetry', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('institution_id')->nullable();
            $table->string('field', 50);
            $table->string('path', 120)->nullable();
            $table->unsignedInteger('count')->default(0);
            $table->date('day');
            $table->timestamps();

            $table->index(['day', 'field'], 'autocomplete_telemetry_day_field');
            $table->index(['institution_id', 'day'], 'autocomplete_telemetry_institution_day');
            $table->unique(['day', 'user_id', 'field', 'path'], 'autocomplete_telemetry_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('autocomplete_telemetry');
    }
};
