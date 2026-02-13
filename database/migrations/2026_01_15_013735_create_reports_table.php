<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->engine = 'InnoDB';

            $table->id();

            $table->foreignId('institution_id')->constrained('institutions')->cascadeOnDelete();
            $table->foreignId('reporter_member_id')->constrained('members')->cascadeOnDelete();

            // target polymorphic simple (type + id)
            $table->enum('target_type', ['post', 'comment', 'member'])->index();
            $table->unsignedBigInteger('target_id')->index();

            $table->string('reason')->nullable();  // spam, sara, pornografi, dll
            $table->text('notes')->nullable();

            $table->enum('status', ['open', 'reviewed', 'dismissed', 'actioned'])->default('open');

            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();

            $table->timestamps();

            $table->index(['institution_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
