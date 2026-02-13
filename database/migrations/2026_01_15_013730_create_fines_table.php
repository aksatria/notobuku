<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('fines', function (Blueprint $table) {
            $table->engine = 'InnoDB';

            $table->id();

            $table->foreignId('institution_id')->constrained('institutions')->cascadeOnDelete();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();

            // FK ini harus nullable + set null
            $table->unsignedBigInteger('loan_item_id')->nullable();

            $table->decimal('amount', 14, 2)->default(0);
            $table->string('currency', 10)->default('IDR');

            $table->string('reason')->nullable();
            $table->enum('status', ['unpaid', 'paid', 'waived'])->default('unpaid');

            $table->timestamp('assessed_at')->useCurrent();
            $table->timestamp('paid_at')->nullable();

            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->foreign('loan_item_id')
                ->references('id')
                ->on('loan_items')
                ->onDelete('set null');

            $table->index(['institution_id', 'status']);
            $table->index(['member_id', 'status']);
            $table->index(['loan_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fines');
    }
};
