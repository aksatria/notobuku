<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('audits', function (Blueprint $table) {
            $table->engine = 'InnoDB';

            $table->id();

            $table->foreignId('institution_id')->nullable()->constrained('institutions')->nullOnDelete();

            // siapa pelaku (staff/admin/system)
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_role')->nullable(); // super_admin/admin/staff/member/system

            // aksi
            $table->string('action'); // contoh: "loan.create", "loan.return", "biblio.approve"
            $table->string('module')->nullable(); // sirkulasi/katalog/komunitas/dll

            // objek yang terkena aksi (polymorphic)
            $table->string('auditable_type')->nullable();
            $table->unsignedBigInteger('auditable_id')->nullable();

            $table->json('metadata')->nullable();

            // tracking sederhana
            $table->string('ip', 64)->nullable();
            $table->text('user_agent')->nullable();

            $table->timestamps();

            $table->index(['institution_id', 'created_at']);
            $table->index(['action']);
            $table->index(['auditable_type', 'auditable_id']);
            $table->index(['actor_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audits');
    }
};
