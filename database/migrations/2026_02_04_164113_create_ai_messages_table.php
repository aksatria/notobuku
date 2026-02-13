<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('ai_messages', function (Blueprint $table) {
            $table->id(); // PAKAI ID BIASA
            $table->foreignId('conversation_id')->constrained('ai_conversations')->onDelete('cascade');
            $table->enum('role', ['user', 'ai', 'system'])->default('user');
            $table->text('content');
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            $table->index(['conversation_id', 'created_at']);
            $table->index(['conversation_id', 'role']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('ai_messages');
    }
};