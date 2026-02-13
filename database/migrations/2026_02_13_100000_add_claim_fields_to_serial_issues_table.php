<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('serial_issues')) {
            return;
        }

        Schema::table('serial_issues', function (Blueprint $table) {
            if (!Schema::hasColumn('serial_issues', 'claimed_at')) {
                $table->timestamp('claimed_at')->nullable()->after('received_at');
            }
            if (!Schema::hasColumn('serial_issues', 'claim_reference')) {
                $table->string('claim_reference', 120)->nullable()->after('status');
            }
            if (!Schema::hasColumn('serial_issues', 'claim_notes')) {
                $table->text('claim_notes')->nullable()->after('notes');
            }
            if (!Schema::hasColumn('serial_issues', 'claimed_by')) {
                $table->foreignId('claimed_by')->nullable()->constrained('users')->nullOnDelete()->after('received_by');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('serial_issues')) {
            return;
        }

        Schema::table('serial_issues', function (Blueprint $table) {
            if (Schema::hasColumn('serial_issues', 'claimed_by')) {
                $table->dropConstrainedForeignId('claimed_by');
            }
            if (Schema::hasColumn('serial_issues', 'claim_notes')) {
                $table->dropColumn('claim_notes');
            }
            if (Schema::hasColumn('serial_issues', 'claim_reference')) {
                $table->dropColumn('claim_reference');
            }
            if (Schema::hasColumn('serial_issues', 'claimed_at')) {
                $table->dropColumn('claimed_at');
            }
        });
    }
};

