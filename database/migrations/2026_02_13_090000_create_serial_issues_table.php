<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('serial_issues', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();

            $table->foreignId('institution_id')->constrained('institutions')->cascadeOnDelete();
            $table->foreignId('biblio_id')->constrained('biblio')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();

            $table->string('issue_code', 80);
            $table->string('volume', 60)->nullable();
            $table->string('issue_no', 60)->nullable();
            $table->date('published_on')->nullable();
            $table->date('expected_on')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->enum('status', ['expected', 'received', 'missing', 'claimed'])->default('expected');
            $table->text('notes')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['institution_id', 'biblio_id', 'issue_code'], 'serial_issue_unique');
            $table->index(['institution_id', 'status']);
            $table->index(['branch_id', 'status']);
            $table->index(['published_on']);
            $table->index(['expected_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('serial_issues');
    }
};

