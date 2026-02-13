<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('member_notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institution_id')->index();
            $table->unsignedBigInteger('member_id')->index();
            $table->unsignedBigInteger('loan_id')->nullable()->index();

            // due_soon / overdue / ...
            $table->string('type', 40)->index();

            // key internal agar idempotent (due_h2, due_h1, overdue_h1, dst)
            $table->string('plan_key', 40)->nullable()->index();

            // email / whatsapp / inapp
            $table->string('channel', 20)->default('inapp')->index();

            // queued / sent / failed
            $table->string('status', 20)->default('queued')->index();

            // tanggal eksekusi (hari ini)
            $table->dateTime('scheduled_for')->index();

            $table->dateTime('sent_at')->nullable()->index();
            $table->string('error_message', 1000)->nullable();

            $table->longText('payload')->nullable();

            $table->timestamps();

            // Pastikan tidak dobel kirim untuk kombinasi yang sama
            $table->unique(['member_id', 'loan_id', 'type', 'plan_key', 'scheduled_for'], 'mn_unique_once');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_notifications');
    }
};
