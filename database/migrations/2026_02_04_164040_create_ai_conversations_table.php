<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('ai_conversations', function (Blueprint $table) {
            $table->id(); // PAKAI ID BIASA, BUKAN UUID
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('title', 100)->default('Percakapan Baru');
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['user_id', 'is_active']);
            $table->index(['user_id', 'updated_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('ai_conversations');
    }
};