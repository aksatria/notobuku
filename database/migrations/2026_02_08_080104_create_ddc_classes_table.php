<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ddc_classes', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name');
            $table->string('normalized_name')->index();
            $table->foreignId('parent_id')->nullable()
                ->constrained('ddc_classes')
                ->nullOnDelete();
            $table->unsignedTinyInteger('level')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ddc_classes');
    }
};
