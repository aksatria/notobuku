<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visitor_counters', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institution_id')->index();
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->unsignedBigInteger('member_id')->nullable()->index();
            $table->enum('visitor_type', ['member', 'non_member'])->default('non_member')->index();
            $table->string('visitor_name', 160)->nullable();
            $table->string('member_code_snapshot', 80)->nullable();
            $table->string('purpose', 160)->nullable();
            $table->timestamp('checkin_at')->index();
            $table->timestamp('checkout_at')->nullable()->index();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->timestamps();

            $table->index(['institution_id', 'checkin_at']);
            $table->index(['institution_id', 'visitor_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visitor_counters');
    }
};
