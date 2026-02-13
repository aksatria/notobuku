<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('budgets', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->decimal('amount', 14, 2)->default(0);
            $table->decimal('spent', 14, 2)->default(0);
            $table->json('meta_json')->nullable();
            $table->timestamps();

            $table->index(['year', 'branch_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budgets');
    }
};
