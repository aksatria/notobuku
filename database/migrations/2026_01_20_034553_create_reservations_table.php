<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('reservations')) return;

        Schema::create('reservations', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('institution_id');
            $table->unsignedBigInteger('branch_id')->nullable();

            // Reservasi berbasis judul/biblio
            $table->unsignedBigInteger('biblio_id');
            $table->unsignedBigInteger('member_id');

            // queued | ready | fulfilled | cancelled | expired
            $table->string('status', 20)->default('queued');

            // posisi antrean
            $table->unsignedInteger('queue_no')->default(1);

            // saat sudah tersedia, kita "tawarkan" item tertentu
            $table->unsignedBigInteger('ready_item_id')->nullable();
            $table->dateTime('ready_at')->nullable();
            $table->dateTime('expires_at')->nullable();

            $table->dateTime('fulfilled_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->unsignedBigInteger('cancelled_by')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['institution_id','biblio_id','status']);
            $table->index(['institution_id','member_id','status']);
            $table->index(['institution_id','branch_id','biblio_id','status']);
            $table->index(['ready_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
