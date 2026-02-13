<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('ai_analytics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('conversation_id')->nullable()->constrained('ai_conversations')->nullOnDelete();
            $table->string('intent', 32)->nullable();
            $table->string('response_type', 32)->nullable();
            $table->unsignedInteger('response_time_ms')->nullable();
            $table->boolean('has_local_results')->default(false);
            $table->string('ai_mode', 16)->default('mock');
            $table->text('question')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['conversation_id', 'created_at']);
            $table->index(['intent', 'response_type']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('ai_analytics');
    }
};
