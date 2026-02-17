<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('search_queries', function (Blueprint $table) {
            $table->string('zero_result_status', 24)->default('open')->after('last_hits');
            $table->timestamp('zero_resolved_at')->nullable()->after('zero_result_status');
            $table->unsignedBigInteger('zero_resolved_by')->nullable()->after('zero_resolved_at');
            $table->string('zero_resolution_note', 255)->nullable()->after('zero_resolved_by');
            $table->string('zero_resolution_link', 255)->nullable()->after('zero_resolution_note');

            $table->index(['institution_id', 'last_hits', 'zero_result_status'], 'search_queries_zero_queue_idx');
        });
    }

    public function down(): void
    {
        Schema::table('search_queries', function (Blueprint $table) {
            $table->dropIndex('search_queries_zero_queue_idx');
            $table->dropColumn([
                'zero_result_status',
                'zero_resolved_at',
                'zero_resolved_by',
                'zero_resolution_note',
                'zero_resolution_link',
            ]);
        });
    }
};

