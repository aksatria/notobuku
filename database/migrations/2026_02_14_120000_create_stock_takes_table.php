<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('stock_takes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('institutions')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('shelf_id')->nullable()->constrained('shelves')->nullOnDelete();

            $table->string('name', 160);
            $table->string('scope_status', 40)->default('all'); // all|available|borrowed|lost|damaged|maintenance
            $table->string('status', 20)->default('draft'); // draft|in_progress|completed|cancelled
            $table->unsignedInteger('expected_items_count')->default(0);
            $table->unsignedInteger('found_items_count')->default(0);
            $table->unsignedInteger('missing_items_count')->default(0);
            $table->unsignedInteger('unexpected_items_count')->default(0);
            $table->unsignedInteger('scanned_items_count')->default(0);
            $table->json('summary_json')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['institution_id', 'status']);
            $table->index(['institution_id', 'branch_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_takes');
    }
};

