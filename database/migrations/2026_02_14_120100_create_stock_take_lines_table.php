<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('stock_take_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_take_id')->constrained('stock_takes')->cascadeOnDelete();
            $table->foreignId('item_id')->nullable()->constrained('items')->nullOnDelete();
            $table->string('barcode', 120)->nullable()->index();
            $table->boolean('expected')->default(true);
            $table->boolean('found')->default(false);
            $table->string('scan_status', 30)->default('pending'); // pending|found|missing|unexpected|out_of_scope
            $table->string('status_snapshot', 40)->nullable();
            $table->string('condition_snapshot', 60)->nullable();
            $table->string('title_snapshot', 255)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('scanned_at')->nullable();
            $table->timestamps();

            $table->unique(['stock_take_id', 'item_id']);
            $table->index(['stock_take_id', 'scan_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_take_lines');
    }
};

