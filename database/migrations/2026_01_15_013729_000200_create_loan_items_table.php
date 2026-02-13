<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('loan_items', function (Blueprint $table) {
            $table->engine = 'InnoDB';

            $table->id();

            // PAKAI foreignId (bigint unsigned) + constrained supaya match dengan loans.id
            $table->foreignId('loan_id')
                ->constrained('loans')
                ->cascadeOnDelete();

            $table->foreignId('item_id')
                ->constrained('items')
                ->cascadeOnDelete();

            $table->enum('status', ['borrowed', 'returned'])->default('borrowed');

            $table->timestamp('borrowed_at')->useCurrent();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('returned_at')->nullable();

            $table->timestamps();

            $table->unique(['loan_id', 'item_id'], 'loan_item_unique');
            $table->index(['item_id', 'status']);
            $table->index(['due_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_items');
    }
};
