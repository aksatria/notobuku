<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('search_synonyms', function (Blueprint $table) {
            $table->string('status', 24)->default('approved')->after('synonyms');
            $table->string('source', 24)->default('manual')->after('status');
            $table->unsignedBigInteger('submitted_by')->nullable()->after('source');
            $table->unsignedBigInteger('approved_by')->nullable()->after('submitted_by');
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->timestamp('rejected_at')->nullable()->after('approved_at');
            $table->string('rejection_note', 255)->nullable()->after('rejected_at');

            $table->index(['institution_id', 'status'], 'search_synonyms_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('search_synonyms', function (Blueprint $table) {
            $table->dropIndex('search_synonyms_status_idx');
            $table->dropColumn([
                'status',
                'source',
                'submitted_by',
                'approved_by',
                'approved_at',
                'rejected_at',
                'rejection_note',
            ]);
        });
    }
};

