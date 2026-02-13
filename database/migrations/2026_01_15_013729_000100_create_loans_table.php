<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('loans', function (Blueprint $table) {
            $table->engine = 'InnoDB';

            $table->id(); // BIGINT UNSIGNED

            $table->foreignId('institution_id')->constrained('institutions')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();

            $table->string('loan_code', 50)->unique();
            $table->enum('status', ['open', 'closed', 'overdue'])->default('open');

            $table->timestamp('loaned_at')->useCurrent();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('closed_at')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['institution_id', 'status']);
            $table->index(['member_id', 'status']);
            $table->index(['loaned_at']);
            $table->index(['due_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loans');
    }
};
