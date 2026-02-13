<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('acquisitions_requests', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();

            $table->foreignId('requester_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('source', ['member_request', 'staff_manual']);

            $table->string('title');
            $table->string('author_text')->nullable();
            $table->string('isbn', 32)->nullable();
            $table->text('notes')->nullable();

            $table->enum('priority', ['low', 'normal', 'high', 'urgent'])->default('normal');
            $table->enum('status', ['requested', 'reviewed', 'approved', 'rejected', 'converted_to_po'])->default('requested');

            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();

            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();

            $table->foreignId('rejected_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->string('reject_reason')->nullable();

            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->decimal('estimated_price', 14, 2)->nullable();

            $table->foreignId('book_request_id')->nullable()->constrained('book_requests')->nullOnDelete();

            $table->timestamps();

            $table->index(['status', 'priority']);
            $table->index(['source', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('acquisitions_requests');
    }
};
