<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('reservations')) return;

        Schema::table('reservations', function (Blueprint $table) {

            if (!Schema::hasColumn('reservations', 'queue_no')) {
                $table->unsignedInteger('queue_no')->nullable()->after('branch_id');
                $table->index(['institution_id', 'biblio_id', 'queue_no'], 'idx_resv_queue');
            }

            if (!Schema::hasColumn('reservations', 'ready_item_id')) {
                $table->unsignedBigInteger('ready_item_id')->nullable()->after('status');
                $table->index(['ready_item_id'], 'idx_resv_ready_item');
            }

            if (!Schema::hasColumn('reservations', 'ready_at')) {
                $table->dateTime('ready_at')->nullable()->after('ready_item_id');
            }

            if (!Schema::hasColumn('reservations', 'expires_at')) {
                $table->dateTime('expires_at')->nullable()->after('ready_at');
                $table->index(['status', 'expires_at'], 'idx_resv_expire_scan');
            }

            if (!Schema::hasColumn('reservations', 'expired_at')) {
                $table->dateTime('expired_at')->nullable()->after('expires_at');
            }

            if (!Schema::hasColumn('reservations', 'fulfilled_at')) {
                $table->dateTime('fulfilled_at')->nullable()->after('expired_at');
            }

            if (!Schema::hasColumn('reservations', 'cancelled_at')) {
                $table->dateTime('cancelled_at')->nullable()->after('fulfilled_at');
            }

            if (!Schema::hasColumn('reservations', 'cancelled_by')) {
                $table->unsignedBigInteger('cancelled_by')->nullable()->after('cancelled_at');
            }
        });
    }

    public function down(): void
    {
        // aman: tidak perlu drop untuk menghindari risiko pada DB stabil produksi
    }
};
