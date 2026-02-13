<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('shelves', function (Blueprint $table) {
            $table->engine = 'InnoDB';

            $table->id();

            // ✅ WAJIB untuk schema siap multi-institusi
            $table->foreignId('institution_id')->constrained('institutions')->cascadeOnDelete();

            // branch boleh nullable (misal rak umum institusi)
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();

            $table->string('name');
            $table->string('code', 50)->nullable(); // kode rak opsional
            $table->string('location_note')->nullable();

            $table->timestamps();

            $table->index(['institution_id']);
            $table->index(['branch_id']);
            $table->unique(['institution_id', 'code'], 'shelves_inst_code_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shelves');
    }
};
