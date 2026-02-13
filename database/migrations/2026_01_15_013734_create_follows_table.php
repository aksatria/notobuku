<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('follows', function (Blueprint $table) {
            $table->engine = 'InnoDB';

            $table->id();

            $table->foreignId('follower_member_id')->constrained('members')->cascadeOnDelete();
            $table->foreignId('following_member_id')->constrained('members')->cascadeOnDelete();

            $table->timestamps();

            $table->unique(['follower_member_id', 'following_member_id'], 'follow_unique');
            $table->index(['following_member_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('follows');
    }
};
