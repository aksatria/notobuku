<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('member_notifications')) {
            return;
        }

        Schema::table('member_notifications', function (Blueprint $table) {
            if (!Schema::hasColumn('member_notifications', 'reservation_id')) {
                $table->unsignedBigInteger('reservation_id')->nullable()->after('loan_id')->index();
            }
            if (!Schema::hasColumn('member_notifications', 'attempt_count')) {
                $table->unsignedInteger('attempt_count')->default(0)->after('status');
            }
            if (!Schema::hasColumn('member_notifications', 'max_attempts')) {
                $table->unsignedInteger('max_attempts')->default(5)->after('attempt_count');
            }
            if (!Schema::hasColumn('member_notifications', 'next_retry_at')) {
                $table->dateTime('next_retry_at')->nullable()->after('scheduled_for')->index();
            }
            if (!Schema::hasColumn('member_notifications', 'dead_lettered_at')) {
                $table->dateTime('dead_lettered_at')->nullable()->after('sent_at')->index();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('member_notifications')) {
            return;
        }

        Schema::table('member_notifications', function (Blueprint $table) {
            if (Schema::hasColumn('member_notifications', 'dead_lettered_at')) {
                $table->dropColumn('dead_lettered_at');
            }
            if (Schema::hasColumn('member_notifications', 'next_retry_at')) {
                $table->dropColumn('next_retry_at');
            }
            if (Schema::hasColumn('member_notifications', 'max_attempts')) {
                $table->dropColumn('max_attempts');
            }
            if (Schema::hasColumn('member_notifications', 'attempt_count')) {
                $table->dropColumn('attempt_count');
            }
            if (Schema::hasColumn('member_notifications', 'reservation_id')) {
                $table->dropColumn('reservation_id');
            }
        });
    }
};
