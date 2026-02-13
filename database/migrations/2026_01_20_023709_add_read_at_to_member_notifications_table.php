<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('member_notifications', function (Blueprint $table) {
            // read_at: untuk fitur "belum dibaca" + "mark as read"
            if (!Schema::hasColumn('member_notifications', 'read_at')) {
                // Letakkan sedekat mungkin dengan status/sent_at (kalau ada)
                if (Schema::hasColumn('member_notifications', 'sent_at')) {
                    $table->timestamp('read_at')->nullable()->after('sent_at');
                } elseif (Schema::hasColumn('member_notifications', 'status')) {
                    $table->timestamp('read_at')->nullable()->after('status');
                } else {
                    $table->timestamp('read_at')->nullable();
                }
            }

            // error_message: dipakai jika pengiriman gagal (aman bila belum ada)
            if (!Schema::hasColumn('member_notifications', 'error_message')) {
                if (Schema::hasColumn('member_notifications', 'status')) {
                    $table->string('error_message', 1000)->nullable()->after('status');
                } else {
                    $table->string('error_message', 1000)->nullable();
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('member_notifications', function (Blueprint $table) {
            if (Schema::hasColumn('member_notifications', 'read_at')) {
                $table->dropColumn('read_at');
            }
            if (Schema::hasColumn('member_notifications', 'error_message')) {
                $table->dropColumn('error_message');
            }
        });
    }
};
