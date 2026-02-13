<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('ai_user_contexts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->json('interests')->nullable();
            $table->json('recent_searches')->nullable();
            $table->string('preferred_response', 32)->nullable();
            $table->timestamp('last_updated_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'last_updated_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('ai_user_contexts');
    }
};
