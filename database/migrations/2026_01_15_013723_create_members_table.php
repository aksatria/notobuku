<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('members', function (Blueprint $table) {
            $table->engine = 'InnoDB';

            $table->id();

            $table->foreignId('institution_id')->constrained('institutions')->cascadeOnDelete();

            // user_id boleh unique, karena 1 user = 1 member
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unique('user_id');

            $table->string('member_code', 50)->unique(); // nomor anggota
            $table->string('full_name');

            $table->string('phone', 30)->nullable();
            $table->text('address')->nullable();

            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active');

            // ✅ ini yang dibutuhkan seeder
            $table->date('joined_at')->nullable();

            $table->timestamps();

            $table->index(['institution_id', 'status']);
            $table->index(['member_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('members');
    }
};
