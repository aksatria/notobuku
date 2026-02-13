<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->engine = 'InnoDB';

            $table->id();

            $table->foreignId('institution_id')->constrained('institutions')->cascadeOnDelete();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();

            $table->foreignId('biblio_id')->nullable()->constrained('biblio')->nullOnDelete();
            $table->foreignId('item_id')->nullable()->constrained('items')->nullOnDelete();

            $table->enum('status', ['active', 'fulfilled', 'cancelled', 'expired'])->default('active');

            $table->timestamp('reserved_at')->useCurrent();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('fulfilled_at')->nullable();

            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['institution_id', 'status']);
            $table->index(['member_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
