<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('purchase_order_lines', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();

            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->foreignId('biblio_id')->nullable()->constrained('biblio')->nullOnDelete();

            $table->string('title');
            $table->string('author_text')->nullable();
            $table->string('isbn', 32)->nullable();

            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 14, 2)->default(0);
            $table->decimal('line_total', 14, 2)->default(0);

            $table->enum('status', ['pending', 'received', 'cancelled'])->default('pending');
            $table->unsignedInteger('received_quantity')->default(0);

            $table->timestamps();

            $table->index(['purchase_order_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_lines');
    }
};
