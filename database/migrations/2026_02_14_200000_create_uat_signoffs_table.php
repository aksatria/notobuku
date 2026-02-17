<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('uat_signoffs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institution_id')->nullable()->index();
            $table->date('check_date')->index();
            $table->string('status', 24)->default('pending')->index(); // pending|pass|fail
            $table->string('operator_name', 120)->nullable();
            $table->unsignedBigInteger('signed_by')->nullable()->index();
            $table->timestamp('signed_at')->nullable();
            $table->text('notes')->nullable();
            $table->string('checklist_file', 255)->nullable();
            $table->timestamps();

            $table->unique(['institution_id', 'check_date'], 'uat_signoffs_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('uat_signoffs');
    }
};

