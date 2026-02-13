<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('items', function (Blueprint $table) {
            $table->engine = 'InnoDB';

            $table->id();

            $table->foreignId('institution_id')->constrained('institutions')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('shelf_id')->nullable()->constrained('shelves')->nullOnDelete();

            $table->foreignId('biblio_id')->constrained('biblio')->cascadeOnDelete();

            $table->string('barcode', 80)->unique();
            $table->string('accession_number', 80)->unique();
            $table->string('inventory_code', 80)->nullable();

            $table->enum('status', ['available', 'borrowed', 'reserved', 'lost', 'damaged', 'maintenance'])
                ->default('available');

            $table->date('acquired_at')->nullable();
            $table->decimal('price', 14, 2)->nullable();
            $table->string('source')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['institution_id', 'status']);
            $table->index(['biblio_id']);
            $table->index(['branch_id']);
            $table->index(['shelf_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
